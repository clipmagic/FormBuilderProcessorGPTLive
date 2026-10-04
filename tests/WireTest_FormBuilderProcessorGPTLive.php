<?php namespace ProcessWire;

/**
 * Portable PHP regression suite. No saved forms, entries, API calls or uninstall.
 * Run through WireTests; all form fixtures remain in memory. Temporary rate-limit
 * session/cache records and global language/form context are restored on failure.
 */
class WireTest_FormBuilderProcessorGPTLive extends WireTest {
    private $temporaryNames = [];

    /** An isolated form with predictable tool names, independent of site content. */
    private function newForm(): FormBuilderForm {
        $forms = wire('modules')->get('FormBuilder');
        require_once wire('config')->paths->FormBuilder . 'FormBuilderProcessor.php';
        $main = $forms->forms(); // Loads FormBuilder's form/field classes without loading a saved form.
        $form = new FormBuilderForm($main);
        $form->name = 'gptlive_fixture';
        $form->set('FormBuilderProcessorGPTLive', []);
        return $form;
    }

    /** Execute each group with framework reporting and unconditional cleanup. */
    public function execute() {
        $language = wire('user')->language;
        $languages = wire('languages');
        $forms = wire('forms');
        $root = wire('config')->urls->root;
        $status = http_response_code();
        $headers = headers_list();
        // CLI runners may already have printed results; response helpers still set headers.
        set_error_handler(static function($level, $message, $file) {
            if($level === E_WARNING && basename($file) === 'FormBuilderProcessorGPTLive.module.php'
                && (strpos($message, 'headers already sent') !== false || strpos($message, 'Cannot modify header information') !== false)) return true;
            return false;
        });
        try {
            foreach(['Conditions', 'Assets', 'Schema', 'Protection', 'Translations', 'Request', 'Uninstall', 'MissingModel', 'HttpTransport'] as $group) {
                $this->{'test' . $group}();
            }
        } finally {
            wire('user')->set('language', $language);
            wire()->fuel()->set('languages', $languages);
            wire()->fuel()->set('forms', $forms);
            wire('config')->urls->root = $root;
            foreach($this->temporaryNames as $name) {
                foreach(['tokens_', 'starts_'] as $prefix) wire('session')->removeFor('FormBuilderProcessorGPTLive', $prefix . $name);
                wire('cache')->delete('gptlive-starts-' . hash('sha256', $name . ':' . wire('session')->getIP()));
            }
            $this->temporaryNames = [];
            restore_error_handler();
            if(!headers_sent()) {
                header_remove();
                foreach($headers as $header) header($header, false);
                if($status) http_response_code($status);
            }
        }
    }

    /** Intercept native HTTP sends; no credentials or paid requests leave the test. */
    private function testHttpTransport() {
        $action = wire('modules')->get('FormBuilderProcessorGPTLive');
        $action->fbForm($this->newForm());
        $payload = ['transport' => ['sdp' => "offer\r\n"]];
        $calls = 0;
        $status = 201;
        $response = json_encode(['transport' => ['sdp' => "answer\r\n"]]);
        $hook = wire()->addHookBefore('WireHttp::send', static function($event) use (&$calls, &$status, &$response, $payload) {
            $calls++;
            $http = $event->object;
            $headers = $http->getHeaders();
            $options = $event->arguments(3);
            if($event->arguments(0) !== 'https://api.openai.com/v1/live/sessions'
                || $event->arguments(2) !== 'POST'
                || json_decode($event->arguments(1), true) !== $payload
                || ($headers['authorization'] ?? '') !== 'Bearer fixture-key'
                || ($headers['content-type'] ?? '') !== 'application/json'
                || $http->getTimeout() != 30
                || !is_string($options['use']) || $options['followRedirects'] !== false) {
                throw new \RuntimeException('WireHttp request contract mismatch');
            }
            (new \ReflectionMethod($http, 'setResponseHeader'))->invoke($http, ['HTTP/1.1 ' . $status . ' Fixture']);
            $event->replace = true;
            $event->return = $response;
        });
        $logHook = wire('log')->addHookBefore('save', static function($event) {
            if($event->arguments(0) !== 'formbuilder-gpt-live') return;
            $event->replace = true; $event->return = true;
        });
        try {
            $send = new \ReflectionMethod($action, 'createLiveSession');
            $result = json_decode($send->invoke($action, 'http_fixture', $payload, 'fixture-key'), true);
            if(($result['transport']['sdp'] ?? '') !== "answer\r\n" || $calls !== 1) throw new \RuntimeException('WireHttp success mismatch');
            $status = 404;
            $response = json_encode(['error' => ['code' => 'model_not_found', 'message' => 'private detail']]);
            $result = json_decode($send->invoke($action, 'http_fixture', $payload, 'fixture-key'), true);
            if(empty($result['manualOnly']) || strpos($result['error'], 'private detail') !== false || $calls !== 2) throw new \RuntimeException('WireHttp service error mismatch');
            $status = 0; $response = false;
            $result = json_decode($send->invoke($action, 'http_fixture', $payload, 'fixture-key'), true);
            if(empty($result['error']) || $calls !== 3) throw new \RuntimeException('WireHttp transport failure mismatch');
            $this->ok('WireHttp JSON/auth/timeout/single-attempt contract and success/service/transport responses');
        } finally {
            wire()->removeHook($hook);
            wire('log')->removeHook($logHook);
        }
    }

    /** A stale Action selection must leave native form rendering usable. */
    private function testMissingModel() {
        $action = wire('modules')->get('FormBuilderProcessorGPTLive');
        $form = $this->newForm();
        $form->set('FormBuilderProcessorGPTLive', ['agentId' => 'missing_' . bin2hex(random_bytes(16))]);
        $action->fbForm($form);
        $input = wire('modules')->get('InputfieldForm');
        $action->form($input);
        $writes = 0;
        $hook = wire('log')->addHookBefore('save', static function($event) use (&$writes) {
            if($event->arguments(0) !== 'formbuilder-gpt-live') return;
            $writes++;
            $event->replace = true;
            $event->return = true;
        });
        $scripts = wire('config')->scripts;
        wire('config')->scripts = clone $scripts;
        try {
            $action->renderReady();
            $html = $input->render();
            if(strpos($html, 'data-gpt-live-unavailable="1"') === false || strpos($html, 'data-gpt-live-session-url') !== false || $writes !== 1) {
                throw new \RuntimeException('Missing model did not render manual-only and log');
            }
            $parser = new \ReflectionMethod($action, 'parseLiveSessionResponse');
            $result = json_decode($parser->invoke($action, $form->name, json_encode(['error' => ['code' => 'model_not_found', 'message' => 'private service detail']]), 404, ''), true);
            if(empty($result['manualOnly']) || strpos($result['error'], 'private service detail') !== false || $writes !== 2) {
                throw new \RuntimeException('Model rejection did not return private manual-only fallback');
            }
            $this->ok('missing saved model renders manual-only and logs; provider rejection hides private details');
        } finally {
            wire('log')->removeHook($hook);
            wire('config')->scripts = $scripts;
        }
    }

	/** Conditions regressions using unsaved fixtures. */
	private function testConditions() {
		$module = wire('modules')->get('FormBuilderProcessorGPTLive');
		$method = new \ReflectionMethod($module, 'formFieldConditionsMatch');
		$cases = json_decode(file_get_contents(wire('config')->paths->siteModules . 'FormBuilderProcessorGPTLive/tests/conditions.json'), true);
		foreach($cases as $index => $case) {
		    $actual = $method->invoke($module, $case['selector'], $case['values']);
		    if($actual !== $case['expected']) throw new \RuntimeException('PHP condition mismatch at case ' . $index . ': ' . $case['selector']);
		}
		$this->ok('PHP shared condition cases: ' . count($cases));
	}

	/** Assets regressions using unsaved fixtures. */
	private function testAssets() {
		$module = wire('modules')->get('FormBuilderProcessorGPTLive');
		$form = $this->newForm();
		$module->fbForm($form);
		$method = new \ReflectionMethod($module, 'configuredAssetUrl');
		$originalRoot = wire('config')->urls->root;
		try {
		    foreach(['jsURL', 'cssURL'] as $setting) {
		        foreach([
		            ['/', '', '/default.asset', '/default.asset'],
		            ['/', ' custom.asset ', '/default.asset', '/custom.asset'],
		            ['/sandbox/', '/custom.asset', '/default.asset', '/sandbox/custom.asset'],
		            ['/sandbox/', '/sandbox/custom.asset', '/default.asset', '/sandbox/custom.asset'],
		            ['/sandbox/', 'https://cdn.example.test/custom.asset?v=1', '/default.asset', 'https://cdn.example.test/custom.asset?v=1'],
		            ['/sandbox/', '//cdn.example.test/custom.asset', '/default.asset', '//cdn.example.test/custom.asset'],
		        ] as [$root, $saved, $default, $expected]) {
		            wire('config')->urls->root = $root;
		            $form->set($module->className(), [$setting => $saved]);
		            if($method->invoke($module, $setting, $default) !== $expected) throw new \RuntimeException('Asset URL mismatch: ' . $setting . ' / ' . $saved);
		        }
		    }
		} finally {
		    wire('config')->urls->root = $originalRoot;
		}
		$form->set($module->className(), []);
		$default = (new \ReflectionMethod($module, 'defaultScriptPath'))->invoke($module);
		$url = $method->invoke($module, 'jsURL', $default);
		if(strpos($url, $default . '?v=') !== 0) throw new \RuntimeException('Local script cache version missing');
		$this->ok("default/custom/external/subdirectory asset URLs and local cache version");
	}

	/** Schema regressions using unsaved fixtures. */
	private function testSchema() {
		$module = wire('modules')->get('FormBuilderProcessorGPTLive');
		$builder = new \ReflectionMethod($module, 'buildSessionPayload');
		$form = $this->newForm();
		$form->nextText = 'Continue';
		$agent = (object) ['apiKey' => 'unused-test-key', 'model' => 'test-model'];
		$fields = ['name' => ['type' => 'string', 'description' => 'Your name']];
		$labels = ['name' => 'Name “quoted” 日本語'];
		$known = [['name' => 'name', 'label' => 'Name', 'value' => 'Test visitor']];
		foreach([[1,1,false,true],[1,1,true,true],[1,2,true,true],[2,2,true,true],[1,2,false,false],[1,1,true,false]] as [$page,$pages,$submit,$supported]) {
		 $request = $builder->invoke($module, $form, $supported ? $fields : [], $supported ? $labels : [], $supported ? $known : [], $page, $pages, ['allowVisitorRequestedSubmission' => $submit], "offer\r\n", $agent->model);
		 // Existing answers must reach the voice agent for every supported page.
		 if($supported) {
		  $context = ' ' . (new \ReflectionMethod($module, 'buildPageContextInstructions'))->invoke($module, $labels, $known);
		  if(substr($request['session']['instructions'], -strlen($context)) !== $context) throw new \RuntimeException('Voice startup did not receive existing answers');
		 }
		 $review = (new \ReflectionMethod($module, 'buildPageReviewInstructions'))->invoke($module, $form, $page, $pages, $supported, $submit && $page === $pages && $supported);
		 if(!str_contains($request['session']['instructions'], $review)) throw new \RuntimeException('Voice startup did not receive current-page navigation/submission policy');
		 $responses = $request['session']['delegation']['responses'];
		 foreach([$request['session']['instructions'], $responses['instructions']] as $instructions) {
		  foreach(['including on previous pages', 'An empty form field does not mean the visitor has not answered verbally', 'their presence alone does not prove the visitor chose them', 'earlier permission does not carry forward', 'that is not proof of success', 'Never speak a visitor confirmation on their behalf'] as $rule) {
		   if(!str_contains($instructions, $rule)) throw new \RuntimeException('Agent review policy missing: ' . $rule);
		  }
		 }
		 $expectedTools = $supported ? (($submit && $page === $pages) ? 2 : 1) : 0;
		 $this->check('Preparation/submission tool count', $expectedTools, count($responses['tools']));
		 $this->check('SDP CRLF preserved', "offer\r\n", $request['transport']['sdp']);
		 $this->check('Delegation model retained', 'test-model', $responses['model']);
		 if($supported) $this->check('Preparation tool uses fixture name', 'prepare_gptlive_fixture', $responses['tools'][0]['name']);
		 if($expectedTools === 2) $this->check('Submission tool uses fixture name', 'submit_gptlive_fixture', $responses['tools'][1]['name']);
		}
		
		// Service response handling still returns only SDP or the Action fallback.
		$module->fbForm($form);
		$parser = new \ReflectionMethod($module, 'parseLiveSessionResponse');
		$hook = wire('log')->addHookBefore('save', static function($event) {
		    if($event->arguments(0) !== 'formbuilder-gpt-live') return;
		    $event->replace = true;
		    $event->return = true;
		});
		try {
		    foreach([[false, 0, 'test transport error'], ['not JSON', 200, ''], [json_encode(['error' => ['message' => 'private service detail']]), 400, ''], [json_encode(['session' => ['private' => 'value']]), 200, '']] as [$body, $status, $error]) {
		        $reply = json_decode($parser->invoke($module, 'gptlive_fixture', $body, $status, $error), true);
		        if($reply !== ['error' => $module->getVoiceMessages()['fallback']]) throw new \RuntimeException('Service failure no longer uses the Action fallback');
		    }
		    $body = json_encode(['transport' => ['sdp' => "answer\r\n"], 'session' => ['private' => 'value']]);
		    $reply = json_decode($parser->invoke($module, 'gptlive_fixture', $body, 201, ''), true);
		    if($reply !== ['transport' => ['sdp' => "answer\r\n"]]) throw new \RuntimeException('Service response exposed extra data or changed SDP');
		} finally {
		    wire('log')->removeHook($hook);
		}
		$this->ok('Startup receives existing answers; six payload policy scenarios');
		$this->ok("service errors/fallback and SDP-only response contract");
	}

	/** Protection regressions using unsaved fixtures. */
	private function testProtection() {
		$module = wire('modules')->get('FormBuilderProcessorGPTLive');
		$form = $this->newForm();
		$form->name = 'gptlive_fixture_' . bin2hex(random_bytes(8));
		$this->temporaryNames[] = $form->name;
		$module->fbForm($form);
		$call = static function($name, ...$args) use($module) {
		    return (new \ReflectionMethod($module, $name))->invoke($module, ...$args);
		};
		$assert = function($ok, $label) { if(!$ok) throw new \RuntimeException($label); $this->ok($label); };
		$token = $call('issueFormToken', $form->name);
		$assert($call('acceptFormToken', $form->name, $token), 'rendered token accepted');
		$assert(!$call('acceptFormToken', $form->name, str_repeat('0', 64)), 'forged token rejected');
		$assert(!$call('acceptFormToken', 'another_form', $token), 'cross-form token rejected');
		$ns = $module->className();
		$record = wire('session')->getFor($ns, 'tokens_' . $form->name);
		$record[hash('sha256', $token)]['expires'] = time() - 1;
		wire('session')->setFor($ns, 'tokens_' . $form->name, $record);
		$assert(!$call('acceptFormToken', $form->name, $token), 'expired token rejected');
		$form->set($ns, ['visitorStartLimit' => 2, 'ipStartLimit' => 0, 'startWindowMinutes' => 10]);
		$assert($call('reserveVoiceStart', $form->name), 'visitor first start');
		$assert($call('reserveVoiceStart', $form->name), 'visitor second start');
		$assert(!$call('reserveVoiceStart', $form->name), 'visitor excess start blocked');
		wire('session')->setFor($ns, 'starts_' . $form->name, [time() - 601]);
		$assert($call('reserveVoiceStart', $form->name), 'visitor allowance recovers after window');
		$form->set($ns, ['visitorStartLimit' => 0, 'ipStartLimit' => 2, 'startWindowMinutes' => 10]);
		$assert($call('reserveVoiceStart', $form->name), 'IP first start');
		$assert($call('reserveVoiceStart', $form->name), 'IP second start');
		$assert(!$call('reserveVoiceStart', $form->name), 'IP excess start blocked');
		$cacheKey = 'gptlive-starts-' . hash('sha256', $form->name . ':' . wire('session')->getIP());
		wire('cache')->delete($cacheKey);
		$assert($call('reserveVoiceStart', $form->name), 'IP allowance recovers after expiry');
		wire('cache')->delete($cacheKey);
		$form->set($ns, ['message_fallback' => 'Custom fallback <plain text>']);
		$assert($module->getVoiceMessages()['fallback'] === 'Custom fallback <plain text>', 'saved fallback used');
		$fields = wire('modules')->get('InputfieldWrapper');
		$module->getConfigInputfields($fields);
		$assert((bool) $fields->getChildByName('visitorStartLimit'), 'Action exposes visitor limit');
		$assert((bool) $fields->getChildByName('message_fallback'), 'Action exposes existing fallback');
		// Shared fields replace duplicates while existing saved text remains readable.
		$assert(!$fields->getChildByName('message_startingSession') && !$fields->getChildByName('message_changingPage'), 'duplicate Action fields removed');
		$form->set($ns, ['message_startingSession' => 'Previously saved start']);
		$assert($module->getVoiceMessages()['starting'] === 'Previously saved start', 'retired message remains usable');
		$form->set($ns, ['message_starting' => 'Shared start', 'message_startingSession' => 'Old start']);
		$assert($module->getVoiceMessages()['starting'] === 'Shared start', 'shared message takes precedence');
		$assert(!isset($module->getVoiceMessages()['startingSession']), 'browser catalog contains shared keys only');
		
		
		// LanguageSupport is optional; exercise resolution without installing it or saving a user.
		$originalLanguage = wire('user')->language;
		$language = new WireData();
		$language->id = 42;
		$language->title = 'Test language';
		$language->addHook('isDefault', static function($event) { $event->return = false; });
		wire('user')->set('language', $language);
		$form->set($ns, ['message_fallback' => 'Default fallback', 'message_fallback__lang42' => 'Translated fallback']);
		$assert($module->getVoiceMessages()['fallback'] === 'Translated fallback', 'visitor language selects saved translation');
		$form->set($ns, ['message_fallback' => 'Default fallback', 'message_fallback__lang42' => '']);
		$assert($module->getVoiceMessages()['fallback'] === 'Default fallback', 'empty translation falls back to default');
		$form->set($ns, ['message_starting' => 'Default start', 'message_startingSession__lang42' => 'Translated old start']);
		$assert($module->getVoiceMessages()['starting'] === 'Translated old start', 'retired translation remains usable');
		wire('user')->set('language', $originalLanguage);
		$assert($call('recentStarts', [100, 101, 200, '200'], 200, 100) === [101, 200], 'rolling boundary excludes expired and malformed timestamps');
		$form->set($ns, ['visitorStartLimit' => 0, 'ipStartLimit' => 0]);
		$assert($call('reserveVoiceStart', $form->name) && $call('reserveVoiceStart', $form->name), 'explicit zero disables limits');
		
		// Reproduce the owner's 1 / 1 / 1 settings with expired visitor and IP starts.
		$form->set($ns, ['visitorStartLimit' => 1, 'ipStartLimit' => 1, 'startWindowMinutes' => 1]);
		wire('session')->setFor($ns, 'starts_' . $form->name, [time() - 61]);
		wire('cache')->save($cacheKey, [time() - 61], 600);
		try {
		    $assert($call('reserveVoiceStart', $form->name), '1/1/1 accepts a start after one minute');
		    $assert(!$call('reserveVoiceStart', $form->name), '1/1/1 blocks another start within the minute');
		} finally {
		    wire('cache')->delete($cacheKey);
		}
		
		$assert((bool) $fields->getChildByName('requestTimeoutSeconds'), 'Action exposes request timeout');
		foreach([[[], 30], [['requestTimeoutSeconds' => 7], 7], [['requestTimeoutSeconds' => 0], 1], [['requestTimeoutSeconds' => 999], 300]] as [$settings, $expected]) {
		    $form->set($ns, $settings);
		    $assert($call('requestTimeoutSeconds') === $expected, 'request timeout default/saved/bounds: ' . $expected);
		}
	}

	/** Translations regressions using unsaved fixtures. */
	private function testTranslations() {
		require_once wire('config')->paths->wire . 'modules/LanguageSupport/LanguageTranslator.php';
		require_once wire('config')->paths->wire . 'modules/LanguageSupport/LanguageParser.php';
		$module = wire('modules')->get('FormBuilderProcessorGPTLive');
		$form = $this->newForm();
		$form->set($module->className(), []);
		$module->fbForm($form);
		$file = wire('config')->paths->siteModules . 'FormBuilderProcessorGPTLive/FormBuilderProcessorGPTLiveMessages.php';
		$defaults = (new \ReflectionMethod($module, 'defaultMessages'))->invoke($module);
		$labels = (new \ReflectionMethod($module, 'defaultMessageLabels'))->invoke($module);
		$parser = (new \ReflectionClass(LanguageParser::class))->newInstanceWithoutConstructor();
		$matches = (new \ReflectionMethod($parser, 'parseFile'))->invoke($parser, $file);
		$found = $matches[2][3];
		$this->check('Native scanner discovers defaults and labels', true, count($found) >= count($defaults) + count($labels));
		
		// Use ProcessWire's translator and native __() lookup without a persisted Language page.
		$translator = (new \ReflectionClass(LanguageTranslator::class))->newInstanceWithoutConstructor();
		$translator->wire(wire());
		(new \ReflectionProperty($translator, 'rootPath'))->setValue($translator, wire('config')->paths->root);
		$domain = $translator->filenameToTextdomain($file);
		foreach($defaults as $text) $translator->setTranslation($domain, $text, $text);
		$translator->setTranslation($domain, $defaults['fallback'], 'Translated module fallback & "review"');
		$language = new WireData();
		$language->id = 42;
		$language->addHook('isDefault', static function($event) { $event->return = false; });
		$language->addHook('translator', static function($event) use ($translator) { $event->return = $translator; });
		$originalLanguage = wire('user')->language;
		$originalLanguages = wire('languages');
		try {
		    wire('user')->set('language', $language);
		    $languages = new WireData();
		    $languages->addHook('getDefault', static function($event) use ($language) { $event->return = $language; });
		    wire()->wire('languages', $languages);
		    if($module->getVoiceMessages()['fallback'] !== 'Translated module fallback & "review"') throw new \RuntimeException('Module file translation was not used');
		    $form->set($module->className(), ['message_fallback' => 'Saved default override']);
		    if($module->getVoiceMessages()['fallback'] !== 'Saved default override') throw new \RuntimeException('Default Action override lost');
		    $form->set($module->className(), ['message_fallback' => 'Saved default override', 'message_fallback__lang42' => 'Saved language override']);
		    if($module->getVoiceMessages()['fallback'] !== 'Saved language override') throw new \RuntimeException('Language Action override lost');
		} finally {
		    wire()->fuel()->set('languages', $originalLanguages);
		    wire('user')->set('language', $originalLanguage);
		}
		$form->set($module->className(), []);
		$fields = wire('modules')->get('InputfieldWrapper');
		$module->getConfigInputfields($fields);
		$field = $fields->getChildByName('message_fallback');
		if((string) $field->value !== '' || $field->placeholder !== $defaults['fallback']) throw new \RuntimeException('Admin defaults would mask language-file translations');
		$this->ok("native scanner finds all message defaults/labels; native textdomain lookup; Action override precedence; blank inherited defaults");
	}

	/** Request regressions using unsaved fixtures. */
	private function testRequest() {
		$first = wire('modules')->get('FormBuilderProcessorGPTLive');
		$second = wire('modules')->get('FormBuilderProcessorGPTLive');
		$first->fbForm($this->newForm());
		if($first === $second) throw new \RuntimeException('Test requires distinct Action instances');
		$writes = 0;
		$hook = wire('log')->addHookBefore('save', static function($event) use (&$writes) {
		    if($event->arguments(0) !== 'formbuilder-gpt-live') return;
		    $writes++;
		    $event->replace = true; // Observe failures without writing test diagnostics to the log.
		    $event->return = true;
		});
		try {
		    $handler = new \ReflectionMethod($first, 'handleSessionRequest');
		    $missing = 'gptlive_missing_' . bin2hex(random_bytes(8));
		    if(wire('forms')->form($missing, false)) throw new \RuntimeException('Fixture name collision');
		    $one = $handler->invoke($first, $missing);
		    $two = $handler->invoke($second, $missing);
		    $three = $handler->invoke($first, $missing);
		    if($writes !== 1 || $one !== $two || $one !== $three || !isset(json_decode($one, true)['error'])) {
		        throw new \RuntimeException('Endpoint reuse mismatch: writes=' . $writes . ', equal=' . (int) ($one === $two && $one === $three));
		    }
		    $this->ok("one endpoint execution across distinct Actions and repeated path-hook calls");
		} finally {
		    wire('log')->removeHook($hook);
		}
	}

	/** Uninstall regressions using unsaved fixtures. */
	private function testUninstall() {
		$modules = wire('modules');
		$action = $modules->get('FormBuilderProcessorGPTLive');
		$original = wire('forms');
		$fake = new class extends \ProcessWire\WireData {
		    public $items = [];
		    public function getFormNames() { return array_keys($this->items); }
		    public function form($name) { return $this->items[$name]; }
		};
		try {
		    wire()->fuel()->set('forms', $fake);
		    $selected = $this->newForm();
		    $selected->pluginActions = [$action->className()];
		    $disabled = clone $selected;
		    $disabled->addFlag(\ProcessWire\FormBuilderProcessor::formFlagDisabled);
		    $plain = $this->newForm();
		    $fake->items = ['zebra' => $selected, 'alpha' => $selected, 'disabled' => $disabled, 'plain' => $plain];
		    $check = function($class, $reasonMode, $initial = true) use ($action) {
		        $event = new \ProcessWire\HookEvent();
		        $event->arguments = [$class, $reasonMode];
		        $event->return = $initial;
		        $action->guardActionUninstall($event);
		        return $event->return;
		    };
		    $reason = $modules->isUninstallable($action->className(), true);
		    $this->check('Native boolean uninstall veto', false, $modules->isUninstallable($action->className()));
		    if(strpos($reason, 'alpha, zebra') === false || strpos($reason, 'disabled') !== false) throw new \RuntimeException('Incorrect blocking form list');
		    if($check('AgentTools', true) !== true || $check($action->className(), true, 'Core reason') !== 'Core reason') throw new \RuntimeException('Unrelated/core checks changed');
		    $fake->items = ['disabled' => $disabled, 'plain' => $plain];
		    if($check($action->className(), true) !== true) throw new \RuntimeException('Unused Action blocked');
		    $this->ok("synthetic selected form veto; sorted names, disabled/unselected exclusions, core reasons and unused Action");
		} finally {
		    wire()->fuel()->set('forms', $original);
		}
	}
}
