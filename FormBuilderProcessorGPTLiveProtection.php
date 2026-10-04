<?php namespace ProcessWire;

/** Session-bound form tokens and atomic, rolling start limits. No visitor-facing policy text here. */
trait FormBuilderProcessorGPTLiveProtection {
	private const TOKEN_LIFETIME = 3600;

	/** Admin limits apply per form; zero explicitly disables that start limit. */
	private function protectionSettings(): array {
		$settings = $this->currentSettings();
		return [
			'visitorStartLimit' => max(0, min(1000, (int) ($settings['visitorStartLimit'] ?? 5))),
			'ipStartLimit' => max(0, min(1000, (int) ($settings['ipStartLimit'] ?? 20))),
			'startWindowMinutes' => max(1, min(1440, (int) ($settings['startWindowMinutes'] ?? 10))),
		];
	}

	/** One per-Action deadline shared by browser fetches and page-script loading. */
	private function requestTimeoutSeconds(): int {
		return max(1, min(300, (int) ($this->currentSettings()['requestTimeoutSeconds'] ?? 30)));
	}

	/** Add native Action inputs with defaults and visitor/admin scope explained. */
	private function addProtectionSettings(InputfieldWrapper $inputfields): void {
		$group = $this->wire()->modules->get('InputfieldFieldset');
		$group->name = 'voiceProtection';
		$group->label = __('Voice session limits', __FILE__);
		$group->description = __('Limits apply to new connection attempts for this form, including failed service starts. Page changes and pause/resume do not count. Visitors see the existing friendly fallback when blocked. A hidden form token is required and expires after one hour; reloading the form renews it. These settings do not limit the duration of an existing conversation.', __FILE__);
		$labels = [
			'visitorStartLimit' => __('Maximum starts per visitor session (default: 5)', __FILE__),
			'ipStartLimit' => __('Maximum starts per IP address (default: 20)', __FILE__),
			'startWindowMinutes' => __('Rolling window in minutes (default: 10)', __FILE__),
		];
		foreach($this->protectionSettings() as $key => $value) {
			$field = $this->wire()->modules->get('InputfieldInteger');
			$field->name = $key;
			$field->columnWidth = 50;
			$field->label = $labels[$key];
			$field->value = $value;
			$field->min = $key === 'startWindowMinutes' ? 1 : 0;
			$field->max = $key === 'startWindowMinutes' ? 1440 : 1000;
			$field->notes = $key === 'startWindowMinutes'
				? __('Both start limits use this window. Range: 1–1440 minutes.', __FILE__)
				: __('Set 0 to disable this limit. Range: 0–1000. A visitor is identified by the ProcessWire browser session; the IP backstop also covers new browser sessions. Shared networks share the IP allowance.', __FILE__);
			$group->add($field);
		}
		$field = $this->wire()->modules->get('InputfieldInteger');
		$field->name = 'requestTimeoutSeconds';
		$field->columnWidth = 50;
		$field->label = __('Request timeout in seconds (default: 30)', __FILE__);
		$field->value = $this->requestTimeoutSeconds();
		$field->min = 1;
		$field->max = 300;
		$field->notes = __('Maximum wait for each voice startup, form page/configuration, diagnostic or page-script request, including response loading. Range: 1–300 seconds. This does not limit conversation duration or change FormBuilder submission validation.', __FILE__);
		$group->add($field);
		$inputfields->add($group);
	}

	/** Keep a bounded set of tokens so separate tabs/renders do not invalidate one another. */
	private function issueFormToken(string $formName): string {
		$session = $this->wire()->session;
		$key = 'tokens_' . $formName;
		$tokens = (array) $session->getFor($this->className(), $key);
		$now = time();
		$tokens = array_filter($tokens, static fn($record) => is_array($record) && ($record['expires'] ?? 0) > $now);
		$token = bin2hex(random_bytes(32));
		$language = $this->wire()->user->language;
		$tokens[hash('sha256', $token)] = ['expires' => $now + self::TOKEN_LIFETIME, 'language' => $language ? (int) $language->id : 0];
		$session->setFor($this->className(), $key, array_slice($tokens, -20, null, true));
		return $token;
	}

	/** Require the same browser session/form and restore the rendered language for JSON requests. */
	private function acceptFormToken(string $formName, $token): bool {
		if(!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) return false;
		$tokens = (array) $this->wire()->session->getFor($this->className(), 'tokens_' . $formName);
		$record = $tokens[hash('sha256', $token)] ?? null;
		if(!is_array($record) || ($record['expires'] ?? 0) <= time()) return false;
		$languages = $this->wire()->languages;
		if($languages && !empty($record['language'])) {
			$language = $languages->get((int) $record['language']);
			if($language->id) $this->wire()->user->language = $language;
		}
		return true;
	}

	/** Retain only timestamps inside a rolling window; expiration is exact at the boundary. */
	private function recentStarts(array $timestamps, int $now, int $window): array {
		return array_values(array_filter($timestamps, static fn($timestamp) => is_int($timestamp) && $timestamp > $now - $window && $timestamp <= $now));
	}

	/**
	 * Reserve before contacting OpenAI. MySQL advisory lock makes the IP read/write atomic
	 * across different visitor sessions; ProcessWire's session lock serializes each visitor.
	 * Cache records expire naturally. IP addresses are hashed, never stored as raw values.
	 */
	private function reserveVoiceStart(string $formName): bool {
		$limits = $this->protectionSettings();
		$now = time();
		$window = $limits['startWindowMinutes'] * 60;
		$session = $this->wire()->session;
		$key = 'starts_' . $formName;
		$visitorStarts = $this->recentStarts((array) $session->getFor($this->className(), $key), $now, $window);
		if($limits['visitorStartLimit'] && count($visitorStarts) >= $limits['visitorStartLimit']) return false;
		if($limits['ipStartLimit']) {
			$cacheKey = 'gptlive-starts-' . hash('sha256', $formName . ':' . $session->getIP());
			$lockName = hash('sha256', $cacheKey);
			$database = $this->wire()->database;
			$lock = $database->prepare('SELECT GET_LOCK(:name, 2)');
			$lock->execute(['name' => $lockName]);
			if((int) $lock->fetchColumn() !== 1) throw new WireException('Could not acquire voice start limit lock');
			try {
				$cache = $this->wire()->cache;
				$ipStarts = $this->recentStarts((array) $cache->get($cacheKey), $now, $window);
				if(count($ipStarts) >= $limits['ipStartLimit']) return false;
				$ipStarts[] = $now;
				if(!$cache->save($cacheKey, $ipStarts, $window)) throw new WireException('Could not save voice start limit');
			} finally {
				$release = $database->prepare('SELECT RELEASE_LOCK(:name)');
				$release->execute(['name' => $lockName]);
			}
		}
		if($limits['visitorStartLimit']) {
			$visitorStarts[] = $now;
			$session->setFor($this->className(), $key, $visitorStarts);
		}
		return true;
	}

	/** Bound diagnostic writes separately without spending the new-session allowance. */
	private function acceptDiagnostic(string $formName): bool {
		$session = $this->wire()->session;
		$key = 'diagnostics_' . $formName;
		$now = time();
		$recent = $this->recentStarts((array) $session->getFor($this->className(), $key), $now, 60);
		if(count($recent) >= 10) return false;
		$recent[] = $now;
		$session->setFor($this->className(), $key, $recent);
		return true;
	}

	/** Technical reason is logged privately; every rejection uses the same editable fallback. */
	private function sessionFailure(int $status, string $detail): string {
		$form = $this->fbForm();
		if($form) $this->logSessionFailure($form->name, $status, $detail);
		return $this->jsonResponse($status, ['error' => $this->getVoiceMessages()['fallback']]);
	}
}
