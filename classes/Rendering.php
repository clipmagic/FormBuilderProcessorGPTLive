<?php namespace ProcessWire;

/** Native rendered field contracts and browser metadata. */
trait FormBuilderProcessorGPTLiveRendering {
	/** Add the shared endpoint URL to the rendered FormBuilder form. */
	public function renderReady() {
		$form = $this->form();
		$fbForm = $this->fbForm();
		if(!$form || !$fbForm) return;
		if(!$this->getSelectedAgent()) {
			$this->logSessionFailure($fbForm->name, 503, 'Saved AgentTools model is missing or incompatible; manual form only');
			$form->attr('data-gpt-live-unavailable', '1');
			// Site-owned controls may be outside the form. Load the client to hide them.
			$this->wire()->config->scripts->add($this->configuredAssetUrl('jsURL', $this->defaultScriptPath()));
			return;
		}
		$pageNum = max(1, (int) $this->processor()->maker()->getPageNumToRender());
		$pageCount = $this->formPageCount($fbForm);
		$toolFields = $this->getToolFields($fbForm, $pageNum);
		$fieldNames = array_keys($toolFields);
		$fieldLabels = $this->getToolFieldLabels($fbForm, $toolFields);
		if(!$fieldNames && ($pageCount < 2 || !$this->getToolFields($fbForm))) return;
		$requiredFieldNames = [];
		foreach($fbForm->getChildrenFlat(['includeNestedForms' => false]) as $field) {
			if(!$field instanceof FormBuilderField || !$field->name || empty($toolFields[$field->name])) continue;
			if($field->required && trim((string) $field->get('requiredIf')) === '') $requiredFieldNames[] = $field->name;
		}
		$messages = $this->getVoiceMessages();
		// FormBuilder emits jsConfig in ProcessWire.config. The form-local copy also
		// survives pagination without replaying inline scripts or mixing form settings.
		$browserConfig = (array) $this->wire()->config->jsConfig('FormBuilderProcessorGPTLive');
		// FormBuilder's debug serializer leaves slashes unescaped. Encode the catalog
		// so editable text containing </script> cannot terminate its inline script.
		$browserConfig[$fbForm->name] = ['requestTimeoutSeconds' => $this->requestTimeoutSeconds(), 'messageData' => base64_encode(json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE))];
		$this->wire()->config->jsConfig('FormBuilderProcessorGPTLive', $browserConfig);
		$form->attr('data-gpt-live-form-name', $fbForm->name);
		$form->attr('data-gpt-live-messages', json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
		$form->attr('data-gpt-live-request-timeout-seconds', (string) $this->requestTimeoutSeconds());
		$form->attr('data-gpt-live-token', $this->issueFormToken($fbForm->name, ['pageNum' => $pageNum, 'fields' => $toolFields]));
		$form->attr('data-gpt-live-session-url', $this->sessionEndpointUrl($fbForm->name));
		$form->attr('data-gpt-live-page-num', (string) $pageNum);
		$form->attr('data-gpt-live-page-count', (string) $pageCount);
		$form->attr('data-gpt-live-date-reference', date('Y-m-d H:i'));
		$form->attr('data-gpt-live-timezone', date_default_timezone_get());
		$form->attr('data-gpt-live-next-page-label', $this->nextPageBreakLabel($fbForm, $pageNum));
		$form->attr('data-gpt-live-tool-name', self::TOOL_NAME_PREFIX . $this->wire()->sanitizer->fieldName($fbForm->name));
		$allowSubmission = !empty($this->currentSettings()['allowVisitorRequestedSubmission']) && $pageNum === $pageCount && (bool) $toolFields;
		$form->attr('data-gpt-live-submission-enabled', $allowSubmission ? '1' : '0');
		$form->attr('data-gpt-live-assistant-speaks-first', $this->assistantSpeaksFirst($this->currentSettings()) ? '1' : '0');
		$form->attr('data-gpt-live-submit-tool-name', self::SUBMIT_TOOL_NAME_PREFIX . $this->wire()->sanitizer->fieldName($fbForm->name));
		$form->attr('data-gpt-live-field-names', json_encode($fieldNames, JSON_UNESCAPED_SLASHES));
		$form->attr('data-gpt-live-field-labels', json_encode($fieldLabels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		$form->attr('data-gpt-live-required-field-names', json_encode($requiredFieldNames, JSON_UNESCAPED_SLASHES));
		$form->attr('data-gpt-live-field-rules', json_encode($this->getToolFieldRules($fbForm, $toolFields), JSON_UNESCAPED_SLASHES));
		$form->attr('data-gpt-live-validation-errors', json_encode($this->nativeValidationErrors($form), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

		$config = $this->wire()->config;
		$scriptUrl = $this->configuredAssetUrl('jsURL', $this->defaultScriptPath());
		$form->attr('data-gpt-live-script-url', $scriptUrl);
		$hasScript = false;
		foreach($config->scripts as $script) {
			if($script === $scriptUrl) { $hasScript = true; break; }
		}
		if(!$hasScript) $config->scripts->add($scriptUrl);

		$styleUrl = $this->configuredAssetUrl('cssURL', $this->defaultStylePath());
		$hasStyle = false;
		foreach($config->styles as $style) {
			if($style === $styleUrl) { $hasStyle = true; break; }
		}
		if(!$hasStyle) $config->styles->add($styleUrl);
	}

	/** Read native errors without clearing them or reproducing site validation rules. */
	private function nativeValidationErrors(InputfieldForm $form): array {
		$errors = [];
		foreach($form->getErrors(false) as $error) {
			if(!is_string($error)) continue;
			$error = trim(html_entity_decode(strip_tags($error), ENT_QUOTES, 'UTF-8'));
			if($error !== '') $errors[] = substr($error, 0, 500);
			if(count($errors) >= 12) break;
		}
		return array_values(array_unique($errors));
	}
}
