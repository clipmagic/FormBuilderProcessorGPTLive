<?php namespace ProcessWire;

/** Supported native field schemas, labels, conditions and choice options. */
trait FormBuilderProcessorGPTLiveFields {
	/**
	 * Build the supported field allow-list and native string/choice-array schema.
	 * A page number of zero includes all pages for saved-value context only.
	 * Excludes honeypots, nested forms and unsupported/structural control types.
	 * @return array<string,array<string,mixed>>
	 */
	private function getToolFields(FormBuilderForm $form, int $pageNum = 0): array {
		$honeypots = $form->honeypot;
		if(!is_array($honeypots)) $honeypots = $honeypots ? [$honeypots] : [];
		$honeypots = array_map('trim', $honeypots);
		$eligibleTypes = ['text', 'textarea', 'email', 'url', 'tel', 'integer', 'float', 'number', 'select', 'radios', 'datetime', 'checkbox', 'checkboxes', 'selectmultiple', 'asmselect', 'page'];
		$fields = [];
		$fieldPages = $this->getFormFieldPageNumbers($form);
		$collisions = [];
		foreach($form->getChildrenFlat(['includeNestedForms' => false]) as $field) {
			if((string) $field->get('inputType') !== 'html' || (string) $field->get('htmlType') !== 'datetime') continue;
			if($form->getFieldByName($field->name . '__time')) {
				$collisions[$field->name] = true;
				$collisions[$field->name . '__time'] = true;
			}
		}
		foreach($form->getChildrenFlat(['includeNestedForms' => false]) as $field) {
			if(!$field instanceof FormBuilderField || !$field->name || in_array($field->name, $honeypots, true)) continue;
			if(isset($collisions[$field->name])) continue;
			if($pageNum > 0 && ($fieldPages[$field->name] ?? 1) !== $pageNum) continue;
			$type = strtolower(preg_replace('/^inputfield/i', '', (string) $field->type));
			if(!in_array($type, $eligibleTypes, true)) continue;
			$inputfield = $this->fbForm() === $form && $this->form() ? $this->form()->getChildByName($field->name) : null;
			if(!$inputfield) $inputfield = $field->getInputfield();
			$choiceType = $type;
			if($type === 'page') {
				$inputfield = $inputfield instanceof InputfieldPage ? $inputfield->getInputfield() : null;
				if(!$inputfield) continue;
				$choiceType = strtolower(preg_replace('/^inputfield/i', '', $inputfield->className()));
				if(!in_array($choiceType, ['select', 'radios', 'checkboxes', 'selectmultiple', 'asmselect'], true)) continue;
			}
			$multiple = in_array($choiceType, ['checkboxes', 'selectmultiple', 'asmselect'], true);
			$inputType = (string) $field->get('inputType');
			$htmlType = (string) $field->get('htmlType');
			$htmlDatetime = $type === 'datetime' && $inputType === 'html' && in_array($htmlType, ['date', 'time', 'datetime'], true);
			if($type === 'datetime' && $inputType !== 'text' && !$htmlDatetime) continue;
			$description = trim(strip_tags((string) ($field->description ?: $field->label ?: $field->name)));
			if($field->required && trim((string) $field->get('requiredIf')) === '') {
				$description = 'Required. Obtain a non-empty answer before final page preparation; other individual fields can be updated earlier. ' . $description;
			} else if($field->required) {
				$description = 'Conditionally required by FormBuilder only when this condition matches: ' . trim((string) $field->get('requiredIf')) . '. Collect a non-empty answer only when it applies. ' . $description;
			} else {
				$description = 'Optional. Return ' . ($multiple ? 'an empty array' : ($choiceType === 'checkbox' ? '"0"' : 'an empty string')) . ' if the visitor does not provide this. ' . $description;
			}
			$showIf = trim((string) $field->showIf);
			if($showIf !== '') $description = 'Shown only when this FormBuilder condition matches: ' . $showIf . '. Prepare only after the field is visible. ' . $description;
			if($type === 'datetime') {
				if($htmlDatetime && $htmlType === 'time') {
					$description .= $this->nativeDatetimeDescription($field, 'time');
				} else if($htmlDatetime) {
					$description .= ' Return the date as YYYY-MM-DD.';
					$description .= $this->nativeDatetimeDescription($field, 'date');
					$dateMin = trim((string) $field->get('dateMin'));
					$dateMax = trim((string) $field->get('dateMax'));
					if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateMin)) $dateMin = '';
					if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateMax)) $dateMax = '';
					if($dateMin !== '' || $dateMax !== '') {
						$range = [];
						if($dateMin !== '') $range[] = 'on or after ' . $dateMin;
						if($dateMax !== '') $range[] = 'on or before ' . $dateMax;
						$description .= ' FormBuilder date limits: ' . implode(' and ', $range) . '. Use these limits as guidance when discussing the requested date; FormBuilder remains responsible for enforcing them.';
					}
				} else {
					$dateFormat = trim((string) $field->get('dateInputFormat'));
					$timeFormat = trim((string) $field->get('timeInputFormat'));
					$format = trim(implode(' ', array_filter([$dateFormat, $timeFormat])));
					if($format !== '') $description .= ' Return one value in exactly this configured PHP date/time input format: ' . $format . '. Follow its order, separators and 12/24-hour format.';
				}
				if(!$htmlDatetime || $htmlType !== 'time') $description .= ' For an ambiguous date, ask for its exact day and month before preparing it. If no year is volunteered, use the next future occurrence relative to the current site date; confirm the resolved date with the visitor.';
				if($htmlDatetime && $htmlType === 'datetime') $description .= ' This is the date component of one date/time preference, paired with ' . $field->name . '__time. Ask about the preference together. Preserve the other component during corrections; clear it only when explicitly requested. Do not infer today for an otherwise unknown date.';
			}
			$property = ['type' => 'string', 'description' => $description];
			if($choiceType === 'checkbox') {
				$checkedValue = (string) $inputfield->get('checkedValue');
				$property['enum'] = ['0', $checkedValue];
				$property['description'] .= ' Return "' . $checkedValue . '" to check this box, or "0" to leave it unchecked. Never infer agreement or consent.';
			} else if(in_array($choiceType, ['select', 'radios', 'checkboxes', 'selectmultiple', 'asmselect'], true)) {
				$options = $inputfield && method_exists($inputfield, 'getOptions') ? $inputfield->getOptions() : [];
				$optionDetails = $this->optionDetails($options, $inputfield);
				if($field->required || $multiple) $optionDetails = array_values(array_filter($optionDetails, static fn(array $option): bool => $option['value'] !== ''));
				$enum = array_column($optionDetails, 'value');
				// Empty native checkbox groups render no controls to prepare, even for [].
				if($choiceType === 'checkboxes' && !$enum) continue;
				if($multiple) {
					$property['type'] = 'array';
					$property['items'] = ['type' => 'string', 'enum' => $enum ?: ['']];
					$property['maxItems'] = $enum ? 100 : 0;
					$property['description'] .= ' Return an array of exact submitted values. Use [] for no selections or an inactive field; replace the entire selection when correcting it.';
				} else {
					if(!$field->required && !in_array('', $enum, true)) array_unshift($enum, '');
					$property['enum'] = array_values(array_unique($enum ?: ['']));
				}
				if($enum) {
					$choiceDescriptions = [];
					foreach($optionDetails as $option) {
						$choiceDescriptions[] = 'submitted value "' . $option['value'] . '" is labelled "' . $option['label'] . '"';
					}
					if($choiceDescriptions) $property['description'] .= ' Use only an allowed FormBuilder choice and return its exact submitted value: ' . implode('; ', $choiceDescriptions) . '.';
				}
			}
			$fields[$field->name] = $property;
			if($htmlDatetime && $htmlType === 'datetime') {
				$fields[$field->name . '__time'] = ['type' => 'string', 'description' => 'Time component of ' . trim(strip_tags((string) ($field->label ?: $field->name))) . ', paired with date field ' . $field->name . '. Return an empty string if no time is supplied; a required date does not make its time required. Preserve the other component during corrections. Do not return a non-empty time without a known date; clarify that date first.' . $this->nativeDatetimeDescription($field, 'time')];
			}
		}
		return $fields;
	}

	/** Describe native machine formats/steps; browser controls remain the validation authority. */
	private function nativeDatetimeDescription(FormBuilderField $field, string $part): string {
		$step = (int) $field->get($part . 'Step');
		$format = $part === 'date' ? 'YYYY-MM-DD' : ($step > 0 && $step < 60 ? 'HH:MM:SS' : 'HH:MM');
		$text = ' Use native ' . $part . ' format ' . $format . ($part === 'time' ? ' in 24-hour time. Clarify AM/PM when unclear; do not infer it.' : '.');
		foreach(['Min' => 'minimum', 'Max' => 'maximum'] as $suffix => $label) {
			$value = trim((string) $field->get($part . $suffix));
			if(preg_match($part === 'date' ? '/^\d{4}-\d{2}-\d{2}$/' : '/^\d{2}:\d{2}(:\d{2})?$/', $value)) $text .= ' Native ' . $part . ' ' . $label . ': ' . $value . '.';
		}
		if($step > 0) $text .= ' Native step: ' . $step . ($part === 'date' ? ' days.' : ' seconds.');
		return $text;
	}

	/** @return array<string,string> Labels for supported fields on the selected page. */
	private function getToolFieldLabels(FormBuilderForm $form, array $toolFields): array {
		$labels = [];
		foreach($form->getChildrenFlat(['includeNestedForms' => false]) as $field) {
			if(!$field instanceof FormBuilderField || !$field->name || !isset($toolFields[$field->name])) continue;
			$labels[$field->name] = trim(strip_tags((string) ($field->label ?: $field->name)));
			if((string) $field->get('inputType') === 'html' && (string) $field->get('htmlType') === 'datetime' && isset($toolFields[$field->name . '__time'])) $labels[$field->name . '__time'] = $labels[$field->name] . ' (time)';
		}
		return $labels;
	}

	/** Return FormBuilder conditions for supported fields so the browser can enforce them. */
	private function getToolFieldRules(FormBuilderForm $form, array $toolFields): array {
		$rules = [];
		foreach($form->getChildrenFlat(['includeNestedForms' => false]) as $field) {
			if(!$field instanceof FormBuilderField || !$field->name || !isset($toolFields[$field->name])) continue;
			$showIf = trim((string) $field->showIf);
			$requiredIf = $field->required ? trim((string) $field->get('requiredIf')) : '';
			$nativeDatetime = (string) $field->type === 'Datetime' && (string) $field->get('inputType') === 'html';
			if($showIf === '' && $requiredIf === '' && !$nativeDatetime) continue;
			$rules[$field->name] = ['showIf' => $showIf, 'requiredIf' => $requiredIf];
			if($nativeDatetime) {
				$rules[$field->name]['nativeType'] = (string) $field->get('htmlType') === 'time' ? 'time' : 'date';
				if(isset($toolFields[$field->name . '__time'])) {
					$rules[$field->name]['timeField'] = $field->name . '__time';
					$rules[$field->name . '__time'] = ['showIf' => $showIf, 'requiredIf' => '', 'nativeType' => 'time', 'dateField' => $field->name];
				}
			}
		}
		return $rules;
	}

	/** Flatten native selectable options, preserving submitted values and visible labels. */
	private function optionDetails(array $options, $inputfield): array {
		$details = [];
		foreach($options as $value => $label) {
			if(is_array($label)) {
				$details = array_merge($details, $this->optionDetails($label, $inputfield));
				continue;
			}
			$value = (string) $value;
			if(preg_match('/^-+$/', $value)) continue;
			$attributes = $inputfield && method_exists($inputfield, 'getOptionAttributes') ? $inputfield->getOptionAttributes($value) : [];
			if(isset($attributes['disabled'])) continue;
			$details[] = ['value' => $value, 'label' => (string) $label];
		}
		return $details;
	}
}
