<?php namespace ProcessWire;

/** Session endpoint, provider transport and bounded diagnostics. */
trait FormBuilderProcessorGPTLiveSession {
    /**
     * Build a site-root-aware URL without trusting the form name as path syntax.
     * @param string $formName
     * @return string
     * @throws WireException
     */
    private function sessionEndpointUrl(string $formName): string {
		$root = rtrim((string) $this->wire()->config->urls->root, '/');
		return $root . '/formbuilder-gpt-live/' . rawurlencode($formName) . '/';
	}

	/**
	 * Handle startup, page-schema refreshes and browser diagnostics on one JSON route.
	 * Origin, session-bound form tokens and start allowances are checked before paid starts.
	 * Page refresh requests return tools without creating a second paid voice session.
	 * Return response text to ProcessPageView; echoing and returning true loses the body.
	 */
	private function handleSessionRequest(string $formName) {
		// Actions are non-singleton modules, and ProcessWire runs path hooks before
		// and after ready(). Share the result across instances for this HTTP request
		// so one offer reserves one start and creates at most one paid session.
		static $responses = [];
		if(!array_key_exists($formName, $responses)) {
			$responses[$formName] = $this->buildSessionResponse($formName);
		}
		return $responses[$formName];
	}

	/** Execute the endpoint once; handleSessionRequest owns request-local reuse. */
	private function buildSessionResponse(string $formName) {
		$forms = $this->wire('forms');
		$fbForm = $forms ? $forms->form($formName, false) : false;
		if($fbForm) $this->fbForm($fbForm);
		if(!$fbForm || !in_array($this->className(), (array) $fbForm->pluginActions, true)) {
			return $this->sessionFailure(404, 'GPT-Live is not enabled for this form');
		}
		if($this->wire()->input->requestMethod() !== 'POST') return $this->sessionFailure(405, 'POST required');
		$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
		if(stripos($contentType, 'application/json') !== 0) return $this->sessionFailure(415, 'JSON required');
		$origin = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
		$expectedOrigin = rtrim((string) $this->wire()->config->urls->httpRoot, '/');
		if($origin === '' || $origin !== $expectedOrigin) return $this->sessionFailure(403, 'Origin rejected');
		$body = file_get_contents('php://input', false, null, 0, 100001);
		if($body === false || strlen($body) > 100000) return $this->sessionFailure(400, 'Invalid session request');
		$payload = json_decode($body, true);
		if(!is_array($payload)) return $this->sessionFailure(400, 'JSON object required');
		if(!$this->acceptFormToken($fbForm->name, $payload['token'] ?? null)) return $this->sessionFailure(403, 'Missing, expired or invalid form token');
		if(is_array($payload['clientDiagnostic'] ?? null)) {
			$accepted = $this->acceptDiagnostic($fbForm->name);
			if($accepted) $this->logClientFailure($fbForm->name, $payload['clientDiagnostic']);
			return $this->jsonResponse(200, ['logged' => $accepted]);
		}
		// Preserve trailing SDP CRLF; trimming the offer breaks negotiation.
		$sdp = is_string($payload['sdp'] ?? null) ? $payload['sdp'] : '';
		$pageUpdate = !empty($payload['pageUpdate']);
		if(!$pageUpdate && (trim($sdp) === '' || strlen($sdp) > 90000)) return $this->sessionFailure(400, 'A valid WebRTC offer is required');
		$pageNum = filter_var($payload['pageNum'] ?? 1, FILTER_VALIDATE_INT);
		$pageCount = $this->formPageCount($fbForm);
		if($pageNum === false || $pageNum < 1 || $pageNum > $pageCount) return $this->sessionFailure(400, 'Invalid FormBuilder page number');
		$settings = $this->currentSettings();
		$this->setArray($settings);
		$agent = $this->getSelectedAgent();
		if(!$agent) return $this->sessionFailure(503, 'No compatible AgentTools model selected');

		$allToolFields = $this->getToolFields($fbForm);
		$contract = $this->formTokenContract($fbForm->name, $payload['token']);
		if($contract !== null && (int) $contract['pageNum'] !== $pageNum) return $this->sessionFailure(400, 'Form token belongs to another page');
		$toolFields = $contract !== null ? $contract['fields'] : $this->getToolFields($fbForm, $pageNum);
		$fieldLabels = $this->getToolFieldLabels($fbForm, $toolFields);
		if(!$toolFields && ($pageCount < 2 || !$allToolFields)) return $this->sessionFailure(503, 'This form has no supported fields for GPT-Live');
		$knownValues = $this->getKnownFormValues($fbForm, $pageNum, $allToolFields, $toolFields, $payload['currentValues'] ?? []);
		try {
			$guidance = $this->pageAssistantGuidance($fbForm, $pageNum, $pageCount, $toolFields, $fieldLabels, $knownValues);
		} catch(\Throwable $error) {
			return $this->sessionFailure(503, 'Assistant guidance hook failed: ' . $error->getMessage());
		}
		$request = $this->buildSessionPayload($fbForm, $toolFields, $fieldLabels, $knownValues, $pageNum, $pageCount, $settings, $sdp, trim((string) $agent->model), $guidance);
		if($pageUpdate) {
			$voiceGuidance = 'Developer guidance now applies to form ' . json_encode($fbForm->name) . ', page ' . $pageNum . ' of ' . $pageCount . '. This replaces all earlier developer guidance; do not carry earlier page-specific guidance forward.';
			$voiceGuidance .= $guidance === '' ? ' No additional developer guidance applies on this page.' : $this->assistantGuidanceInstructions($guidance);
			$voiceGuidance .= $this->buildClarificationInstructions($settings);
			$voiceGuidance .= $this->buildChoiceNoticeInstructions($settings, $toolFields);
			return $this->jsonResponse(200, ['responses' => $request['session']['delegation']['responses'], 'assistantGuidance' => $voiceGuidance]);
		}

		// Page-schema refresh above never consumes a new connection allowance.
		try {
			if(!$this->reserveVoiceStart($fbForm->name)) return $this->sessionFailure(429, 'Voice session start limit reached');
		} catch(\Throwable $error) {
			return $this->sessionFailure(503, 'Voice session limiter unavailable: ' . $error->getMessage());
		}

		return $this->createLiveSession($fbForm->name, $request, trim((string) $agent->apiKey));
	}

	/** Contact OpenAI and return only the negotiated SDP; credentials stay on the server. */
	private function createLiveSession(string $formName, array $request, string $apiKey): string {
		$http = $this->wire(new WireHttp());
		$http->setHeader('Authorization', 'Bearer ' . $apiKey);
		$http->setHeader('Content-Type', 'application/json');
		$http->setTimeout(30);
		// A session POST must not be retried through another transport after failure.
		$transport = function_exists('curl_init') ? 'curl' : (ini_get('allow_url_fopen') ? 'fopen' : 'socket');
		try {
			$response = $http->post('https://api.openai.com/v1/live/sessions', json_encode($request, JSON_UNESCAPED_SLASHES), [
				'use' => $transport,
				'followRedirects' => false,
			]);
		} catch(\Throwable $error) {
			return $this->parseLiveSessionResponse($formName, false, 0, $error->getMessage());
		}
		return $this->parseLiveSessionResponse($formName, $response, (int) $http->getHttpCode(), (string) $http->getError());
	}

	/** Validate service JSON/status/SDP; expose the existing fallback and log private details. */
	private function parseLiveSessionResponse(string $formName, $response, int $status, string $transportError): string {
		if($response === false) {
			$this->logSessionFailure($formName, 502, 'Could not reach OpenAI: ' . ($transportError !== '' ? $transportError : 'HTTP request failed'));
			return $this->jsonResponse(502, ['error' => $this->getVoiceMessages()['fallback']]);
		}
		$result = json_decode($response, true);
		if(!is_array($result)) {
			$this->logSessionFailure($formName, $status ?: 502, 'OpenAI returned a non-JSON response');
			return $this->jsonResponse(502, ['error' => $this->getVoiceMessages()['fallback']]);
		}
		if($status >= 400) {
			$error = $result['error'] ?? $result;
			$errorDetail = $this->describeServiceError($error);
			$this->logSessionFailure($formName, $status, $errorDetail);
			$modelUnavailable = is_array($error) && (
				($error['code'] ?? '') === 'model_not_found'
				|| ($error['param'] ?? '') === 'model'
				|| ($error['param'] ?? '') === 'session.delegation.model'
			);
			return $this->jsonResponse($status, ['error' => $this->getVoiceMessages()['fallback'], 'manualOnly' => $modelUnavailable]);
		}
		if(!is_array($result['transport'] ?? null) || !is_string($result['transport']['sdp'] ?? null) || trim($result['transport']['sdp']) === '') {
			$topLevelKeys = implode(', ', array_map('strval', array_keys($result)));
			$transportKeys = is_array($result['transport'] ?? null)
				? implode(', ', array_map('strval', array_keys($result['transport'])))
				: '(missing)';
			$sessionKeys = is_array($result['session'] ?? null)
				? implode(', ', array_map('strval', array_keys($result['session'])))
				: '(missing)';
			$this->logSessionFailure(
				$formName,
				$status,
				sprintf('OpenAI returned a successful response without a usable transport.sdp; top-level keys: %s; transport keys: %s; session keys: %s', $topLevelKeys ?: '(none)', $transportKeys, $sessionKeys)
			);
			return $this->jsonResponse(502, ['error' => $this->getVoiceMessages()['fallback']]);
		}
		// The browser needs only the negotiated answer; do not echo the complete
		// Live session object (which may include server-side session configuration).
		return $this->jsonResponse($status ?: 502, ['transport' => ['sdp' => $result['transport']['sdp']]]);
	}

	/** Format known error fields for admin logs without returning service details to visitors. */
	private function describeServiceError($error): string {
		if(is_array($error)) {
			$details = [];
			foreach(['type', 'code', 'param', 'message'] as $key) {
				if(isset($error[$key]) && is_scalar($error[$key])) $details[] = $key . ': ' . (string) $error[$key];
			}
			$encodedError = json_encode($error, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
			return $details ? implode('; ', $details) : ($encodedError !== false ? $encodedError : 'OpenAI returned an error response');
		} else {
			return is_scalar($error) ? (string) $error : 'OpenAI returned an error response';
		}
	}

	/** Log technical session-start failures for administrators without exposing them to visitors. */
	private function logSessionFailure(string $formName, int $status, string $detail): void {
		$detail = preg_replace('/[\x00-\x1F\x7F]+/', ' ', strip_tags($detail));
		$detail = trim(substr((string) $detail, 0, 1500));
		$this->wire('log')->save('formbuilder-gpt-live', sprintf('GPT-Live session start failed for form %s; HTTP %d; %s', $formName, $status, $detail));
	}

	/** Log a sanitized browser-side startup failure for administrators. */
	private function logClientFailure(string $formName, array $diagnostic): void {
		$stage = preg_replace('/[^a-zA-Z0-9_-]/', '', substr((is_scalar($diagnostic['stage'] ?? null) ? (string) $diagnostic['stage'] : 'unknown'), 0, 40));
		$errorName = preg_replace('/[^a-zA-Z0-9_-]/', '', substr((is_scalar($diagnostic['name'] ?? null) ? (string) $diagnostic['name'] : 'Error'), 0, 60));
		$message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', strip_tags((is_scalar($diagnostic['message'] ?? null) ? (string) $diagnostic['message'] : '')));
		$message = trim(substr((string) $message, 0, 600));
		$this->wire('log')->save('formbuilder-gpt-live', sprintf('GPT-Live browser connection failed for form %s at %s; %s: %s', $formName, $stage ?: 'unknown', $errorName ?: 'Error', $message ?: 'No browser error message provided'));
	}

    /**
     * Set JSON/no-store headers and return text for the ProcessWire path hook.
     * @param int $status
     * @param array $data
     * @return string
     */
	private function jsonResponse(int $status, array $data): string {
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		return $json !== false ? $json : '{}';
	}
}
