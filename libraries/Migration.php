<?php

abstract class Migration {
	protected static $migrations;
	
	/**
	 * Return an array of valid migration identifiers.
	 */
	public static function get_ids() {
		// Check for cached values first.
		if ((bool) self::$migrations) {
			return self::$migrations;
		}
		
		// Search the migrations path.
		$files = glob(APPPATH.'migrations'.DIRECTORY_SEPARATOR.'*.php');
		
		// Initialise the result.
		$result = array();
		
		// Loop over each, using PHP's duck typing to get just the numeric part.
		foreach ($files as $file) {
			// Strip the path part.
			$file = basename($file);
			
			// Store just the numeric part.
			$result[] = (int) $file;
		}
		
		// Return the array and cache it.
		return self::$migrations = $result;
	}
	
	/**
	 * Return the latest migration id.
	 */
	public static function latest_id() {
		$migrations = self::get_ids();
		return (int) end($migrations);
	}
	
	
	/**
	 * Return the filename of a Migration from its id.
	 */
	public static function filename($id) {
		// Build the path and skeleton.
		$path = APPPATH.'migrations'.DIRECTORY_SEPARATOR;
		$path .= sprintf('%05d*.php', (int) $id);
		
		// Search the filesystem.
		$files = glob($path);
		
		if (1 != ($count = count($files))) {
			throw new Ladder_Exception(
				'Failed to location migration %d (found %d files)',
				$id, $count
			);
		}
		
		// Return the 1st filename after auto-including it.
		require_once($files[0]);
		return $files[0];
	}
	
	/**
	 * Return the human-readable name from a Migration id.
	 */
	public static function name($id) {
		// Get the filename, minus extension.
		$file = basename(self::filename($id), '.php');
		
		// Split apart the id and name.
		list($id, $name) = explode('-', $file, 2);
		
		// Split the name pieces up.
		$parts = explode('_', $name);
		
		// Uppercase the first letter of each part and we're done!
		return implode('_', array_map('ucfirst', $parts));
	}
	
	/**
	 * Return the classname from a Migration id.
	 */
	public static function classname($id) {
		// Get the name.
		$name = self::name($id);
		
		// Append the classtype and formatted id. Done!
		return $name.sprintf('_Migration_%05d', $id);
	}
	
	/**
	 * Instantiate a Migration from its id.
	 */

	public static function factory($id) {
		$class = self::classname($id);
		return new $class;
	}

	/**
	 * Instance Variables and Methods.
	 */
	
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

	public function __construct() {
		// Do we need to check version numbers?
		if ((bool) $this->min_version) {
			Ladder::check_version_min($this->min_version);
		}
		
		// Get a Database instance for the migrations to use.
		$this->db = Database::instance();

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

	/**
	 * Automatically call the execute method on any tables used in up() or down().
	 */
	public function execute() {
		foreach ($this->tables as $table) {
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

	/**
	 * Automatically import any data files specified in the Migration.
	 */
	protected function import_data() {
		// Don't attempt to run if there's no data specified.
		if (! (bool) $this->import_data) {
			return;
		}

		// Explode the class, it's like Something_Name_Migraton_00001
		$parts = explode('_', get_class($this));
		$number = end($parts);

		foreach ($this->import_data as $table) {
			// Build the path to the file.
			$filename = sprintf(
				APPPATH.'migrations'.DIRECTORY_SEPARATOR
				.'data'.DIRECTORY_SEPARATOR.'%s_%s.csv',
				$number, $table
			);
			
			// Import via the Table class.
			$this->table($table)->import_csv($filename);
		}
	}
}
