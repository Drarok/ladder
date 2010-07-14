<?php

class Migration_Model extends ORM {
	/**
	 * Return an instance representing the latest migration.
	 */
	public static function latest() {
		// Get the latest migration by id.
		$result = ORM::factory('migration')
			->orderby('migration', 'DESC')
			->find()
		;
		
		// Throw an exception if there's no result.
		if (! $result->loaded) {
			throw new Ladder_Exception('Failed to load the latest migration');
		}
		
		return $result;
	}
	
	/**
	 * Find out if the specified migration id has been applied to the database.
	 */
	public static function exists($id) {
		return ORM::factory('migration')
			->where('migration', (int) $id)
			->find()
			->loaded
		;
	}
	
	/**
	 * Store the migration id to the database as applied.
	 */
	public static function create($id) {
		if (Migration_Model::exists($id)) {
			throw new Ladder_Exception('Cannot add migration %d as it already exists', (int) $id);
		}
		
		$migration = ORM::factory('migration');
		$migration->migration = (int) $id;
		$migration->applied = date('Y-m-d H:i:s');
		$migration->save();
	}
	
	/**
	 * Instance Variables and Methods.
	 */
	protected $primary_key = 'migration';
	protected $primary_val = 'applied';
	
	public function __construct($id = NULL) {
		$this->table_name = Kohana::config('ladder.migrations_table');
		
		// Ensure the migrations table exists before all else.
		$this->check_table();
		
		parent::__construct($id);
	}
	
	protected function check_table() {
		Database::instance()->query(
			'CREATE TABLE IF NOT EXISTS `migrations` '
			.'(`migration` INTEGER NOT NULL PRIMARY KEY, '
			.'`applied` DATETIME NOT NULL)'
		);
	}
	
	/**
	 * Find and return the latest migration.
	 */
	public function latest_id() {
		// Get the latest id from the database.
		$latest_id = $this->db
			->select(new Database_Expression('MAX(`migration`) AS `latest_id`'))
			->from($this->table_name)
			->get()
			->current()
			->latest_id
		;
		
		if (! (bool) $latest_id) {
			$latest_id = 0;
		}
		
		return $latest_id;
	}
}