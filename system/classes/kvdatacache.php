<?php

/**
 * Set this to TRUE to output all the SQL for this class, FALSE to hide it
 * all, or NULL to hide it by default, but allow --show-sql to work.
 */
define('KVCACHE_DEBUG', NULL);

/**
 * This class implements both an in-memory cache – and access to – the
 * Key-Value database store. Each migration has its own unique set of keys,
 * but can access others should it need to.
 * @since 0.6.0
 */
class KVDataCache {
	protected static $instance = NULL;
	
	/**
	 * Singleton access to an instance of KVDataCache.
	 */
	public static function instance() {
		if (! (bool) self::$instance) {
			self::$instance = new KVDataCache;
		}
		
		return self::$instance;
	}
	
	// Ensure we have easy access to the LadderDB instance.
	protected $db;
	
	// Store the data locally.
	protected $cache = array();
	
	// Record what has been changed.
	protected $changed = array();
	
	/**
	 * Force anyone wanting to use this class to use the singleton by making the
	 * constructor protected.
	 */
	protected function __construct() {
		$this->db = LadderDB::factory();
		
		// Make sure we flush the cache when the database is changed.
		hooks::add_hook(hooks::DATABASE_CHANGED, array($this, 'flush'));
	}
	
	/**
	 * Automatically save the data when the object is destroyed.
	 */
	public function __destruct() {
		$this->save();
	}
	
	/**
	 * Create and/or return the whole array for a migration from the cache.
	 * @param $id integer Id of the migration to get data for.
	 */
	protected function &migration_array($id) {
		$id = (int) $id;
		
		// Ensure any existing data is loaded.
		$this->load_kvdata($id);
		
		if (! array_key_exists($id, $this->cache)) {
			$this->cache[$id] = array();
		}
		
		return $this->cache[$id];
	}
	
	/**
	 * Load from the database table into the local cache for a migration.
	 * @param $id integer The migration id to load data for.
	 * @param $force[optional] boolean Force a reload of the data?
	 */
	protected function load_kvdata($id, $force = FALSE) {
		// Avoid re-loading, unless asked to.
		if (array_key_exists($id, $this->cache) AND ! (bool) $force) {
			return FALSE;
		}
		
		$result = $this->db->query(sprintf(
			'SELECT `key`, `value` FROM `%s` WHERE (`migration` = %d)',
			$this->db->get_kvdata_table(), (int) $id
		), KVCACHE_DEBUG);
		
		if (! (bool) mysql_num_rows($result)) {
			// There was no data, so bail out.
			return FALSE;
		} else {
			$data = array();
			while ($row = mysql_fetch_object($result)) {
				$data[$row->key] = unserialize($row->value);
			}
			$this->cache[$id] = $data;
		}
	}
	
	public function set($id, $key, $value) {
		// Get the array for the migration id.
		$array =& $this->migration_array($id);
		
		// Set the value.
		$array[$key] = $value;
		
		// Note that it's been changed.
		$this->changed[$id] = $id;
	}
	
	public function get($id, $key = NULL, $default = NULL) {
		// Get the array for the passed migration id.
		$array = $this->migration_array($id);
		
		// Return the whole array if no key passed.
		if ($key === NULL) {
			return $array;
		}
		
		// Return the value, or the default.
		return array_key_exists($key, $array)
			? $array[$key]
			: $default
		;
	}
	
	public function remove($id, $key = NULL) {
		// Get the array (and make sure it really exists).
		$array =& $this->migration_array($id);
		
		if ($key === NULL) {
			// Remove the whole array if no key passed.
			unset($this->cache[$id]);
		} else {
			// Just remove the passed key.
			$array[$key] = NULL;
		}
		
		// Remember that this item was changed.
		$this->changed[$id] = $id;
	}
	
	public function save() {
		foreach ($this->changed as $id) {
			if (array_key_exists($id, $this->cache)) {
				foreach ($this->cache[$id] as $key => $value) {
					if ($value === NULL) {
						$this->db->query(sprintf(
							'DELETE FROM `%s` WHERE (`migration` = %d) AND '
							.'(`key` = \'%s\')',
							$this->db->get_kvdata_table(), (int) $id,
							$this->db->escape_value($key)
						), KVCACHE_DEBUG);
					} else {
						$this->db->query(sprintf(
							'REPLACE INTO `%s` SET '
							.'`migration` = %d, `key` = \'%s\', '
							.'`value` = \'%s\'',
							$this->db->get_kvdata_table(), (int) $id,
							$this->db->escape_value($key),
							$this->db->escape_value(serialize($value))
						), KVCACHE_DEBUG);
					}
				}
			} else {
				$this->db->query(sprintf(
					'DELETE FROM `%s` WHERE (`migration` = %d)',
					$this->db->get_kvdata_table(), $id
				), KVCACHE_DEBUG);
			}
		}
		
		// Clear the changed array to avoid double-saving.
		$this->changed = array();
	}
	
	/**
	 * Save all data, and clear the cache.
	 * @since 0.7.0
	 * @return NULL
	 */
	public function flush() {
		$this->save();
		$this->cache = array();
	}
}
