<?php

class {{MIGRATION_CLASS}} extends Migration {
	protected $min_version = '{{LADDER_VERSION}}';
	// protected $databases = FALSE;
	// protected $import_data = array();
	// protected $import_update = FALSE; // TRUE for all, or array of tables from import_data to update.
	// protected $import_key_fields = FALSE; // Array of 'table' => array('fields', 'for', 'where').
	// protected $unimport_data = TRUE; // or an array of tables to unimport.
	// protected $unimport_key_fields = FALSE; // or an array of table => array(key, fields).

	public function up() {
		// $this->create_table('new_table')
		// $this->table('table')
		// $this->set('key', 'value');
	}

	public function down() {
		// $this->table('new_table')->drop();
		// $value = $this->get('key');
	}
}