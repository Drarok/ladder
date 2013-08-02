<?php

abstract class Migration {
	protected $tables = array();

	protected $databases = FALSE;
	protected $database_name = FALSE;
	protected $should_run = TRUE;
	protected $min_version = FALSE;

	/**
	 * $import_data array Table names to import. Prefixed with the migration
	 * number when loaded from migrations/data. eg 'table' becomes
	 * '00001_table.csv'.
	 */
	protected $import_data = array();

	/**
	 * $import_update mixed Should imports perform and UPDATE instead of an
	 * INSERT? Either boolean TRUE/FALSE for all imports, or an array of 
	 * table names.
	 */
	protected $import_update = FALSE;

	/**
	 * $import_key_fields array This *must* be set when $import_update is TRUE.
	 * Specify the fields to use in the WHERE clause when importing via UPDATE.
	 * e.g. array('table_one' => 'id', 'table_two' => array('tab_id', 'name'));
	 */
	protected $import_key_fields = FALSE;

	/**
	 * $unimport_data mixed Either FALSE to disable unimport, TRUE to mirror
	 * the $import_data property, or an explicit array of tables to unimport.
	 */
	protected $unimport_data = TRUE;

	/**
	 * $unimport_key_fields mixed Either FALSE to use all fields in the CSV,
	 * or an array of arrays containing the fields to use as the WHERE clause
	 * when unmporting.
	 * e.g. array('table1' => 'id', 'table_2' => array('tab_id', 'name')).
	 */
	protected $unimport_key_fields = FALSE;

	public static function factory(LadderDB $database, $id) {
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
		$migration_path = LADDER_APPPATH.'migrations'.DS;

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
		// Pass integers through Migration::file_name first.
		if (is_numeric($file_name)) {
			$file_name = Migration::file_name($file_name);
			if ($file_name === FALSE) {
				return FALSE;
			}
		}

		// Make sure we only look at the filename.
		$file_name = basename($file_name, '.php');

		// Split the id and name apart.
		$parts = explode('-', $file_name, 2);

		// Detect timestamps and return the new format.
		if (count($parts) == 1 && $parts[0] > 1000000000) {
			return 'Migration_' . $parts[0];
		}

		// We should always end up with 2 parts for sequential ids.
		if (count($parts) != 2) {
			return FALSE;
		}

		// Return the sequential class name.
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
		$migrations_path = LADDER_APPPATH.'migrations'.DS.'*.php';

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
				if ($id > 1000000000) {
					$migration_class = substr($migration_class, strlen('Migration_'));
				} else {
					$migration_class = substr($migration_class, 0, -16);
				}
			}

			$result[$id] = $migration_class;
		}

		return $result;
	}

	public static function get_latest_migration_id() {
		$migrations = Migration::get_migration_ids();
		return end($migrations);
	}

	public function __construct(LadderDB $database) {
		// Do we need to check version numbers?
		if ((bool) $this->min_version) {
			Ladder::check_version_min($this->min_version);
		}

		// Always store the LadderDB instance.
		$this->db = $database;

		// Save the current database name.
		$this->database_name = $database->name;

		// Detect if we should run or not, if they've set the $databases property.
		if ((bool) $this->databases) {
			$this->should_run = in_array($database->name, (array) $this->databases);
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
		$this->finalise();
	}

	/**
	 * Default implementation of init does nothing, but subclasses
	 * may override it to do any setup they need before the migration
	 * is run (e.g. it is called before up() or down()).
	 */
	protected function init() {
	}
	
	/**
	 * Default implementation of finalise does nothing, but subclasses
	 * may override if to do any cleanup after the migrations has run.
	 */
	protected function finalise() {
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
						echo "\t", 'Warning: ', $e->getMessage(), ' when trying to import.', PHP_EOL;
					}
				} elseif ($method == 'down' AND (bool) $params['with-data']) {
					try {
						$this->unimport_data();
					} catch (Exception $e) {
						echo "\t", 'Warning: ', $e->getMessage(), ' when trying to unimport.', PHP_EOL;
					}
				}
				
				if ($method == 'up') {
					// Make sure the key-value data is stored after an upgrade.
					KVDataCache::instance()->save();
					
					// Let the hooks system know we're done.
					hooks::run_hooks(hooks::MIGRATION_UP);
				} elseif ($method == 'down') {
					// Remove the key-value data after a downgrade.
					KVDataCache::instance()->remove($this->id);
					
					// Let the hooks system know we're done.
					hooks::run_hooks(hooks::MIGRATION_DOWN);
				}

				return $result;
			}
		}
	}

	/**
	 * Override __get to add support for id and id_padded properties.
	 * @since 0.4.10
	 */
	public function __get($key) {
		if ($key === 'id' OR $key === 'id_padded') {
			// Explode the class name, it's like Something_Name_Migraton_00001
			$parts = explode('_', get_class($this));
			
			// Get the last element from the array.
			$number = end($parts);
			
			// Return the id as an integer or string.
			if ($key === 'id') {
				return (int) $number;
			} elseif ($key === 'id_padded') {
				return $number;
			}
		}
	}

	/**
	 * Default implementation of test() does nothing, but migrations may
	 * override this to provide some tests for use with --run-tests.
	 */
	public function test() {
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
			$this->tables[$name] = new Table($name, NULL, $options);
		}

		return $this->tables[$name];
	}
	
	protected function import_data() {
		if (! (bool) $this->import_data) {
			return;
		}

		foreach ((array) $this->import_data as $table) {
			// We should update if import_update is TRUE,
			// or contains the table name in an array.
			$use_update = (
				($this->import_update === TRUE) OR
				(
					is_array($this->import_update) AND
					in_array($table, $this->import_update)
				)
			);

			/**
			 * The above code didn't have enough parentheses, so wasn't working correctly.
			 * I used the caveman debugging output below to diagnose it.
			 * var_dump($this->import_update);
			 * var_dump($table);
			 * echo 'import_update == ', ($this->import_update === TRUE) ? 'TRUE' : 'FALSE', PHP_EOL;
			 * echo 'is_array === ', is_array($this->import_update) ? 'TRUE' : 'FALSE', PHP_EOL;
			 * echo 'in_array === ', in_array($table, $this->import_update) ? 'TRUE' : 'FALSE', PHP_EOL;
			 * echo 'Update === ', $use_update ? 'TRUE' : 'FALSE', PHP_EOL;
			 */

			// Inform the user what we're up to.
			echo "\t\t",
				$use_update ? 'Updating' : 'Importing',
				' data for ', $table, PHP_EOL
			;

			// Make sure key fields are set.
			if ($use_update) {
				if (array_key_exists($table, $this->import_key_fields)) {
					$key_fields = $this->import_key_fields[$table];
				} else {
					throw new Exception(sprintf(
						'Missing key fields for UPDATE table %s',
						$table
					));
				}
			} else {
				$key_fields = FALSE;
			}

			$filename = LADDER_APPPATH.sprintf('migrations/data/%s_%s.csv', $this->id_padded, $table);

			$this->table($table)
				->import_csv($filename, $use_update, $key_fields)
			;
		}
	}

	/**
	 * Delete data specified in the import_data property, the opposite
	 * of import_data().
	 * @since 0.4.11
	 */
	protected function unimport_data() {
		// Return immediately if unimport is disabled.
		if (! (bool) $this->unimport_data) {
			return;
		}

		// Use the tables specified in import_data if unimport_data isn't explicit.
		if ($this->unimport_data === TRUE) {
			$tables = $this->import_data;
		} else {
			$tables = (array) $this->unimport_data;
		}

		// Check there's actually some tables to work with.
		if (! (bool) $tables) {
			return;
		}

		foreach ($tables as $table) {
			echo "\t\t", 'Unimporting data for ', $table, PHP_EOL;
			$filename = LADDER_APPPATH.sprintf('migrations/data/%s_%s.csv', $this->id_padded, $table);

			// Check for specified key fields.
			if ((bool) $this->unimport_key_fields) {
				$key_fields = array_key_exists($table, $this->unimport_key_fields)
					? (array) $this->unimport_key_fields[$table]
					: FALSE
				;
			} else {
				// No key fields specified at all.
				$key_fields = FALSE;
			}

			// Pass on to the Table class.
			$this->table($table)->unimport_csv($filename, $key_fields);
		}
	}

	/**
	 * Load data from a file in the 'data' directory, auto-prefixing the
	 * filename with the migration number.
	 * @param string $name File to load (exclude the migration number).
	 * @since 0.4.10
	 */
	protected function data($name) {
		// Files are always lower-case.
		$name = strtolower($name);
		
		// Use the ladder class to get the file data.
		return Ladder::file('migrations', 'data', $this->id_padded.'_'.$name);
	}
	
	/**
	 * Allow migrations to store key-value pairs in the database.
	 * @param string $key Key to store the data against.
	 * @param mixed $value Data to store against the key.
	 * @since 0.6.0
	 */
	protected function set($key, $value) {
		$cache = KVDataCache::instance();
		$cache->set($this->id, $key, $value);
	}
	
	/**
	 * Allow migrations to fetch the key-value pairs stored in the database.
	 * @param $key[optional] string The key to fetch the value for, or nothing to get all data.
	 * @param $default[optional] mixed Default value to return if not in the database.
	 * @param $id[optional] integer The id of the migration to get data for, or nothing
	 * to refer use to the current migration.
	 * @since 0.6.0
	 */
	protected function get($key = NULL, $default = NULL, $id = NULL) {
		if (! (bool) $id) {
			$id = $this->id;
		}
		
		return KVDataCache::instance()->get($id, $key, $default);
	}
	
	/**
	 * Allow migrations to remove all their data (such as for a down() method).
	 * @param $key[optional] The key to remove, or nothing to remove all data.
	 */
	protected function remove($key = NULL) {
		KVDataCache::instance()->remove($this->id, $key);
	}
}
