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
}