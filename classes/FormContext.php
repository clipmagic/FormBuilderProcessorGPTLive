<?php namespace ProcessWire;

/** Native pages, known values and FormBuilder condition matching. */
trait FormBuilderProcessorGPTLiveFormContext {
	/** Count the pages created by FormBuilder Page Break fields. */
	private function formPageCount(FormBuilderForm $form): int {
		if(!$this->wire()->modules->isInstalled('InputfieldFormBuilderPageBreak')) return 1;
		$pageCount = 1;
		foreach($form->getChildrenFlat(['includeNestedForms' => false]) as $field) {
			if($field instanceof FormBuilderField && (string) $field->type === 'FormBuilderPageBreak') $pageCount++;
		}
		return $pageCount;
	}

	/** Page Break field label names the page reached from the current page. */
	private function nextPageBreakLabel(FormBuilderForm $form, int $pageNum): string {
		$breaks = $form->getPageBreakFields();
		$field = $breaks[$pageNum] ?? null;
		if(!$field) return '';
		$language = $this->wire()->user->language;
		$label = $language && !$language->isDefault() ? (string) $field->get('label' . $language) : '';
		if($label === '') $label = (string) $field->label;
		return trim(strip_tags($label));
	}

	/** Match FormBuilderMaker's configurable navigation button text. */
	private function nextControlLabel(FormBuilderForm $form): string {
		$language = $this->wire()->user->language;
		$label = $language && !$language->isDefault() ? (string) $form->get('nextText' . $language) : '';
		if($label === '') $label = (string) $form->nextText;
		return trim(strip_tags($label)) ?: __('Next', __FILE__);
	}

	/** @return array<string,int> Field name to FormBuilder page number. */
	private function getFormFieldPageNumbers(FormBuilderForm $form): array {
		$pageNum = 1;
		$fieldPages = [];
		foreach($form->getChildrenFlat(['includeNestedForms' => false]) as $field) {
			if(!$field instanceof FormBuilderField) continue;
			if((string) $field->type === 'FormBuilderPageBreak') {
				$pageNum++;
				continue;
			}
			if($field->name) $fieldPages[$field->name] = $pageNum;
		}
		return $fieldPages;
	}

	/** Return supported visitor answers already entered on this or an earlier page. */
	private function getKnownFormValues(FormBuilderForm $form, int $pageNum, array $allToolFields, array $pageToolFields, $currentValues): array {
		$fieldPages = $this->getFormFieldPageNumbers($form);
		$processor = $this->processor();
		$entry = $processor ? $processor->getEntry() : null;
		$fieldsByName = [];
		foreach($form->getChildrenFlat(['includeNestedForms' => false]) as $field) {
			if(!$field instanceof FormBuilderField || !$field->name) continue;
			$fieldsByName[$field->name] = $field;
			if((string) $field->get('inputType') === 'html' && (string) $field->get('htmlType') === 'datetime' && isset($allToolFields[$field->name . '__time'])) $fieldsByName[$field->name . '__time'] = $field;
		}
		$valuesByName = [];
		if(is_array($entry)) {
			foreach($allToolFields as $name => $definition) {
				$field = $fieldsByName[$name] ?? null;
				$parentName = $field ? $field->name : $name;
				if(($fieldPages[$parentName] ?? 1) > $pageNum || !array_key_exists($parentName, $entry)) continue;
				$value = $this->formValueToString($entry[$parentName]);
				if($field && (string) $field->type === 'Datetime' && (string) $field->get('inputType') === 'html' && $value !== '') {
					$timestamp = ctype_digit($value) ? (int) $value : strtotime($value);
					$isTime = $name !== $parentName || (string) $field->get('htmlType') === 'time';
					$step = (int) $field->get('timeStep');
					$value = $timestamp ? date($isTime ? ($step > 0 && $step < 60 ? 'H:i:s' : 'H:i') : 'Y-m-d', $timestamp) : '';
				}
				if(trim($value) !== '') $valuesByName[$name] = is_array($entry[$parentName]) ? array_slice(array_map('strval', array_filter($entry[$parentName], 'is_scalar')), 0, 100) : substr($value, 0, 4000);
			}
		}
		if(is_array($currentValues)) {
			foreach($currentValues as $name => $value) {
				if(!is_string($name) || !isset($pageToolFields[$name]) || (!is_scalar($value) && !is_array($value))) continue;
				if(is_array($value)) {
					$value = array_slice(array_map(static fn($part): string => substr((string) $part, 0, 4000), array_filter($value, 'is_scalar')), 0, 100);
					if(!$value) unset($valuesByName[$name]);
					else $valuesByName[$name] = $value;
					continue;
				}
				$value = substr((string) $value, 0, 4000);
				if(trim($value) === '') unset($valuesByName[$name]);
				else $valuesByName[$name] = $value;
			}
		}
		foreach($valuesByName as $name => $value) {
			$field = $fieldsByName[$name] ?? null;
			$showIf = $field ? trim((string) $field->showIf) : '';
			if($showIf !== '' && !$this->formFieldConditionsMatch($showIf, $valuesByName)) unset($valuesByName[$name]);
		}
		$totalLength = 0;
		$knownValues = [];
		foreach($valuesByName as $name => $value) {
			$field = $fieldsByName[$name] ?? null;
			$label = $field ? trim(strip_tags((string) ($field->label ?: $name))) : $name;
			$label = substr($label, 0, 300);
			$remaining = 20000 - $totalLength - strlen($name) - strlen($label);
			if($remaining <= 0) {
				break;
			}
			$value = substr(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : $value, 0, $remaining);
			$knownValues[] = [
				'name' => $name,
				'label' => $label,
				'value' => $value,
			];
			$totalLength += strlen($name) + strlen($label) + strlen($value);
		}
		return $knownValues;
	}

	/** Convert supported saved values to concise context without serializing objects. */
	private function formValueToString($value): string {
		if(is_scalar($value)) return (string) $value;
		if(!is_array($value)) return '';
		$values = [];
		foreach($value as $part) {
			if(is_scalar($part)) $values[] = (string) $part;
		}
		return implode(', ', $values);
	}

	/** Match saved values against FormBuilder's supported simple showIf syntax. */
	private function formFieldConditionsMatch(string $selector, array $values): bool {
		foreach(explode(',', $selector) as $condition) {
			if(!preg_match('/^([a-zA-Z][a-zA-Z0-9_-]*)\s*(>=|<=|!=|\*=|\^=|\$=|%=|~=|>|<|=)\s*(.*?)\s*$/', trim($condition), $match)) return false;
			$name = $match[1];
			$operator = $match[2];
			$expectedString = trim($match[3]);
			if(strlen($expectedString) >= 2 && (($expectedString[0] === '"' && substr($expectedString, -1) === '"') || ($expectedString[0] === "'" && substr($expectedString, -1) === "'"))) {
				$expectedString = substr($expectedString, 1, -1);
			}
			$expected = explode('|', $expectedString);
			// Missing/unselected dependencies are empty; choice lists match any selected value.
			$raw = $values[$name] ?? '';
			$actual = array_map('strval', array_filter(is_array($raw) ? $raw : [$raw], 'is_scalar'));
			if(!$actual) $actual = [''];
			if($operator === '=' && !array_intersect($actual, $expected)) return false;
			if($operator === '!=' && array_intersect($actual, $expected)) return false;
			if($operator === '*=' && !array_filter($actual, static fn(string $value): bool => (bool) array_filter($expected, static fn(string $part): bool => strpos($value, $part) !== false))) return false;
			if($operator === '^=' && !array_filter($actual, static fn(string $value): bool => (bool) array_filter($expected, static fn(string $part): bool => strpos($value, $part) === 0))) return false;
			if($operator === '$=' && !array_filter($actual, static fn(string $value): bool => (bool) array_filter($expected, static fn(string $part): bool => ($part === '' || substr($value, -strlen($part)) === $part)))) return false;
			if($operator === '~=' && !array_filter($actual, static fn(string $value): bool => (bool) array_filter($expected, static fn(string $part): bool => in_array($part, preg_split('/\s+/', trim($value)) ?: [], true)))) return false;
			if($operator === '%=' && !array_filter($actual, static fn(string $value): bool => (bool) array_filter($expected, static fn(string $part): bool => stripos($value, $part) !== false))) return false;
			if(in_array($operator, ['>', '<', '>=', '<='], true)) {
				// Mirror the browser's decimal/scientific grammar; blank, hex and infinity fail.
				$numeric = '/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/D';
				if(!preg_match($numeric, trim($expected[0]))) return false;
				$expectedNumber = (float) $expected[0];
				if(!is_finite($expectedNumber)) return false;
				$matches = false;
				foreach($actual as $value) {
					if(!preg_match($numeric, trim($value))) continue;
					$actualNumber = (float) $value;
					if(!is_finite($actualNumber)) continue;
					if(($operator === '>' && $actualNumber > $expectedNumber) || ($operator === '<' && $actualNumber < $expectedNumber) || ($operator === '>=' && $actualNumber >= $expectedNumber) || ($operator === '<=' && $actualNumber <= $expectedNumber)) $matches = true;
				}
				if(!$matches) return false;
			}
		}
		return true;
	}
}
