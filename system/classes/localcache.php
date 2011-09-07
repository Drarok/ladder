<?php

/**
 * This class implements both an in-memory cache – and access to – an
 * on-disk (local) Key-Value store.
 * @since 0.7.1
 */
class LocalCache {
	public static function factory($filename) {
		return new LocalCache($filename);
	}

	protected $path;
	protected $cache = array();

	public function __construct($filename) {
		// Make sure the cache path exists.
		if (! is_dir(Ladder::path('cache'))) {
			mkdir(Ladder::path('cache'));
		}
		$this->path = Ladder::path('cache', $filename);
		$this->load();
	}

	public function __destruct() {
		$this->save();
	}

	public function load() {
		if (! file_exists($this->path)) {
			// No file, so bail out immediately.
			return;
		}

		$this->cache = unserialize(file_get_contents($this->path));
	}
	
	public function save() {
		file_put_contents($this->path, serialize($this->cache));
	}

	public function set($key, $value) {
		$this->cache[$key] = $value;
	}

	public function get($key = NULL, $default = NULL) {
		if ($key === NULL) {
			return $this->cache;
		}

		return array_key_exists($key, $this->cache)
			? $this->cache[$key]
			: $default
		;
	}

	public function remove($key) {
		if (array_key_exists($key, $this->cache)) {
			unset($this->cache[$key]);
		}
	}
}

