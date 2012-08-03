<?php

class Table {
	private $name;
	private $columns;
	private $indexes;
	private $triggers;
	private $constraints;
	private $created;
	private $table_columns;
	private $table_indexes;
	private $options;
	private $insert_id;

	public static function factory($name, $created = NULL, $options = NULL) {
		return new Table($name, $created, $options);
	}

	public static function exists($name) {
		$db = LadderDB::factory();
		$res = $db->query(sprintf(
			'SHOW TABLES LIKE \'%s\'', $db->escape_value($name)
		));
		return mysql_num_rows($res) == 1;
	}

	public function __construct($name, $created = NULL, $options = NULL) {
		$this->name = $name;
		
		if ($created === NULL) {
			// Detect if the table exists.
			$this->created = Table::exists($name);
		} else {
			// Use the passed value.
			$this->created = (bool) $created;
		}
		
		// Setup the instance.
		$this->clear();

		if (! (bool) $this->created) {
			// Add a default `id` column, make it autoincrement and primary key.
			$this->column('id', 'integer', array('null' => FALSE, 'autoincrement' => TRUE));
			$this->index('PRIMARY', 'id');
		}

		if ($options === NULL) {
			// If no options passed, then use the defaults.
			$options = Config::item('table');
		}

		$this->options = $options;
	}

	public function __get($key) {
		if ($key === 'name') {
			return $this->name;
		} elseif ($key === 'insert_id') {
			return $this->insert_id;
		}
	}

	/**
	 * Fetch column information from the database.
	 * @return array
	 */
	public function get_columns($force_refresh = FALSE) {
		if (! (bool) $force_refresh AND (bool) $this->table_columns) {
			return $this->table_columns;
		}

		$db = LadderDB::factory();
		$cols = array();
		$field_query = $db->query(sprintf('SHOW FULL COLUMNS FROM `%s`', $this->name));
		while ($field_row = mysql_fetch_object($field_query)) {
			$cols[$field_row->Field] = $field_row;
		}
		return $this->table_columns = $cols;
	}
	
	/**
	 * Get index information from the database.
	 * @return array
	 */
	public function get_indexes($force_refresh = FALSE) {
		if (! (bool) $force_refresh AND (bool) $this->table_indexes) {
			return $this->table_indexes;
		}
		
		$db = LadderDB::factory();
		$index_query = $db->query(sprintf('SHOW INDEXES FROM `%s`', $this->name));
		
		$indexes = array();
		while ($index_row = mysql_fetch_object($index_query)) {
			if (! array_key_exists($index_row->Key_name, $indexes)) {
				$indexes[$index_row->Key_name] = array();
			}
			$indexes[$index_row->Key_name][] = $index_row;
		}
		
		return $this->table_indexes = $indexes;
	}

	/**
	 * Remove all pending SQL statements.
	 * @return NULL
	 */
	public function clear() {
		$this->columns = $this->indexes = $this->triggers = $this->constraints = array(
			'add' => array(),
			'drop' => array(),
		);
		$this->columns['alter'] = array();
	}

	/**
	 * Automatically add the `created` and `modified` fields.
	 * @return Table
	 */
	public function timestamps() {
		return $this->column('created', 'datetime', array('null' => FALSE))
			->column('modified', 'datetime');
	}
	
	/**
	 * Add a column to this Table instance.
	 * @return Table
	 * @param string $name Name of the column to add.
	 * @param string $type Column type to add.
	 * @param array $options[optional] Options such as unique, null.
	 */
	public function column($name, $type, $options = array()) {
		$this->columns['add'][$name] = array($name, $type, $options);
		return $this;
	}

	/**
	 * Alter a column in this Table instance.
	 * @return Table
	 * @param string $name Name of the column to alter.
	 * @param string $type Column type to change to.
	 * @param array $options[optional] Options such as unique, null, limit etc.
	 */
	public function alter_column($name, $type, $options = array()) {
		$this->columns['alter'][$name] = array($name, $type, $options);
		return $this;
	}

	/**
	 * Drop a column from this Table instance.
	 * @return Table
	 * @param string $name The name of the column to drop.
	 */
	public function drop_column($name) {
		$this->columns['drop'][$name] = $name;
		return $this;
	}

	/**
	 * Add an index to this Table instance.
	 * @return Table
	 * @param string $name Name of index to add. If this is the only parameter, the column to index.
	 * @param array $columns[optional] The columns to use in the index.
	 * @param array $options[optional] Any options, such as primary, unique.
	 */
	public function index($name, $columns = array(), $options = array()) {
		// Allow them to alter the PRIMARY key.
		if ((arr::val($options, 'primary') === TRUE) OR ($name == 'primary')) {
			$name = 'PRIMARY';

			// Remove the default PRIMARY column if we're building a table.
			if (! $this->created AND array_key_exists('id', $this->columns['add']))
				unset($this->columns['add']['id']);
		}

		$this->indexes['add'][$name] = array($name, $columns, $options);
		return $this;
	}

	/**
	 * Drop an index from this Table instance.
	 * @return Table
	 * @param string $name The name of the index to drop.
	 */
	public function drop_index($name) {
		$this->indexes['drop'][$name] = $name;
		return $this;
	}

	/**
	 * Create a trigger on this Table instance.
	 * @return Table
	 * @param string $when Either 'before' or 'after'.
	 * @param string $event Any of 'insert', 'update' or 'delete'.
	 * @param string $sql The statements to execute on the trigger.
	 */
	public function trigger($when, $event, $sql) {
		$when = strtolower($when);
		$event = strtolower($event);
		$name = sprintf('%s_%s_%s_tr', $this->name, $when, substr($event, 0, 3));
		$this->triggers['add'][$name] = array($name, $when, $event, $this->name, $sql);
		return $this;
	}

	/**
	 * Drop a trigger from this Table instance.
	 * @return Table
	 * @param string $name The name of the trigger to drop.
	 */
	public function drop_trigger($when, $event) {
		$when = strtolower($when);
		$event = strtolower($event);
		$name = sprintf('%s_%s_%s_tr', $this->name, $when, substr($event, 0, 3));
		$this->triggers['drop'][$name] = $name;
		return $this;
	}

	/**
	 * Create a constraint on this Table instance.
	 * @return Table
	 * @param string $column The column in this table to constrain.
	 * @param string $reference_table The table to refer to.
	 * @param string|array $reference_fields Fields in $reference_table to constrain to.
	 * @param string|array $cascade[optional] Which actions to cascade.
	 * @param string $name[optional] Name the constraint, or omit for automatic naming.
	 */
	public function constraint($column, $reference_table, $reference_fields, $cascade = NULL, $name = NULL) {
		// Fix up any string/array params first.
		$reference_fields = (array) $reference_fields;
		$cascade = (array) $cascade;

		if (! (bool) $name) {
			// Work out a name for the constraint.
			$name = sprintf('%s_%s_%s', $this->name, $reference_table, implode('_', $reference_fields));
		}

		// Remember its details for later.
		$this->constraints['add'][$name] = array(
			$name, $column, $reference_table, $reference_fields, $cascade
		);

		return $this;
	}

	/**
	 * Drop a constraint from this Table instance.
	 * @return Table
	 * @param string $index The index in this table the constraint uses.
	 * @param string $reference_table The table it refers to.
	 * @param string|array $reference_fields The fields it refers to.
	 */
	public function drop_constraint($index, $reference_table, $reference_fields) {
		// Allow a special case where you need to specify the exact name.
		if (($reference_table === NULL) AND ($reference_fields === NULL)) {
			$this->constraints['drop'][$index] = $index;
			return $this;
		}

		// Fix up any string/array params first.
		$reference_fields = (array) $reference_fields;

		// Work out a name for the constraint.
		$name = sprintf('%s_%s_%s', $this->name, $reference_table, implode('_', $reference_fields));

		// Remember it for later.
		$this->constraints['drop'][$name] = $name;

		return $this;
	}

	/**
	 * Perform all outstanding SQL statements.
	 * @return NULL
	 */
	public function execute() {
		// Initialise to false, we need to see if there's work to do.
		$todo = FALSE;

		// Check the columns array first.
		$check_keys = array('add', 'alter', 'drop');
		foreach ($check_keys as $key) {
			// If there's something to do, set the flag and break.
			if ((bool) $this->columns[$key]) {
				$todo = TRUE;
				break;
			}
		}

		// If there's still nothing to do, check the indexes.
		if (! $todo) {
			$check_keys = array('add', 'drop');
			foreach ($check_keys as $key) {
				if ((bool) $this->indexes[$key]) {
					$todo = TRUE;
					break;
				}
			}
		}

		// Still nothing? Look at constraints.
		if (! $todo) {
			$check_keys = array('add', 'drop');
			foreach ($check_keys as $key) {
				if ((bool) $this->constraints[$key]) {
					$todo = TRUE;
					break;
				}
			}
		}

		// Still nothing? Look at triggers.
		if (! $todo) {
			$check_keys = array('add', 'drop');
			foreach ($check_keys as $key) {
				if ((bool) $this->triggers[$key]) {
					$todo = TRUE;
					break;
				}
			}
		}

		// If nothing to do, no need to execute any SQL!
		if (! $todo)
			return FALSE;

		try {
			if (! $this->created) {
				sql::add_table(
					$this->name, $this->columns['add'],
					$this->indexes['add'], $this->triggers['add'],
					$this->constraints['add'], $this->options
				);

				$this->created = TRUE;
			} else {
				sql::alter(
					$this->name, $this->columns,
					$this->indexes, $this->triggers,
					$this->constraints, $this->options
				);
			}
		} catch (Exception $e) {
			$this->clear();
			throw $e;
		}

		$this->clear();

		return $this;
	}
	
	public function data($migration) {
		$this->execute(); // Ensure the table exists, and has the right fields...
		
		if ($migration === FALSE)
			return;
		
		require_once(sprintf('migrations/data/%s-%s.php',
			end(explode('_', get_class($migration))), strtolower($this->name)));
	}

	public function select($fields = NULL, $where = NULL, $fetch = 'assoc') {
		// Escape fields, or use * by default.
		if (! is_null($fields)) {
			$fields = '`'.implode('`, `', (array) $fields).'`';
		} else {
			$fields = '*';
		}

		if ((bool) $where) {
			// Use field => value pairs where available.
			if (is_array($where)) {
				$clauses = array();

				foreach ($where as $field => $value) {
					$clause = sql::escape($field, '`');
					$clause .= ' = ';
					$clause .= sql::escape($value);
					$clauses[] = $clause;
				}

				// Compile into a string.
				$where = 'WHERE '.implode(' AND ', $clauses);
			}
		}

		// Run the query.
		$query = LadderDB::factory()->query(sprintf(
			'SELECT %s FROM `%s` %s',
			$fields, $this->name, $where
		));

		// Build the function name to use.
		$func = 'mysql_fetch_'.$fetch;

		// Loop over the result set, saving to an array.
		$result = array();
		while ((bool) $row = $func($query)) {
			$result[] = $row;
		}

		// Free the result set.
		mysql_free_result($query);

		// Return the rows!
		return $result;
	}
	
	/**
	 * Return an array containing the columns from the primary key.
	 * @return array
	 */
	public function primary_columns() {
		$indexes = $this->get_indexes();
		
		$result = array();
		
		if (array_key_exists('PRIMARY', $indexes)) {
			foreach ($indexes['PRIMARY'] as $column) {
				$result[] = $column->Column_name;
			}
		}
		
		return $result;
	}
	
	/**
	 * Return an associative array of table data, using the primary key as
	 * the array keys.
	 * @return array
	 * @since 0.7.0
	 */
	public function select_primary() {
		$primary_columns = $this->primary_columns();
		
		if (count($primary_columns) != 1) {
			// We can't find a primary key, or it has more than 1 column, so return an empty array.
			return array();
		}
		
		$primary_column = $primary_columns[0];
		$data = $this->select();
		
		$result = array();
		foreach ($data as $row) {
			$result[$row[$primary_column]] = $row;
		}
		
		return $result;
	}
	
	public function insert($data, $extra = '') {
		$this->execute();
		sql::insert($this->name, $data, $extra);
		$this->insert_id = sql::insert_id();
		return $this;
	}
	
	public function update($data, $where) {
		$this->execute();
		sql::update($this->name, $data, $where);
		return $this;
	}
	
	public function delete($where) {
		$this->execute();
		sql::delete($this->name, $where);
		return $this;
	}

	public function truncate() {
		$this->execute();
		sql::truncate($this->name);
		return $this;
	}

	public function drop() {
		$this->execute();
		sql::drop_table($this->name);
	}

	public function rename($new_name) {
		sql::rename_table($this->name, $new_name);
		$this->name = $new_name;
		return $this;
	}

	/**
	 * Load a CSV file and return its data as an associative array.
	 * @param string $path Path to the CSV file.
	 * @since 0.4.11
	 */
	public function get_csv_data($path) {
		// Fail if the file doesn't exist.
		if (! file_exists($path)) {
			throw new Exception('Cannot find file: '.$path);
		}

		$csv = fopen($path, 'r');

		// Always assume the 1st row is the field names.
		$headers = fgetcsv($csv);

		// Initialise the result array.
		$result = array();

		// Loop over the file and add array elements.
		while (! feof($csv)) {
			// Skip errors, and blink lines. Returned as a single null element array.
			if (! (bool) $row = fgetcsv($csv) OR $row == array(NULL)) {
				continue;
			}

			// Change any strings of 'null' into actual NULL values.
			foreach ($row as &$cell) {
				if (strtolower($cell) == 'null') {
					$cell = NULL;
				}
			}

			// Make sure the number of elements matches by padding the end with NULL.
			while (count($row) < count($headers)) {
				$row[] = NULL;
			}

			// Combine the data into an associative array and add to result.
			$result[] = array_combine($headers, $row);
		}

		// Close the file and return the data.
		fclose($csv);
		return $result;
	}

	/**
	 * Import a CSV file into the table, assuming the 1st row contains field
	 * names, and the rest is data. Blank lines are skipped.
	 * If you need multiple lines, or embedded quotes, you *must* enclose the
	 * value in double quotes ("), and double-up the quotes inside, like
	 * Microsoft Excel. The PHP documentation contradicts this, but testing
	 * shows that to be incorrect. e.g. "<a href=""page.html"">page</a>"
	 * @param $path string Path to the file to import.
	 * @param $use_update[optional] boolean Should the import use an UPDATE query.
	 * @param $key_fields[optional] array Which fields to use in the WHERE
	 * clause to UPDATEs.
	 */
	public function import_csv($path, $use_update = FALSE, $key_fields = FALSE) {
		foreach ($this->get_csv_data($path) as $row) {
			if (! $use_update) {
				$this->insert($row);
			} else {
				// Get the key fields as array keys.
				$where = array_fill_keys((array) $key_fields, FALSE);

				// Loop through the key fields and set their values.
				foreach ($where as $key => &$value) {
					// Grab the value.
					$value = $row[$key];

					// We don't want to update the field we match on!
					unset($row[$key]);
				}

				// Run the update!
				$this->update(
					$row,
					$where
				);
			}
		}

		return $this;
	}

	/**
	 * Unimport a CSV file, deleting data that exists and matches the CSV.
	 * @param string $path Path to the csv file to unimport.
	 * @param mixed $key_fields FALSE to use all fields, or an array of fields
	 * to use in the WHERE clause. @since 0.4.12
	 * @since 0.4.11
	 */
	public function unimport_csv($path, $key_fields = FALSE) {
		foreach ($this->get_csv_data($path) as $row) {
			if ($key_fields === FALSE) {
				// If no key fields specified, use all of them.
				$where = $row;
			} else {
				// Loop over the fields specified, fetching the values.
				$where = array();
				foreach ($key_fields as $field_name) {
					$where[$field_name] = array_key_exists($field_name, $row)
						? $row[$field_name]
						: NULL
					;
				}
			}

			// Delete the data as specified.
			$this->delete($where);
		}

		return $this;
	}

	/**
	 * Grant permissions to users for this table.
	 * @param mixed $permissions String or array of strings to grant.
	 * @param mixed $users String or array of user@host strings.
	 * @since 0.4.13
	 */
	public function grant($permissions, $users) {
		// Make sure any outstanding changes are performed first.
		$this->execute();

		// Get a LadderDB instance.
		$db = LadderDB::factory();

		// Get a grant manager instance.
		$grant = Grant_Manager::instance();

		// Loop over each user and execute the grant manager.
		foreach ((array) $users as $user) {
			$grant->grant($privileges, $object, $user);
		}

		return $this;
	}
	
	/**
	 * Return the row count of the table this instance represents.
	 * @return int
	 * @since 0.7.0
	 */
	public function row_count() {
		// Make sure there are no pending updates.
		$this->execute();
		
		// Run the SQL.
		$db = LadderDB::factory();
		$query = $db->query(sprintf(
			'SELECT COUNT(*) FROM %s',
			sql::escape_identifier($this->name)
		));
		
		// Get the value and return.
		$row = mysql_fetch_row($query);
		return (int) $row[0];
	}
}
