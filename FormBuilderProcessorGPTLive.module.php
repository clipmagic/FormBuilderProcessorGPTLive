<?php namespace ProcessWire;

/**
 * FormBuilder Action bridging server-defined form fields to a GPT-Live browser client.
 * Builds page-specific tool schemas and creates sessions using AgentTools credentials.
 * Browser tool calls prepare controls; FormBuilder retains validation and processing.
 * No API key is included in rendered attributes or session responses.
 */
require_once __DIR__ . '/classes/Messages.php';
require_once __DIR__ . '/classes/Protection.php';
require_once __DIR__ . '/classes/Configuration.php';
require_once __DIR__ . '/classes/Assets.php';
require_once __DIR__ . '/classes/Rendering.php';
require_once __DIR__ . '/classes/Session.php';
require_once __DIR__ . '/classes/Instructions.php';
require_once __DIR__ . '/classes/FormContext.php';
require_once __DIR__ . '/classes/Fields.php';

class FormBuilderProcessorGPTLive extends FormBuilderProcessorAction {
	use FormBuilderProcessorGPTLiveMessages;
	use FormBuilderProcessorGPTLiveProtection;
	use FormBuilderProcessorGPTLiveConfiguration;
	use FormBuilderProcessorGPTLiveAssets;
	use FormBuilderProcessorGPTLiveRendering;
	use FormBuilderProcessorGPTLiveSession;
	use FormBuilderProcessorGPTLiveInstructions;
	use FormBuilderProcessorGPTLiveFormContext;
	use FormBuilderProcessorGPTLiveFields;
	/** Shared route; the saved form Action determines which requests are enabled. */
	private const ENDPOINT_ROUTE = '/formbuilder-gpt-live/{formName}/';
	/** Stable tool names must match the rendered attributes used by the browser. */
	private const TOOL_NAME_PREFIX = 'prepare_';
	private const SUBMIT_TOOL_NAME_PREFIX = 'submit_';

	/** Register one ProcessWire URL hook for every FormBuilder GPT-Live form. */
	public function init() {
		$this->wire()->modules->addHookAfter('isUninstallable', $this, 'guardActionUninstall');
		$root = rtrim((string) $this->wire()->config->urls->root, '/');
		$this->wire()->addHook($root . self::ENDPOINT_ROUTE, function(HookEvent $event) {
			return $this->handleSessionRequest((string) $event->arguments('formName'));
		});
		$this->wire()->addHookAfter('FormBuilder::embed', $this, 'addRequiredPermissionsToEmbed');
		$this->wire()->addHookAfter('FormBuilderProcessor::renderOrProcessReady', $this, 'refreshRenderedChoices', ['priority' => 200]);
	}

	/** Publish the native choice contract after site hooks populate the rendered controls. */
	public function refreshRenderedChoices(HookEvent $event) {
		if($event->object !== $this->processor() || $event->arguments(1) !== FormBuilderMaker::submitTypeNone) return;
		$this->renderReady();
	}

	/** Hook for trusted server-side, form-specific guidance; no customization by default. */
	public function ___getAssistantGuidance(array $context): string {
		return '';
	}

	/** Block native admin/API uninstall while an enabled form still selects this Action. */
	public function guardActionUninstall(HookEvent $event) {
		$class = $event->arguments(0);
		if(is_object($class)) $class = $class->className();
		if(ltrim((string) $class, '\\') !== $this->className() && ltrim((string) $class, '\\') !== __CLASS__) return;
		// Preserve core dependency/permanent-module reasons rather than replacing them.
		if($event->return !== true) return;
		$forms = $this->wire('forms');
		if(!$forms) $forms = $this->wire()->modules->get('FormBuilder');
		// Module settings can be opened before any form processor has been loaded.
		require_once $this->wire()->config->paths->FormBuilder . 'FormBuilderProcessor.php';
		$names = [];
		foreach($forms->getFormNames() as $name) {
			$form = $forms->form($name);
			// FormBuilder has disabled forms, not a page-style published status.
			if($form->hasFlag(FormBuilderProcessor::formFlagDisabled)) continue;
			if(in_array($this->className(), (array) $form->pluginActions, true)) $names[] = $name;
		}
		if(!$names) return;
		sort($names, SORT_NATURAL | SORT_FLAG_CASE);
		$reason = sprintf($this->_('Disable the GPT-Live Action on these forms and save them before uninstalling: %s'), implode(', ', $names));
		// ProcessWire renders this reason in Modules; boolean callers only need the veto.
		$event->return = $event->arguments(1) ? $reason : false;
	}

	/** Allow voice and user-initiated clipboard access on iframe embeds that enable this action. */
	public function addRequiredPermissionsToEmbed(HookEvent $event) {
		$forms = $this->wire('forms');
		$form = $forms ? $forms->form($event->arguments(0), false) : false;
		if(!$form || !in_array($this->className(), (array) $form->pluginActions, true)) return;
		$markup = (string) $event->return;
		$event->return = preg_replace_callback('/<iframe\b[^>]*>/i', function($matches) {
			$tag = $matches[0];
			$permissions = ['microphone', 'clipboard-write'];
			if(preg_match('/\ballow\s*=\s*(["\'])(.*?)\1/i', $tag, $allow)) {
				$policy = trim($allow[2], " ;");
				foreach($permissions as $permission) {
					if(!preg_match('/(?:^|;)\s*' . preg_quote($permission, '/') . '(?:\s|;|$)/i', $policy)) {
						$policy = $policy === '' ? $permission : $policy . '; ' . $permission;
					}
				}
				$tag = str_replace($allow[0], 'allow=' . $allow[1] . $policy . $allow[1], $tag);
			} else {
				$tag = preg_replace('/^<iframe\b/i', '<iframe allow="microphone; clipboard-write"', $tag, 1);
			}
			return $tag;
		}, $markup, 1);
	}

}
