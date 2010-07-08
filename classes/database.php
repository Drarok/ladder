<?php

class Database {
	protected $conn;
	protected $databases;
	protected $database_id;
	
	protected static $instance;
	
	public static function factory() {
		if (! self::$instance)
			new Database;
		
		return self::$instance;
	}

	public function __construct() {
		$this->connect();

		if (is_string($this->databases = Config::item('database.database')))
			$this->databases = array($this->databases);

		$this->database_id = -1;
		
		if (! self::$instance)
			self::$instance = $this;
	}

	public function __get($key) {
		if ($key === 'database') {
			echo 'Warning: Database->database is deprecated. Use Database->name', "\n";

			// Grab the current call stack.
			$stack = debug_backtrace(TRUE);

			// Output info.
			echo "\t", 'Debug Backtrace:', "\n";
			foreach ($stack as $info) {
				$file_path = $info['file'];
				if (substr($file_path, 0, strlen(APPPATH)) == APPPATH) {
					$file_path = substr($file_path, strlen(APPPATH));
					$file_path = 'APPPATH'.DS.$file_path;
				} elseif (substr($file_path, 0, strlen(SYSPATH)) == SYSPATH) {
					$file_path = substr($file_path, strlen(SYSPATH));
					$file_path = 'SYSPATH'.DS.$file_path;
				}

				// Is it a class method that was called?
				if (array_key_exists('type', $info)) {
					if ($info['type'] == '::') {
						$prefix = $info['class'].'::';
					} else {
						$prefix = get_class($info['object']).'->';
					}
				} else {
					$prefix = '';
				}

				$function = $prefix.$info['function'];

				echo "\t", sprintf('File: %s, line: %d, function: %s', $file_path, $info['line'], $function), "\n";
			}

			echo "\n";

			// Finally return.
			return $this->databases[$this->database_id];
		}

		if ($key === 'name') {
			return $this->databases[$this->database_id];
		}
	}

	protected function connect() {
		// Grab the port, prefix with colon if it's set.
		if ((bool) $port = Config::item('database.port')) {
			$port = ':'.$port;
		}

		// Initialise the options.
		$parsed_options = 0;

		// Check for options.
		if ((bool) Config::item('database.compress')) {
			$parsed_options |= MYSQL_CLIENT_COMPRESS;
		}

		// Attempt to connect.
		$this->conn = mysql_connect(
			$host = Config::item('database.hostname').$port,
			Config::item('database.username'),
			Config::item('database.password'),
			FALSE,
			$parsed_options
		);

		if (! (bool) $this->conn)
			throw new Exception('Unable to connect to database at '.$host.' '.mysql_error());

		echo 'Connected to ', $host, "\n";

		if ($this->database_id > -1) {
			if (! mysql_select_db($this->name, $this->conn))
				throw new Exception('Invalid database: '.$this->name);
		}
	}

	protected function disconnect() {
		mysql_close($this->conn);
		$this->conn = FALSE;
	}

	public function reconnect() {
		$this->disconnect();
		$this->connect();
	}

	public function escape_value($value) {
		return mysql_real_escape_string($value, $this->conn);
	}

	public function query($sql, $show_sql = NULL) {
		// If nothing passed, use params to set option.
		if ($show_sql === NULL) {
			global $params;
			$show_sql = $params['show-sql'];
		}

		// Should we show this SQL?
		if ($show_sql) {
			echo $sql, "\n";
		}
		
		$res = mysql_query($sql, $this->conn);

		if (! (bool) $res) {
			throw new Exception(mysql_error($this->conn));
		} else {
			return $res;
		}
	}

	public function insert_id() {
		return mysql_insert_id($this->conn);
	}

	public function next_database($output = TRUE) {
		++$this->database_id;

		if ($this->database_id < count($this->databases)) {
			if (! mysql_select_db($this->name, $this->conn))
				throw new Exception('Invalid database: '.$this->name);

			if ((bool) $output) {
				echo $this->name, '... ', "\n";
			}
			$this->check_migrations_table();

			return TRUE;
		} else {
			return FALSE;
		};
	}

	public function get_migrations_table() {
		static $table_name;

		if (! (bool) $table_name) {
			// Get the migrations table name from the config.
			$table_name = Config::item('database.migrations_table', 'migrations');
		}

		return $table_name;
	}

	/**
	 * Check that the migrations table exists. If not, create it.
	 */
	public function check_migrations_table() {
		// Check the migrations table exists
		$table_query = $this->query(
			'SHOW TABLES LIKE \''.$this->get_migrations_table().'\'',
			FALSE
		);
		
		if (! (bool) mysql_num_rows($table_query)) {
			$this->query(
				'CREATE TABLE `'.$this->get_migrations_table().'` ('
				.'`migration` int(11) NOT NULL default \'0\', '
				.'`applied` datetime NOT NULL default \'0000-00-00 00:00:00\', '
				.'UNIQUE KEY `migration` (`migration`)'
				.') TYPE=MyISAM', FALSE
			);
		}
	}

	public function get_current_migration() {
		// Find what the maximum migration is...
		$migration_query = $this->query(
			'SELECT MAX(`migration`) AS `migration` FROM `'
			.$this->get_migrations_table().'`',
			FALSE
		);
		$migration_result = mysql_fetch_object($migration_query);
		return (int) $migration_result->migration;
	}

	public function get_migrations() {
		// Query the table.
		$query = $this->query(sprintf(
			'SELECT `migration`  FROM `%s` ORDER BY `migration`',
			$this->get_migrations_table()
		));

		// Initialise our result array.
		$result = array();

		// Loop over each row.
		while ($row = mysql_fetch_object($query)) {
			$result[] = (int) $row->migration;
		}

		return $result;
	}

	public function has_migration($id) {
		$query = $this->query(sprintf(
			'SELECT `migration` FROM `%s` WHERE `migration` = %d',
			$this->get_migrations_table(), (int) $id
		));

		return mysql_num_rows($query) == 1;
	}

	public function remove_migration($id) {
		$query = $this->query(sprintf(
			'DELETE FROM `%s` WHERE `migration` = %d',
			$this->get_migrations_table(), (int) $id
		));
	}

	public function add_migration($id) {
		$query = $this->query(sprintf(
			'INSERT INTO `%s` (`migration`, `applied`) VALUES (%d, NOW())',
			$this->get_migrations_table(), (int) $id
		));
	}
}
