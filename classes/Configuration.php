<?php namespace ProcessWire;

/** Per-form Action settings, AgentTools selection and voice preferences. */
trait FormBuilderProcessorGPTLiveConfiguration {
	/**
	 * Build per-form model, submission, asset and endpoint settings in the Actions UI.
	 */
	public function getConfigInputfields(InputfieldWrapper $inputfields) {
		$form = $this->fbForm();
		if(!$form) return;

		$settings = $this->currentSettings();
		$agents = $this->compatibleAgents();

		/** @var InputfieldSelect $agentSelect */
		$agentSelect = $this->wire()->modules->get('InputfieldSelect');
		$agentSelect->attr('name', 'agentId');
		$agentSelect->label = __('Preferred AgentTools model', __FILE__);
		$agentSelect->description = __('Choose the OpenAI AgentTools agent used for GPT-Live delegation. Its API key stays on the server.', __FILE__);
		$agentSelect->required = true;
		$agentSelect->addOption('', __('Select an AgentTools model', __FILE__));

		$primary = null;
		foreach($agents as $agent) {
			/** @var AgentToolsAgent $agent */
			$label = trim((string) $agent->label);
			$model = trim((string) $agent->model);
			$optionLabel = $label !== '' && $label !== $model
				? sprintf('%s (%s)', $label, $model)
				: $model;
			$agentSelect->addOption((string) $agent->id, $optionLabel);
			if(!$primary) $primary = $agent;
		}

		$selectedAgentId = (string) ($settings['agentId'] ?? '');
		if($selectedAgentId === '' && $primary) $selectedAgentId = (string) $primary->id;
		$agentSelect->val($selectedAgentId);
		if(!$agents) {
			$agentSelect->notes = __('No compatible OpenAI AgentTools models are configured. Configure one in AgentTools before using this action.', __FILE__);
		}
		$inputfields->add($agentSelect);
		$this->addVoicePreferences($inputfields, $settings);
		$speakFirst = $this->wire()->modules->get('InputfieldCheckbox');
		$speakFirst->name = 'assistantSpeaksFirst';
		$speakFirst->label = __('Assistant speaks first', __FILE__);
		$speakFirst->description = __('Enabled by default. Welcome the visitor when a new voice conversation is ready. Customise the Welcome message under Voice messages. Resume and page changes do not repeat the welcome.', __FILE__);
		if($this->assistantSpeaksFirst($settings)) $speakFirst->attr('checked', 'checked');
		$inputfields->add($speakFirst);

		$clarifications = $this->wire()->modules->get('InputfieldInteger');
		$clarifications->name = 'clarificationLimit';
		$clarifications->label = __('Maximum clarification attempts per field', __FILE__);
		$clarifications->description = __('When an answer is misheard or misunderstood, allow this many clarification or read-back questions before asking the visitor to type the value. Applies to all fields.', __FILE__);
		$clarifications->notes = __('Default: 1. Range: 1–5. The initial question does not count. Questions that gather missing details or distinguish between valid choices do not count. After unsuccessful clarification, the assistant asks for manual entry and waits for the visitor to finish.', __FILE__);
		$clarifications->min = 1;
		$clarifications->max = 5;
		$clarifications->inputType = 'number';
		$clarifications->attr('step', 1);
		$clarifications->required = true;
		$clarifications->value = $this->clarificationLimit($settings);
		$inputfields->add($clarifications);

		$choiceNotice = $this->wire()->modules->get('InputfieldInteger');
		$choiceNotice->name = 'choiceNoticeThreshold';
		$choiceNotice->label = __('Choice count before interruption reminder', __FILE__);
		$choiceNotice->description = __('Before offering more than this many choices, tell visitors they can interrupt with their answer or select their choice on screen at any time. Applies to single-choice and multiple-choice questions, including widget suggestions.', __FILE__);
		$choiceNotice->notes = __('Default: 4. Range: 1–100. The reminder comes before the choices. A list equal to the threshold does not need the reminder.', __FILE__);
		$choiceNotice->inputType = 'number';
		$choiceNotice->min = 1;
		$choiceNotice->max = 100;
		$choiceNotice->attr('step', 1);
		$choiceNotice->required = true;
		$choiceNotice->value = $this->choiceNoticeThreshold($settings);
		$inputfields->add($choiceNotice);

		/** @var InputfieldCheckbox $allowSubmission */
		$allowSubmission = $this->wire()->modules->get('InputfieldCheckbox');
		$allowSubmission->attr('name', 'allowVisitorRequestedSubmission');
		$allowSubmission->label = __('Allow visitors to ask GPT-Live to submit this form', __FILE__);
		$allowSubmission->description = __('Unchecked by default. When checked, GPT-Live can submit through FormBuilder only after the visitor explicitly asks. FormBuilder still performs its normal validation and processing.', __FILE__);
		if(!empty($settings['allowVisitorRequestedSubmission'])) $allowSubmission->attr('checked', 'checked');
		$inputfields->add($allowSubmission);

		/** @var InputfieldURL $scriptPath */
		$scriptPath = $this->wire()->modules->get('InputfieldURL');
		$scriptPath->attr('name', 'jsURL');
		$scriptPath->label = __('URL to JavaScript file that provides voice controls', __FILE__);
		$scriptPath->description = __('Specify a URL/path relative to the root of the ProcessWire installation.', __FILE__);
		$scriptPath->attr('value', trim((string) ($settings['jsURL'] ?? '')) ?: $this->defaultScriptPath());
		$defaultScriptPath = $this->defaultScriptPath();
		$defaultScriptUrl = $this->wire()->config->urls->root . ltrim($defaultScriptPath, '/');
		$scriptPath->notes = __('Default value:', __FILE__) . " [$defaultScriptPath]($defaultScriptUrl)";
		$inputfields->add($scriptPath);

		$stylePath = $this->wire()->modules->get('InputfieldURL');
		$stylePath->attr('name', 'cssURL');
		$stylePath->label = __('URL to CSS file that styles voice controls', __FILE__);
		$stylePath->description = __('Specify a URL/path relative to the root of the ProcessWire installation.', __FILE__);
		$stylePath->attr('value', trim((string) ($settings['cssURL'] ?? '')) ?: $this->defaultStylePath());
		$defaultStylePath = $this->defaultStylePath();
		$defaultStyleUrl = $this->wire()->config->urls->root . ltrim($defaultStylePath, '/');
		$stylePath->notes = __('Default value:', __FILE__) . " [$defaultStylePath]($defaultStyleUrl)";
		$inputfields->add($stylePath);

		$endpoint = $this->wire()->modules->get('InputfieldMarkup');
		$endpoint->label = __('GPT-Live endpoint', __FILE__);
		$endpoint->value = '<code>' . $this->wire()->sanitizer->entities($this->sessionEndpointUrl($form->name)) . '</code>';
		$endpoint->notes = __('This shared ProcessWire endpoint loads this form’s saved Action settings each time a voice session starts.', __FILE__);
		$inputfields->add($endpoint);
		$this->addProtectionSettings($inputfields);

		$this->addMessageSettings($inputfields, $settings);
	}

	/** Resolve the selected AgentTools agent by its stable ID for server-side use. */
	public function getSelectedAgent(): ?AgentToolsAgent {
		$settings = $this->currentSettings();
		$agentId = trim((string) ($settings['agentId'] ?? ''));
		if($agentId === '') return null;
		$at = $this->wire('at');
		if(!$at || !method_exists($at, 'getAgents')) return null;
		try {
			$agent = $at->getAgents()->getById($agentId);
		} catch(\Throwable $error) {
			return null;
		}
		return $this->isCompatibleAgent($agent) ? $agent : null;
	}

	/** @return AgentToolsAgent[] */
	private function compatibleAgents(): array {
		$at = $this->wire('at');
		if(!$at || !method_exists($at, 'getAgents')) return [];

		$agents = [];
		foreach($at->getAgents() as $agent) {
			if($this->isCompatibleAgent($agent)) $agents[] = $agent;
		}
		return $agents;
	}

	/**
	 * Accept configured OpenAI agents; this does not verify model availability.
	 * @param mixed $agent
	 * @return bool
     */
    private function isCompatibleAgent($agent): bool {
		if(!$agent instanceof AgentToolsAgent) return false;
		if($agent->provider !== 'openai' || trim((string) $agent->model) === '' || trim((string) $agent->apiKey) === '') return false;
		$endpointHost = strtolower((string) parse_url((string) $agent->endpointUrl, PHP_URL_HOST));
		return $endpointHost === '' || $endpointHost === 'api.openai.com';
	}

    /**
     * Read this Action’s saved settings from its currently bound FormBuilder form.
     * @return array<string,mixed>
     */
    private function currentSettings(): array {
		$form = $this->fbForm();
		$settings = $form ? $form->get($this->className()) : [];
		return is_array($settings) ? $settings : [];
	}

	private function assistantSpeaksFirst(array $settings): bool {
		return !array_key_exists('assistantSpeaksFirst', $settings) || !empty($settings['assistantSpeaksFirst']);
	}

	/** Invalid or older saved settings use the single-attempt default. */
	private function clarificationLimit(array $settings): int {
		$value = $settings['clarificationLimit'] ?? 1;
		if(!is_int($value) && !(is_string($value) && ctype_digit($value))) return 1;
		return $value >= 1 && $value <= 5 ? (int) $value : 1;
	}

	private function choiceNoticeThreshold(array $settings): int {
		$value = $settings['choiceNoticeThreshold'] ?? 4;
		if(!is_int($value) && !(is_string($value) && ctype_digit($value))) return 4;
		return $value >= 1 && $value <= 100 ? (int) $value : 4;
	}

	/** Supported built-in API names; regional descriptions are documented by OpenAI. */
	private function voiceOptions(): array {
		$names = explode(' ', 'alloy ash ballad beacon bossa brise cedar cinder coral delta echo flitz gleam harema juni marin meridian nira noeul nuri quartz ripple sage shimmer shitan sillage stone tempo verse vesper willow');
		$options = array_combine($names, array_map('ucfirst', $names));
		$options['marin'] = __('Marin (default)', __FILE__);
		$options['quartz'] = __('Quartz — Australian English, feminine', __FILE__);
		$options['ripple'] = __('Ripple — Australian English, masculine', __FILE__);
		$options['vesper'] = __('Vesper — British English, masculine', __FILE__);
		$options['willow'] = __('Willow — Irish English, feminine', __FILE__);
		$options['stone'] = __('Stone — Irish English, masculine', __FILE__);
		$options['gleam'] = __('Gleam — North American English, feminine', __FILE__);
		$options['meridian'] = __('Meridian — North American English, masculine', __FILE__);
		$options['bossa'] = __('Bossa — Brazilian Portuguese, feminine', __FILE__);
		$options['tempo'] = __('Tempo — Brazilian Portuguese, masculine', __FILE__);
		$options['beacon'] = __('Beacon — Filipino English, masculine', __FILE__);
		$options['delta'] = __('Delta — Southern U.S. English, feminine', __FILE__);
		$options['cinder'] = __('Cinder — Southern U.S. English, masculine', __FILE__);
		return $options;
	}

	/** Accent preferences are language-specific speaking guidance, not API voice names. */
	private function accentOptions(): array {
		return [
			'en-AU' => ['English', 'Australian English', __('English — Australian', __FILE__)],
			'en-GB' => ['English', 'British English', __('English — British', __FILE__)],
			'en-US' => ['English', 'American English', __('English — American', __FILE__)],
			'en-NZ' => ['English', 'New Zealand English', __('English — New Zealand', __FILE__)],
			'en-IE' => ['English', 'Irish English', __('English — Irish', __FILE__)],
			'fr-FR' => ['French', 'French as spoken in France', __('French — France', __FILE__)],
			'fr-CA' => ['French', 'Canadian French', __('French — Canada', __FILE__)],
			'de-DE' => ['German', 'German as spoken in Germany', __('German — Germany', __FILE__)],
			'es-ES' => ['Spanish', 'Spanish as spoken in Spain', __('Spanish — Spain', __FILE__)],
			'es-MX' => ['Spanish', 'Mexican Spanish', __('Spanish — Mexico', __FILE__)],
			'pt-BR' => ['Portuguese', 'Brazilian Portuguese', __('Portuguese — Brazil', __FILE__)],
			'pt-PT' => ['Portuguese', 'European Portuguese', __('Portuguese — Portugal', __FILE__)],
		];
	}

	private function selectedVoice(array $settings): string {
		$voice = $settings['voice'] ?? '';
		return is_string($voice) && isset($this->voiceOptions()[$voice]) ? $voice : 'marin';
	}

	private function addVoicePreferences(InputfieldWrapper $inputfields, array $settings): void {
		$voice = $this->wire()->modules->get('InputfieldSelect');
		$voice->name = 'voice';
		$voice->label = __('Speaking voice', __FILE__);
		$voice->description = __('Choose the voice used for this form. Regional voices have their own speaking influence.', __FILE__);
		$voice->notes = __('The same voice is retained when the visitor changes language. Start a new conversation after changing this setting. Test pronunciation in each supported language.', __FILE__);
		$voice->addOptions($this->voiceOptions());
		$voice->val($this->selectedVoice($settings));
        $voice->columnWidth = 50;
		$inputfields->add($voice);

		$accent = $this->wire()->modules->get('InputfieldSelect');
		$accent->name = 'accent';
		$accent->label = __('Preferred regional accent', __FILE__);
        $accent->columnWidth = 50;
		$accent->description = __('Applies only while speaking the selected language. For example, Australian English guidance stops when the visitor switches to French.', __FILE__);
		$accent->notes = __('This guides pronunciation; it does not choose or restrict the conversation language, or guarantee an accent. Other languages use natural pronunciation for that language.', __FILE__);
		$accent->addOption('', __('Automatic — natural pronunciation for the spoken language', __FILE__));
		$accent->addOption('custom', __('Custom — any language and regional accent', __FILE__));
		foreach($this->accentOptions() as $key => $option) $accent->addOption($key, $option[2]);
		$selected = $settings['accent'] ?? '';
		$accent->val(is_string($selected) && ($selected === 'custom' || isset($this->accentOptions()[$selected])) ? $selected : '');
		$inputfields->add($accent);
		foreach(['accentLanguage' => __('Accent language', __FILE__), 'accentRegion' => __('Regional accent', __FILE__)] as $name => $label) {
			$field = $this->wire()->modules->get('InputfieldText');
			$field->name = $name;
			$field->label = $label;
			$field->showIf = 'accent=custom';
			$field->maxlength = 100;
			$field->description = $name === 'accentLanguage'
				? __('Name any spoken language, for example Japanese, Arabic or Hindi. This does not restrict the visitor’s language.', __FILE__)
				: __('Name the regional pronunciation to prefer in that language.', __FILE__);
			$field->val(is_string($settings[$name] ?? null) ? $settings[$name] : '');
			$inputfields->add($field);
		}
	}
}
