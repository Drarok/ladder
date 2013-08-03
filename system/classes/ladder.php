<?php

final class Ladder {
	protected $db;
	protected $options;

	public static $show_sql = FALSE;

	public function __construct($migrate_to, $simulate = FALSE) {
		$this->db = LadderDB::factory();

		while ($this->db->next_database()) {
			try {
				$this->migrate($migrate_to, $simulate);
			} catch (Exception $e) {
				echo "\nERROR: ", $e->getMessage(), "\n\n";
			}
		}
	}

	/**
	 * Check the version is at least the passed-in one.
	 * @param $version string Minimum version number required.
	 */
	public static function check_version_min($version) {
		// If our version is less than the requested, throw an exception.
		if (version_compare(LADDER_VERSION, $version, '<')) {
			throw new Exception(sprintf(
				'Failed version check. Required %s, but using %s.',
				$version,
				LADDER_VERSION
			));
		}
	}

	/**
	 * Find all migrations that haven't been applied and run them.
	 */
	public function migrate($migrate_to, $simulate = FALSE) {
		$current_migration = $this->db->get_current_migration();

		$migrate_target = $migrate_to;
		if ($migrate_to == 'latest') {
			$migrate_to = 2147483647; // 32-bit safe, and timestamp-safe until 2038.
		}

		if ($migrate_to == $current_migration) {
			throw new Exception('Already at migration '.$migrate_target);
		}

		if ($migrate_to < $current_migration) {
			$method = 'down';
			$sort = 'rsort';
		} else {
			$method = 'up';
			$sort = 'sort';
		};

		$migration_rows = self::select(
			sprintf(
				'SELECT `migration` from `%s` ORDER BY `migration`',
				$this->db->get_migrations_table()
			),
			'migration'
		);
		$migration_files = glob(LADDER_APPPATH.'migrations/*.php');

		echo "\n", ucfirst($method), sprintf('grading `%s` from %d to %s', $this->db->name, $current_migration, $migrate_target), "\n";

		// Sort the items so to run them in order.
		$sort($migration_files);

		$this->db->show_sql = TRUE;

		foreach ($migration_files as $file_path) {
			$file_name = basename($file_path);
			$migration_id = (int) $file_name;

			// Ignore invalid or 0 ids.
			if ((int) $migration_id === 0) {
				continue;
			}

			// Don't run ones that we've not been told to...
			if ($method == 'up' AND ($migration_id > $migrate_to)) {
				continue;
			} elseif ($method == 'down' AND (($migration_id <= $migrate_to) OR ($migration_id > $current_migration))) {
				continue;
			}

			// Skip migrations when upgrading that are already applied.
			if ($method == 'up' AND in_array((int) $migration_id, $migration_rows)) {
				continue;
			}

			// Skip migrations when downgrading that were not previously applied to the db.
			if ($method == 'down' AND ! in_array((int) $migration_id, $migration_rows)) {
				continue;
			}

			// Translate filename to classname.
			$migration_name = Migration::class_name($file_path);

			if ($simulate === TRUE) {
				echo '(simulated) ';
			}

			echo "\t", $migration_name, '->', $method, "\n";

			sql::reset_defaults();

			try {
				$prefixed_method = '_'.$method;
				require_once($file_path);
				$mig = new $migration_name($this->db);
				if (! (bool) $simulate) {
					$mig->$prefixed_method();

					// Run the test method if requested.
					global $params;
					if ($method === 'up' AND (bool) $params['run-tests']) {
						$mig->execute();
						$mig->test();
					}
				}
				unset($mig);

				// Either the migration succeeded, or we're in simulate mode.
				if ($method == 'up') {
					$this->db->add_migration($migration_id);
				} else {
					$this->db->remove_migration($migration_id);
				}
			} catch (Exception $e) {
				echo "\n\tERROR: ", $e->getMessage(), "\n";
			}
		}
	}


	public static function select($sql, $field = FALSE, $value = FALSE) {
		$res = LadderDB::factory()->query($sql);

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

	public static function error_handler($errno, $errstr, $errfile = NULL, $errline = NULL) {
		// Ignore errors that fall below the reporting threshold.
		if (! ($errno & ini_get('error_reporting'))) {
			return;
		}

		echo 'PHP Error: ', "\t", $errno, PHP_EOL;
		echo "\t\t", $errstr, PHP_EOL;

		debug_print_backtrace();

		/*
		if ((bool) $errfile) {
			echo "File:\t", $errfile, (bool) $errline ? ' ['.$errline.']' : FALSE, PHP_EOL;
		}
		*/
	}

	public static function exception_handler($exception) {
		echo sprintf(
			'Uncaught exception \'%s\' with message:', get_class($exception)
		), PHP_EOL;

		echo sprintf(
			"\t".'\'%s\' in %s [%s]',
			$exception->getMessage(), $exception->getFile(),
			$exception->getLine()
		), PHP_EOL;

		echo 'Stack Trace:', PHP_EOL;

		// Get the stack trace information.
		$trace = $exception->getTrace();
		$traceline = "\t".'#%s %s(%s): %s(%s)';
		foreach ($trace as $key => $stackPoint) {
			// Convert the arguments to their type.
			$stackPoint['args'] = array_map('gettype', $stackPoint['args']);

			echo sprintf(
				$traceline, $key, $stackPoint['file'],
				$stackPoint['line'],
				array_key_exists('class', $stackPoint)
					? $stackPoint['class'].'->'.$stackPoint['function']
					: $stackPoint['function'],
				implode(', ', $stackPoint['args'])
			), PHP_EOL;
		}
	}

	/**
	 * Build a path to a file or directory, prepended with the application path.
	 * @param mixed $* Variable number of parameters, appended together to
	 * build the final path.
	 * @return string A full path to a file/directory.
	 * @since 0.4.8
	 */
	public static function path() {
		// Get the arguments passed to the method.
		$params = func_get_args();

		// Prepend the application path.
		array_unshift($params, rtrim(LADDER_APPPATH, DS));

		return implode(DS, $params);
	}

	/**
	 * Get the contents of a file, using the Ladder::path() helper method
	 * to build the path.
	 * @param mixed $* Variable number of parameters, appended together to
	 * build the final path.
	 * @return string Contents of the file requested.
	 * @since 0.4.8
	 */
	public static function file() {
		// Get the params passed and pass through Ladder::path.
		$params = func_get_args();
		$path = call_user_func_array(array('Ladder', 'path'), $params);

		// Throw an exception if the file doesn't exist.
		if (! file_exists($path)) {
			throw new Exception('No such file at path: '.$path);
		}

		// Return the contents.
		return file_get_contents($path);
	}
}
