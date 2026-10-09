<?php namespace ProcessWire;

/** Bundled asset URLs, root/subdirectory handling and cache versions. */
trait FormBuilderProcessorGPTLiveAssets {
	/** Return this module's browser client URL in root-relative form. */
	private function defaultScriptPath(): string {
		return $this->defaultAssetPath('FormBuilderProcessorGPTLive.js');
	}

	/** Return this module's default voice-control stylesheet in root-relative form. */
	private function defaultStylePath(): string {
		return $this->defaultAssetPath('FormBuilderProcessorGPTLive.css');
	}

    /**
     * Resolve bundled assets relative to the installation root, including subdirectories.
     * @param string $filename
     * @return string
     * @throws WireException
     */
    private function defaultAssetPath(string $filename): string {
		$config = $this->wire()->config;
		$moduleUrl = $config->urls($this->className());
		if(!$moduleUrl) $moduleUrl = $config->urls->siteModules . $this->className() . '/';
		$url = $moduleUrl . $filename;
		$rootUrl = (string) $config->urls->root;
		if(strpos($url, $rootUrl) === 0) return '/' . ltrim(substr($url, strlen($rootUrl)), '/');
		return $url;
	}

	/** Resolve saved/default JS or CSS paths against the site root, then version local assets. */
	private function configuredAssetUrl(string $setting, string $defaultPath): string {
		$url = trim((string) ($this->currentSettings()[$setting] ?? ''));
		if($url === '') $url = $defaultPath;
		$rootUrl = (string) $this->wire()->config->urls->root;
		if(strlen($url) && strpos($url, '//') === false && strpos($url, $rootUrl) !== 0) {
			$url = $rootUrl . ltrim($url, '/');
		}
		return $this->addAssetCacheBuster($url);
	}

	/** Add a cache version when a root-relative asset maps to a local file. */
	private function addAssetCacheBuster(string $url): string {
		$urlParts = parse_url($url);
		if(!is_array($urlParts) || isset($urlParts['scheme']) || isset($urlParts['host'])) return $url;
		$urlPath = parse_url($url, PHP_URL_PATH);
		if(!is_string($urlPath) || $urlPath === '') return $url;
		$urlPath = rawurldecode($urlPath);
		$rootUrlPath = (string) parse_url((string) $this->wire()->config->urls->root, PHP_URL_PATH);
		if($rootUrlPath !== '' && $rootUrlPath !== '/' && strpos($urlPath, $rootUrlPath) === 0) {
			$urlPath = substr($urlPath, strlen($rootUrlPath));
		}
		$rootPath = realpath((string) $this->wire()->config->paths->root);
		if($rootPath === false) return $url;
		$filePath = realpath($rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($urlPath, '/')));
		if($filePath === false || strpos($filePath, $rootPath . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) return $url;
		$separator = strpos($url, '?') === false ? '?' : '&';
		return $url . $separator . 'v=' . (int) filemtime($filePath);
	}

	/** Sanitize administrator-selected browser asset URLs. */
	public function set($key, $value) {
		if(in_array($key, ['jsURL', 'cssURL'], true) && strlen((string) $value)) {
			$value = $this->wire()->sanitizer->url((string) $value);
			$urlPath = parse_url($value, PHP_URL_PATH);
			$extension = $key === 'jsURL' ? '.js' : '.css';
			if(!is_string($urlPath) || strtolower(substr($urlPath, -strlen($extension))) !== $extension) {
				$this->error($key === 'jsURL'
					? __('The voice controls script URL must point to a .js file.', __FILE__)
					: __('The voice controls stylesheet URL must point to a .css file.', __FILE__));
				return '';
			}
		}
		return parent::set($key, $value);
	}
}
