<?php namespace ProcessWire;

/** Default editable UI/tool messages. Protocol names and model policy remain code-owned. */
trait FormBuilderProcessorGPTLiveMessages {
	/** Literal defaults shared by controls and errors; __FILE__ binds translation to this trait file. */
	private function defaultMessages(): array {
		$messages = [
			"idle" => __("Start voice assistant", __FILE__),
			"starting" => __("Starting voice assistant…", __FILE__),
			"active" => __("Pause voice assistant", __FILE__),
			"pausing" => __("Pausing voice assistant…", __FILE__),
			"paused" => __("Resume voice assistant", __FILE__),
			"resuming" => __("Resuming voice assistant…", __FILE__),
			"navigating" => __("Changing form page…", __FILE__),
			"copy" => __("Copy conversation", __FILE__),
			"visitorSpeaker" => __("You", __FILE__),
			"assistantSpeaker" => __("Assistant", __FILE__),
			"fallback" => __("Voice assistance is unavailable right now. Please complete and submit this form normally.", __FILE__),
			"copied" => __("Conversation copied.", __FILE__),
			"copyFailed" => __("Could not copy the conversation. You can select and copy the transcript manually.", __FILE__),
			"sending" => __("Sending the form through FormBuilder…", __FILE__),
			"submissionFailed" => __("FormBuilder could not start submission. Review the form and use its normal submit button.", __FILE__),
			"listening" => __("Listening. Speak naturally, then pause for the assistant to reply.", __FILE__),
			"replying" => __("The assistant is replying…", __FILE__),
			"preparing" => __("Preparing the form…", __FILE__),
			"connectionEnded" => __("The voice connection ended. Your form values and transcript remain available; complete the form normally or start a new voice conversation.", __FILE__),
			"unsupportedBrowser" => __("This browser does not support the voice assistant. You can still complete the form normally.", __FILE__),
			"requestingMicrophone" => __("Requesting microphone access…", __FILE__),
			"audioBlocked" => __("The assistant replied, but audio playback was blocked. Follow the transcript and review the form.", __FILE__),
			"connecting" => __("Connecting to the voice assistant…", __FILE__),
			"pausedStatus" => __("Voice assistant paused. Your conversation and form values are kept. Resume when you are ready.", __FILE__),
			"resumeFailed" => __("Could not resume microphone access. Your conversation is still paused; try again or complete the form normally.", __FILE__),
			"toolInvalidObject" => __("The form values were not a valid object. Ask the visitor for the details again.", __FILE__),
			"toolUnknownFields" => __("The response included fields that are not available in this form. Do not prepare those values.", __FILE__),
			"toolMissingRequired" => __("Required form details are missing. Ask the visitor only for the listed details, then prepare the form again. Do not say the form is ready until all required details are supplied.", __FILE__),
			"toolUnsupportedValues" => __("Some values could not be placed in the visible form. Confirm the related FormBuilder condition is active, then try again.", __FILE__),
			"toolInvalidValues" => __("Some values do not satisfy the form requirements. Ask the visitor only for the missing or invalid details.", __FILE__),
			"toolSubmissionDisabled" => __("This Action does not allow GPT-Live to submit. The visitor can use the form’s normal submit button.", __FILE__),
			"toolSubmissionPending" => __("A FormBuilder submission is already in progress. Wait for its result and do not submit again.", __FILE__),
			"toolBrowserSubmissionUnavailable" => __("This browser cannot submit the form through its normal submit control. Ask the visitor to submit it using the form.", __FILE__),
			"toolSubmissionInvalid" => __("FormBuilder browser validation found missing or invalid values. Ask the visitor to review the highlighted fields, then try again only after they directly request submission.", __FILE__),
			"toolSubmissionRequested" => __("The form was handed to FormBuilder for normal validation and processing. Do not claim it succeeded; wait for the FormBuilder result.", __FILE__),
			"toolPageChanging" => __("FormBuilder is changing pages. Wait for the current page to finish loading, then ask only about its visible fields.", __FILE__),
			"toolSubmissionNotConfirmed" => __("The visitor has not clearly asked to submit. Continue review and ask for explicit confirmation before using the submission tool.", __FILE__),
			"diagnosticPeerFailed" => __("The peer connection entered the failed state.", __FILE__),
			"diagnosticChannelClosed" => __("The data channel closed before the session was ready.", __FILE__),
			"diagnosticStartTimeout" => __("No session.started event arrived within 20 seconds.", __FILE__),
			"pagePreparedDestination" => __("The fields on this page are prepared. Use {control} to go to {destination}.", __FILE__),
			"nextInstructionDestination" => __("Ask the visitor to use the form control labelled {control} to go to the page labelled {destination}. Do not ask for final review or submission yet.", __FILE__),
			"pagePrepared" => __("The fields on this page are prepared. Use {control} to continue.", __FILE__),
			"navigationControl" => __("the form’s page navigation control", __FILE__),
			"preparedWithSubmission" => __("The fields on this page are prepared for your review. Check them; the assistant will ask before submitting.", __FILE__),
			"preparedManual" => __("The fields on this page are prepared for your review. You can ask for a correction or end the voice assistant.", __FILE__),
			"navigationBlocked" => __("Please correct the fields highlighted by FormBuilder. Your conversation continues.", __FILE__),
			"pageChanged" => __("Page {page} of {pages}. Your conversation continues.", __FILE__),
			"nextInstruction" => __("Ask the visitor to use the form control labelled {control} to continue. Do not ask for final review or submission yet.", __FILE__),
			"pagePreparedTool" => __("The fields on this page are prepared. {instruction} Keep the conversation open for corrections.", __FILE__),
			"toolPreparedWithSubmission" => __("The fields on this page are prepared. Ask the visitor to review them, then ask a clear confirmation question before submitting. Wait for an explicit yes to that question; an earlier request to submit is not final confirmation. If the visitor wants to check, go back or wait, acknowledge briefly and wait without offering submission. Corrections and navigation require fresh review and confirmation. Keep the conversation open for corrections.", __FILE__),
			"toolPreparedManual" => __("The fields on this page are prepared. Ask the visitor to review them and submit the form personally if correct. Keep the conversation open for corrections.", __FILE__),
		];
		// __() can entity-encode translated text. The catalog is plain text;
		// template attributes escape it and JS displays it with textContent.
		return array_map(static fn(string $text): string => html_entity_decode($text, ENT_QUOTES, 'UTF-8'), $messages);
	}

	/** Literal admin labels are discoverable by the same ProcessWire translation file. */
	private function defaultMessageLabels(): array {
		return [
			"idle" => __("Idle", __FILE__),
			"starting" => __("Starting", __FILE__),
			"active" => __("Active", __FILE__),
			"pausing" => __("Pausing", __FILE__),
			"paused" => __("Paused", __FILE__),
			"resuming" => __("Resuming", __FILE__),
			"navigating" => __("Navigating", __FILE__),
			"copy" => __("Copy", __FILE__),
			"visitorSpeaker" => __("Visitor Speaker", __FILE__),
			"assistantSpeaker" => __("Assistant Speaker", __FILE__),
			"fallback" => __("Friendly fallback", __FILE__),
			"copied" => __("Copied", __FILE__),
			"copyFailed" => __("Copy Failed", __FILE__),
			"sending" => __("Sending", __FILE__),
			"submissionFailed" => __("Submission Failed", __FILE__),
			"listening" => __("Listening", __FILE__),
			"replying" => __("Replying", __FILE__),
			"preparing" => __("Preparing", __FILE__),
			"connectionEnded" => __("Connection Ended", __FILE__),
			"unsupportedBrowser" => __("Unsupported Browser", __FILE__),
			"requestingMicrophone" => __("Requesting Microphone", __FILE__),
			"audioBlocked" => __("Audio Blocked", __FILE__),
			"connecting" => __("Connecting", __FILE__),
			"pausedStatus" => __("Paused Status", __FILE__),
			"resumeFailed" => __("Resume Failed", __FILE__),
			"toolInvalidObject" => __("Tool Invalid Object", __FILE__),
			"toolUnknownFields" => __("Tool Unknown Fields", __FILE__),
			"toolMissingRequired" => __("Tool Missing Required", __FILE__),
			"toolUnsupportedValues" => __("Tool Unsupported Values", __FILE__),
			"toolInvalidValues" => __("Tool Invalid Values", __FILE__),
			"toolSubmissionDisabled" => __("Tool Submission Disabled", __FILE__),
			"toolSubmissionPending" => __("Tool Submission Pending", __FILE__),
			"toolBrowserSubmissionUnavailable" => __("Tool Browser Submission Unavailable", __FILE__),
			"toolSubmissionInvalid" => __("Tool Submission Invalid", __FILE__),
			"toolSubmissionRequested" => __("Tool Submission Requested", __FILE__),
			"toolPageChanging" => __("Tool Page Changing", __FILE__),
			"toolSubmissionNotConfirmed" => __("Tool Submission Not Confirmed", __FILE__),
			"diagnosticPeerFailed" => __("Diagnostic Peer Failed", __FILE__),
			"diagnosticChannelClosed" => __("Diagnostic Channel Closed", __FILE__),
			"diagnosticStartTimeout" => __("Diagnostic Start Timeout", __FILE__),
			"pagePreparedDestination" => __("Page Prepared Destination", __FILE__),
			"nextInstructionDestination" => __("Next Instruction Destination", __FILE__),
			"pagePrepared" => __("Page Prepared", __FILE__),
			"navigationControl" => __("Navigation Control", __FILE__),
			"preparedWithSubmission" => __("Prepared With Submission", __FILE__),
			"preparedManual" => __("Prepared Manual", __FILE__),
			"navigationBlocked" => __("Navigation Blocked", __FILE__),
			"pageChanged" => __("Page Changed", __FILE__),
			"nextInstruction" => __("Next Instruction", __FILE__),
			"pagePreparedTool" => __("Page Prepared Tool", __FILE__),
			"toolPreparedWithSubmission" => __("Tool Prepared With Submission", __FILE__),
			"toolPreparedManual" => __("Tool Prepared Manual", __FILE__),
		];
	}

	/** Retired duplicate settings remain readable until the Action is saved again. */
	private function savedMessage(array $settings, string $key, string $suffix): string {
		$aliases = [
			"starting" => ["startingSession"],
			"navigating" => ["changingPage"],
			"copied" => ["copiedLabel"],
			"listening" => ["listeningPause", "resumedStatus"],
			"connectionEnded" => ["connectionLost", "connectionClosed", "pauseFailed", "pageContinuationFailed"],
			"fallback" => ["endpointUnavailable", "notConfigured", "connectionTimeout"],
			"toolMissingRequired" => ["toolMissingConditional"],
			"toolBrowserSubmissionUnavailable" => ["toolSubmitControlMissing"],
		];
		// Prefer the shared setting; older per-instance text is only a fallback.
		foreach(array_merge([$key], $aliases[$key] ?? []) as $name) {
			$value = trim((string) ($settings['message_' . $name . $suffix] ?? ''));
			if($value !== '') return $value;
		}
		return '';
	}

	/** Only visitor-visible copy belongs in per-form Action settings. */
	private function isVisitorMessage(string $key): bool {
		return !preg_match('/^(tool|diagnostic|nextInstruction)|^pagePreparedTool$/', $key);
	}

	/** Resolve saved text in the visitor language, falling back to the default-language value. */
	public function getVoiceMessages(): array {
		$settings = $this->currentSettings();
		$language = $this->wire()->user->language;
		$suffix = $language && !$language->isDefault() ? '__lang' . $language->id : '';
		$messages = [];
		foreach($this->defaultMessages() as $key => $default) {
			if(!$this->isVisitorMessage($key)) {
				$messages[$key] = $default;
				continue;
			}
			$value = $suffix !== '' ? $this->savedMessage($settings, $key, $suffix) : '';
			if($value === '') $value = $this->savedMessage($settings, $key, '');
			$messages[$key] = $value !== '' ? $value : $default;
		}
		return $messages;
	}

	/** Separate scalar language fields match FormBuilder's Action settings saver. */
	private function addMessageSettings(InputfieldWrapper $inputfields, array $settings): void {
		$languages = $this->wire()->languages;
		$variants = ['' => __('Default language', __FILE__)];
		if($languages) foreach($languages as $language) {
			$variants[$language->isDefault() ? '' : '__lang' . $language->id] = (string) $language->title;
		}
		$tabs = $this->wire()->modules->get('InputfieldFieldset');
		$tabs->name = 'voice_messages';
		$tabs->label = __('Voice messages', __FILE__);
		$tabs->wrapClass .= ' gpt-live-message-tabs';
		$tabs->collapsed = Inputfield::collapsedYes;
		$this->wire()->modules->get('JqueryWireTabs');
		$this->wire()->config->scripts->add($this->addAssetCacheBuster($this->defaultAssetPath('FormBuilderProcessorGPTLiveAdmin.js')));
		foreach($variants as $suffix => $label) {
			$group = $this->wire()->modules->get('InputfieldFieldset');
			$group->name = 'messages' . $suffix;
			$group->label = $label;
			$group->wrapClass .= ' gpt-live-language-page';
			$group->description = __('Visitor-visible labels and messages only. Plain text. Keep placeholders such as {control}, {destination}, {page} and {pages}. Leave blank to use inherited text. A saved language override takes priority, then saved default-language text, then the module translation file. Internal tool feedback and diagnostics use module language-file translations.', __FILE__);
			$group->collapsed = Inputfield::collapsedNever;
			foreach($this->defaultMessages() as $key => $default) {
				if(!$this->isVisitorMessage($key)) continue;
				$name = 'message_' . $key . $suffix;
				$field = $this->wire()->modules->get('InputfieldTextarea');
				$field->name = $name;
				$field->label = $this->defaultMessageLabels()[$key];
				$field->rows = 2;
				$saved = $this->savedMessage($settings, $key, $suffix);
				// Blank means use the module language-file default, rather than saving
				// an English copy that would mask future module translations.
				$field->value = $saved;
				$field->placeholder = $default;
				$group->add($field);
			}
			$tabs->add($group);
		}
		$inputfields->add($tabs);
	}
}
