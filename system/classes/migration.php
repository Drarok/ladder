<?php

abstract class Migration {
	protected $tables = array();

	protected $databases = FALSE;
	protected $database_name = FALSE;
	protected $should_run = TRUE;

	/**
	 * $import_data array Table names to import. Prefixed with the migration number
	 * when loaded from migrations/data. eg 'table' becomes '00001_table.csv'.
	 */
	protected $import_data = array();

	public function __construct(Database $database) {
		// Always store the database instance.
		$this->db = $database;

		// Save the current database name.
		$this->database_name = $database->name;

		// Detect if we should run or not, if they've set the $databases property.
		if (is_array($this->databases))
			$this->should_run = in_array($database->name, $this->databases);

		// Allow migrations to perform any setup they need.
		$this->init();
	}

	/**
	 * Automatically execute tables that need it when we're unset.
	 */
	public function __destruct() {
		$this->execute();
	}

	/**
	 * Default implementation of init does nothing, but subclasses
	 * may override it to do any setup they need before the migration
	 * is run (e.g. it is called before up() or down()).
	 */
	public function init() {
	}

	public function execute() {
		foreach ($this->tables as $id => $table) {
			$table->execute();
		}

		// Don't execute twice.
		$this->tables = array();
	}

	// Check we should run before passing to the subclass function.
	public function __call($method, $args) {
		$valid_methods = array('_up', '_down');

		if (in_array($method, $valid_methods)) {
			if (! $this->should_run)
				return;
			else {
				// Remove the underscore and run.
				$method = substr($method, 1);
				$result = $this->$method($args);

				// Do we need to execute the data() method?
				global $params;
				if ($method == 'up' AND (bool) $params['with-data']) {
					$this->import_data();
				}

				return $result;
			}
		}
	}

	/**
	 * Create an instance of Table representing a new table.
	 * @return Table 
	 * @param string $name Name of the new table to create.
	 */
	protected function create_table($name) {
		$this->tables[] = $table = new Table($name, FALSE);
		return $table;
	}

	/**
	 * Create and instance of Table representing an existing table.
	 * @return Table
	 * @param string $name Name of the table to open.
	 */
	protected function table($name) {
		$this->tables[] = $table = new Table($name, TRUE);
		return $table;
	}

	protected function drop_table($name) {
		sql::drop_table($name);
	}

	protected function add_column($table, $name, $type, $options = array()) {
		sql::add_column($table, array($name, $type, $options));
	}

	protected function drop_column($table, $name) {
		sql::drop_column($table, $name);
	}

	protected function add_index($table, $name, $columns = FALSE, $options = array()) {
		sql::add_index($table, array($name, $columns, $options));
	}

	protected function drop_index($table, $name) {
		sql::drop_index($table, $name);
	}

	protected function import_data() {
		if (! (bool) $this->import_data) {
			echo 'Warning: No import_data specified for migration ', get_class($this), "\n";
			return;
		}

		// Explode the class, it's like Something_Name_Migraton_00001
		$parts = explode('_', get_class($this));
		$number = end($parts);

		foreach ($this->import_data as $table) {
			$filename = APPPATH.sprintf('migrations/data/%s_%s.csv', $number, $table);
			$this->table($table)->import_csv($filename);
		}
	}
}
