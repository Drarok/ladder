<?php

class LadderDB {
	protected $conn;
	protected $databases;
	protected $database_id;

	protected static $instance;

	public static function factory() {
		if (! (bool) self::$instance) {
			new LadderDB;
		}

		return self::$instance;
	}

	public function __construct() {
		if (! self::$instance) {
			self::$instance = $this;
		}

		$this->database_id = -1;
	}

	public function __get($key) {
		if ($key === 'name') {
			return $this->databases[$this->database_id];
		}
	}

	protected function connect() {
		// No need to connect again if we have a resource.
		if ((bool) $this->conn) {
			return;
		}

		// Grab the port
		if ((bool) $port = Config::item('database.port')) {
			$port = intval($port);
		}

		// Attempt to connect.
		$host = Config::item('database.hostname').$port;
		echo 'Connecting to ', $host, '... ';
		$this->conn = mysqli_connect(
			$host,
			Config::item('database.username'),
			Config::item('database.password'),
			$this->name,
			$port
		);

		if (! (bool) $this->conn)
			throw new Exception('Unable to connect to database at '.$host.' '.mysqli_error($this->conn));

		// Grab the version number from the server now we're connected.
		$version = $this->query('SELECT @@version');
		$version = mysqli_fetch_row($version);
		$version = $version[0];

		echo sprintf('Connected. Server version %s.', $version), PHP_EOL;

		if ($this->database_id > -1) {
			if (! mysqli_select_db($this->conn, $this->name))
				throw new Exception('Invalid database: '.$this->name);
		}

		hooks::run_hooks(hooks::DATABASE_CONNECT);
	}

	protected function disconnect() {
		mysqli_close($this->conn);
		$this->conn = FALSE;
		hooks::run_hooks(hooks::DATABASE_DISCONNECT);
	}

	public function reconnect() {
		$this->disconnect();
		$this->connect();
	}

	public function escape_value($value) {
		$this->connect();
		return mysqli_real_escape_string($this->conn, $value);
	}

	public function query($sql, $show_sql = NULL) {
		$this->connect();

		// If nothing passed, use params to set option.
		if ($show_sql === NULL) {
			global $params;
			$show_sql = $params['show-sql'];
		}

		// Should we show this SQL?
		if ($show_sql) {
			echo $sql, "\n";
		}

		$res = mysqli_query($this->conn, $sql);

		if (! (bool) $res) {
			$error = mysqli_error($this->conn);

			$warnings = array();

			// See if we need to ask for warnings information.
			if (strpos($error, 'Check warnings') !== FALSE) {
				$warn_query = mysqli_query($this->conn, 'SHOW WARNINGS');

				while ((bool) $warn_row = mysqli_fetch_object($warn_query)) {
					$warnings[] = $warn_row->Level.' - '.$warn_row->Message;
				}
			}

			throw new Exception($error.' '.implode(', ', $warnings));
		} else {
			return $res;
		}
	}

	public function insert_id() {
		return mysqli_insert_id($this->conn);
	}

	public function next_database($output = TRUE) {
		$this->connect();

		if (! (bool) $this->databases) {
			$this->databases = (array) Config::item('database.database');
		}

		++$this->database_id;

		if ($this->database_id < count($this->databases)) {
			if (! mysqli_select_db($this->conn, $this->name))
				throw new Exception('Invalid database: '.$this->name);

			if ((bool) $output) {
				echo $this->name, '... ', "\n";
			}
			$this->check_migrations_table();
			hooks::run_hooks(hooks::DATABASE_CHANGED);

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
	 * Get the name of the kvdata table.
	 * @since 0.6.0
	 */
	public function get_kvdata_table() {
		static $table_name;

		if (! (bool) $table_name) {
			$table_name = Config::item('database.kvdata_table', 'migrations_kvdata');
		}

		return $table_name;
	}

	/**
	 * Check that the migrations tables exist. If not, create them.
	 */
	public function check_migrations_table() {
		// Check the migrations table exists
		if (! (bool) Table::exists($this->get_migrations_table())) {
			Table::factory($this->get_migrations_table())
				->column('migration', 'integer', array('null' => FALSE))
				->column('applied', 'datetime', array('null' => FALSE))
				->index('primary', 'migration')
				->execute()
			;
		}

		// Ensure that the new `kvdata` table exists, and is the right structure.
		if (! Table::exists($this->get_kvdata_table())) {
			Table::factory($this->get_kvdata_table())
				->column('migration', 'integer', array('null' => FALSE))
				->column('key', 'varchar', array('limit' => 128, 'null' => FALSE))
				->column('value', 'longtext')
				->index('primary', array('migration', 'key'))
				->execute()
			;
		} else {
			// Ensure the on-disk structure matches the new format.
			$kvdata_table = Table::factory($this->get_kvdata_table(), TRUE);

			if (! array_key_exists('key', $kvdata_table->get_columns())) {
				echo 'WARNING: Upgrading kvdata store...', PHP_EOL;

				$kvdata_temp = Table::factory('migrations_kvdata_temp')
					->column('migration', 'integer', array('null' => FALSE))
					->column('key', 'varchar', array('limit' => 128, 'null' => FALSE))
					->column('value', 'longtext')
					->index('primary', array('migration', 'key'))
					->execute()
				;

				$kvdata_rows = $kvdata_table->select();

				foreach ($kvdata_rows as $row) {
					$row = (object) $row;
					$data = unserialize($row->kvdata);
					foreach ($data as $key => $value) {
						$kvdata_temp->insert(array(
							'migration' => $row->migration,
							'key' => $key,
							'value' => serialize($value),
						));
					}
				}

				// Archive the old table, and move the new table into place.
				$kvdata_table->rename($this->get_kvdata_table() . '_old');
				$kvdata_temp->rename($this->get_kvdata_table());
			}
		}
	}

	public function get_current_migration() {
		// Find what the maximum migration is...
		$migration_query = $this->query(
			'SELECT MAX(`migration`) AS `migration` FROM `'
			.$this->get_migrations_table().'`',
			FALSE
		);
		$migration_result = mysqli_fetch_object($migration_query);
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
		while ($row = mysqli_fetch_object($query)) {
			$result[] = (int) $row->migration;
		}

		return $result;
	}

	public function has_migration($id) {
		$query = $this->query(sprintf(
			'SELECT `migration` FROM `%s` WHERE `migration` = %d',
			$this->get_migrations_table(), (int) $id
		));

		return mysqli_num_rows($query) == 1;
	}

	/**
	 * Remove a migration from the database.
	 * @param integer $migration The migration id to remove.
	 */
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

	/**
	 * Return an array containing all the tables in the current database.
	 * @return array
	 * @since 0.7.0
	 */
	public function get_tables() {
		$query = $this->query('SHOW TABLES');

		$system_tables = array(
			$this->get_migrations_table(),
			$this->get_kvdata_table(),
		);

		$tables = array();
		while ((bool) $row = mysqli_fetch_row($query)) {
			if (in_array($row[0], $system_tables)) {
				continue;
			}

			$tables[] = $row[0];
		}

		return $tables;
	}
}
