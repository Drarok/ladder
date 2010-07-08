<?php

class Migration_Model extends ORM {
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