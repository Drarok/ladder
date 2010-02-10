<?php

final class Ladder {
	protected $db;
	protected $options;

	public static $show_sql = FALSE;

	const VERSION = '0.2.0';

	public function __construct($migrate_to, $simulate = FALSE) {
		$this->db = Database::factory();

		while ($this->db->next_database()) {
			try {
				$this->check_migration_tables();
				$this->migrate($migrate_to, $simulate);
			} catch (Exception $e) {
				echo "\nERROR: ", $e->getMessage(), "\n\n";
			}
		}
	}

	public static function check_version_min($version) {
		// If our version is less than the requested, throw an exception.
		if (version_compare(self::VERSION, $version, '<')) {
			throw new Exception(sprintf(
				'Failed version check. Required %s, but using %s.',
				$version,
				self::VERSION
			));
		}
	}

	protected function get_migrations_table() {
		static $table_name;

		if (! (bool) $table_name) {
			// Get the migrations table name from the config.
			$table_name = Config::item('database.migrations_table', 'migrations');
		}

		return $table_name;
	}

	/**
	 * Check that the migrations table exists.
	 */
	protected function check_migration_tables() {
		// Check the migrations table exists
		$table_query = $this->db->query(
			'SHOW TABLES LIKE \''.$this->get_migrations_table().'\'',
			FALSE
		);
		
		if (! (bool) mysql_num_rows($table_query)) {
			$this->db->query(
				'CREATE TABLE `'.$this->get_migrations_table().'` ('
				.'`migration` int(11) NOT NULL default \'0\', '
				.'`applied` datetime NOT NULL default \'0000-00-00 00:00:00\', '
				.'UNIQUE KEY `migration` (`migration`)'
				.') TYPE=MyISAM', FALSE
			);
		}
	}

	protected function get_current_migration() {
		// Find what the maximum migration is...
		$migration_query = $this->db->query(
			'SELECT MAX(`migration`) AS `migration` FROM `'
			.$this->get_migrations_table().'`',
			FALSE
		);
		$migration_result = mysql_fetch_object($migration_query);
		return (int) $migration_result->migration;
	}

	/**
	 * Find all migrations that haven't been applied and run them.
	 */
	public function migrate($migrate_to, $simulate = FALSE) {
		$current_migration = $this->get_current_migration();

		if ($migrate_to == $current_migration)
			throw new Exception('Already at migration '.$migrate_to);

		if ($migrate_to < $current_migration) {
			$method = 'down';
			$sort = 'rsort';
		} else {
			$method = 'up';
			$sort = 'sort';
		};

		$migration_rows = self::select(
			'SELECT `migration` from `'.$this->get_migrations_table()
			.'` ORDER BY `migration`',
			'migration'
		);
		$migration_files = glob(APPPATH.'migrations/*.php');

		if ($migrate_to == 99999)
			$migrate_to = 'latest';

		echo "\n", ucfirst($method), sprintf('grading `%s` from %d to %s', $this->db->name, $current_migration, $migrate_to), "\n";

		// Sort the items so to run them in order.
		$sort($migration_files);

		$this->db->show_sql = TRUE;

		foreach ($migration_files as $file_path) {
			$file_name = basename($file_path);
			list($migration_id, $migration_name) = explode('-', $file_name, 2);

			// Ignore invalid or 0 ids.
			if ((int) $migration_id === 0)
				continue; 

			// Don't run ones that we've not been told to...
			if ($method == 'up' AND ($migration_id > $migrate_to))
				continue;
			elseif ($method == 'down' AND (($migration_id <= $migrate_to) OR ($migration_id > $current_migration)))
				continue;

			// Skip migrations when upgrading that are already applied.
			if ($method == 'up' AND in_array((int) $migration_id, $migration_rows))
				continue;

			// Skip migrations when downgrading that were not previously applied to the db.
			if ($method == 'down' AND ! in_array((int) $migration_id, $migration_rows))
				continue;

			// Translate filename to classname.
			$migration_name = implode('_', array_map('ucfirst', explode('_', strtolower(substr($migration_name, 0, -4)))));
			$migration_name = $migration_name.'_Migration_'.$migration_id;

			if ($simulate === TRUE)
				echo '(simulated) ';
				
			echo "\t", $migration_name, '->', $method, "\n";

			sql::reset_defaults();

			try {
				$prefixed_method = '_'.$method;
				require_once($file_path);
				$mig = new $migration_name($this->db);
				if (! (bool) $simulate) {
					$mig->$prefixed_method();

					// Run the test method if there is one and we're meant to.
					global $params;
					if ($method === 'up' AND (bool) $params['run-tests'] AND method_exists($mig, 'test')) {
						$mig->test();
					}
				}
				unset($mig);

				// Either the migration succeeded, or we're in simulate mode.
				if ($method == 'up') {
					$this->db->query(sprintf(
						'INSERT INTO `%s` SET `migration`=%d, `applied`=NOW()',
						$this->get_migrations_table(),
						$migration_id
					));
				} else {
					$this->db->query(sprintf(
						'DELETE FROM `%s` WHERE `migration`=%d',
						$this->get_migrations_table(),
						$migration_id
					));
				}
			} catch (Exception $e) {
				echo "\n\tERROR: ", $e->getMessage(), "\n";
			}
		}
	}


	public static function select($sql, $field = FALSE, $value = FALSE) {
		$res = Database::factory()->query($sql);
		
		if ($res === TRUE)
			throw new Exception('Invalid query for select: '.$sql);

		$rows = array();

		if ((bool) $field AND $value === FALSE) {
			// Single-value indexed array
			while ($row = mysql_fetch_object($res))
				$rows[] = $row->$field;
			return $rows;
		} elseif ((bool) $field AND (bool) $value) {
			// name => value pairing
			while ($row = mysql_fetch_object($res))
				$rows[$row->$field] = $row->$value;
			return $rows;
		} elseif (! (bool) $field AND (bool) $value) {
			// value => row pairing (id => object)
			while ($row = mysql_fetch_object($res))
				$rows[$row->$value] = $row;
			return $rows;
		} else {
			// Straight array.
			while ($row = mysql_fetch_object($res))
				$rows[] = $row;
			return $rows;
		}
	}
}
