<?php

abstract class Migration {
	protected $tables = array();

	protected $databases = FALSE;
	protected $database_name = FALSE;
	protected $should_run = TRUE;
	protected $min_version = FALSE;

	/**
	 * $import_data array Table names to import. Prefixed with the migration number
	 * when loaded from migrations/data. eg 'table' becomes '00001_table.csv'.
	 */
	protected $import_data = array();

	public static function factory(Database $database, $id) {
		// Initialise.
		$instance = FALSE;

		// Get the class name from the id.
		$migration_file = Migration::file_name($id);

		// If the file was found, get the class name.
		if ((bool) $migration_file) {
			require_once($migration_file);
			$migration_class = Migration::class_name($migration_file);
		}

		// If we got a class name, instantiate.
		if ((bool) $migration_class) {
			$instance = new $migration_class($database);
		}

		return $instance;
	}

	/**
	 * Return the full path to a migration, based on its id.
	 * @param integer The id to look for.
	 * @return mixed Either a boolean FALSE on failure, or a string,
	 * specifying the full path on success.
	 */
	public static function file_name($id) {
		// Work out the path.
		$migration_path = APPPATH.'migrations'.DS;

		// Append the filename skeleton.
		$migration_path .= sprintf('%05d-*', (int) $id);

		// Look on the filesystem for it.
		$files = glob($migration_path);

		// If we didn't find it, bail.
		if (count($files) != 1) {
			return FALSE;
		}

		return $files[0];
	}

	/**
	 * Return the class name of a Migration, based off its file name.
	 * @param $file_name mixed Either the id or filename of a migration. Can optionally include the path.
	 * @return mixed Either boolean FALSE on failure, or the class name.
	 */
	public static function class_name($file_name) {
		// Pass integers through Migration::class_name first.
		if (is_numeric($file_name)) {
			$file_name = Migration::file_name($file_name);
			if ($file_name === FALSE) {
				return FALSE;
			}
		}

		// Make sure we only look at the filename.
		$file_name = basename($file_name, EXT);

		// Split the id and name apart.
		$parts = explode('-', $file_name, 2);

		// We should always end up with 2 parts.
		if (count($parts) != 2) {
			return FALSE;
		}

		// Return the class name.
		return sprintf(
			'%s_Migration_%05d',
			implode('_', array_map('ucfirst', explode('_', $parts[1]))),
			$parts[0]
		);
	}

	/**
	 * Return an associative array of <id> => <migration_path>.
	 * @return array
	 */
	public static function get_migration_paths() {
		// Work out the path to our migrations.
		$migrations_path = APPPATH.'migrations'.DS.'*.php';

		// Search the filesystem and sort.
		$migrations = glob($migrations_path);
		sort($migrations);
		
		// Initialise the result.
		$result = array();

		// Split the numeric part off the filename.
		foreach ($migrations as $file_path) {
			// Split the id off the front.
			list($id) = explode('-', basename($file_path), 2);

			// Make sure it's an integer.
			$id = (int) $id;

			// Make sure it's unique.
			if (array_key_exists($id, $result)) {
				throw new Exception('Duplicate migration id: '.$id);
			}

			// Add it to the result array.
			$result[$id] = $file_path;
		}

		return $result;
	}

	/**
	 * Return an array of migration ids.
	 * @return array
	 */
	public static function get_migration_ids() {
		// Simply return the keys from get_migration_paths.
		return array_keys(Migration::get_migration_paths());
	}

	/**
	 * Get an associative array of <id> => <class_name>.
	 * @return array
	 */
	public static function get_migration_names($full_name = FALSE) {
		// Get the paths.
		$migrations = Migration::get_migration_paths();

		// Initialise our result array.
		$result = array();

		// Loop over each file and get its class name.
		foreach ($migrations as $id => $migration_path) {
			$migration_class = Migration::class_name($migration_path);

			if (! (bool) $full_name) {
				$migration_class = substr($migration_class, 0, -16);
			}

			$result[$id] = $migration_class;
		}

		return $result;
	}

	public static function get_latest_migration_id() {
		$migrations = Migration::get_migration_ids();
		return end($migrations);
	}

	public function __construct(Database $database) {
		// Do we need to check version numbers?
		if ((bool) $this->min_version) {
			Ladder::check_version_min($this->min_version);
		}

		// Always store the database instance.
		$this->db = $database;

		// Save the current database name.
		$this->database_name = $database->name;

		// Detect if we should run or not, if they've set the $databases property.
		if (is_array($this->databases)) {
			$this->should_run = in_array($database->name, $this->databases);
		}

		// Get a reference to the grant manager singleton.
		$this->permissions = Grant_Manager::instance();

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
					try {
						$this->import_data();
					} catch (Exception $e) {
						echo "\t", 'Warning: ', $e->getMessage(), ' when trying to import.', "\n";
					}
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
	protected function create_table($name, $options = NULL) {
		if (array_key_exists($name, $this->tables)) {
			throw new Exception('Table has already been created: '.$name);
		}

		return $this->tables[$name] = new Table($name, FALSE, $options);
	}

	/**
	 * Create and instance of Table representing an existing table.
	 * @return Table
	 * @param string $name Name of the table to open.
	 */
	protected function table($name, $options = NULL) {
		if (! array_key_exists($name, $this->tables)) {
			$this->tables[$name] = new Table($name, TRUE, $options);
		}

		return $this->tables[$name];
	}

	protected function drop_table($name, $if_exists = FALSE) {
		sql::drop_table($name, $if_exists);
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
			return;
		}

		// Explode the class, it's like Something_Name_Migraton_00001
		$parts = explode('_', get_class($this));
		$number = end($parts);

		foreach ($this->import_data as $table) {
			echo "\t\t", 'Importing data for ', $table, PHP_EOL;
			$filename = APPPATH.sprintf('migrations/data/%s_%s.csv', $number, $table);
			$this->table($table)->import_csv($filename);
		}
	}
}
