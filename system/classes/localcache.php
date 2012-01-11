<?php

/**
 * This class implements both an in-memory cache – and access to – an
 * on-disk (local) Key-Value store.
 *
 * @since 0.7.1
 */
class LocalCache {
	public static function factory($filename) {
		return new LocalCache($filename);
	}

	protected $path;
	
	protected $cache = array();
	
	/**
	 * Flag to determine if we need to save any data when save() is next called.
	 *
	 * @var bool
	 */
	protected $_dirty = FALSE;

	public function __construct($filename) {
		// Make sure the cache path exists.
		if (! is_dir(Ladder::path('cache'))) {
			mkdir(Ladder::path('cache'));
		}
		
		// Store the path to the file, and attempt to load.
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
		if ($this->_dirty) {
			file_put_contents($this->path, serialize($this->cache));
			$this->_dirty = FALSE;
		}
	}

	public function set($key, $value) {
		$this->_dirty = TRUE;
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
			$this->_dirty = TRUE;
			unset($this->cache[$key]);
		}
	}

	public function clear() {
		$this->_dirty = TRUE;
		$this->cache = array();
	}
}

