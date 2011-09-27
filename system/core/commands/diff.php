<?php

/**
 * Simple database diffing, using data saved by the diff-save command.
 */

$db = LadderDB::factory();
$ignore_tables = Config::item('diff.ignore-tables', array());

while ($db->next_database()) {
	$cache = LocalCache::factory($db->name);

	if (! (bool) $cache->get()) {
		echo 'There is no saved table info to compare with. Please run diff-save first.', PHP_EOL;
	} else {
		$old_tables = array();
		foreach ($cache->get() as $key => $value) {
			if (substr($key, 0, 6) == 'table_') {
				$old_tables[substr($key, 6)] = $value;
			}
		}
		
		$new_tables = array_diff($db->get_tables(), array_keys($old_tables));
		
		if ((bool) $new_tables) {
			foreach ($new_tables as $table_name) {
				if (in_array($table_name, $ignore_tables)) {
					continue;
				}
				
				echo "\t", sprintf(
					'$this->create_table(\'%s\')',
					$table_name
				), PHP_EOL;
				
				$table = Table::factory($table_name);
				
				$primary_columns = $table->primary_columns();
				$standard_primary_key = (
					(count($primary_columns) == 1) AND
					($primary_columns[0] == 'id')
				);
				
				foreach ($table->get_columns() as $column => $info) {
					if (($column == 'id') AND $standard_primary_key) {
						continue;
					}
					
					echo "\t\t", parse_field_info($info), PHP_EOL;
				}
				
				foreach ($table->get_indexes() as $index => $info) {
					if (($index == 'PRIMARY') AND $standard_primary_key) {
						continue;
					}
					
					$unique = FALSE;
					$index_fields = array();
					foreach ($info as $index_info) {
						$index_fields[] = $index_info->Column_name;
						$unique = (($index_info->Non_unique == '0') AND ($index != 'PRIMARY'));
					}
					echo "\t\t", sprintf(
						'->index(\'%s\', array(\'%s\')%s)',
						$index, implode('\', \'', $index_fields),
						$unique ? ', array(\'unique\' => TRUE)' : FALSE
					), PHP_EOL;
				}
				
				echo "\t;", PHP_EOL, PHP_EOL;
			}
		}
		
		foreach ((array) $old_tables as $table_name => $info) {
			if (in_array($table_name, $ignore_tables)) {
				continue;
			}
			
			// Get the info out of the array.
			$prev_columns = $info['columns'];
			$prev_indexes = $info['indexes'];
			$prev_data = array_key_exists('data', $info)
				? $info['data']
				: NULL
			;
			
			// Get current info.
			if (! Table::exists($table_name)) {
				echo "\t", sprintf(
					'$this->table(\'%s\')->drop();',
					$table_name
				), PHP_EOL;
				continue;
			}

			$current_table = Table::factory($table_name, TRUE);
			$current_columns = $current_table->get_columns();
			$current_indexes = $current_table->get_indexes();
			$current_data = $prev_data === NULL
				? NULL
				: $current_table->select_primary()
			;
			
			// Compare table info.
			$new_columns = array_diff(array_keys($current_columns), array_keys($prev_columns));
			$missing_columns = array_diff(array_keys($prev_columns), array_keys($current_columns));

			$diff_columns = array();
			foreach (array_intersect(array_keys($current_columns), array_keys($prev_columns)) as $check_column) {
				$prev_info = $prev_columns[$check_column];
				$curr_info = $current_columns[$check_column];

				foreach ($prev_info as $info_key => $info_val) {
					if ($curr_info->$info_key != $info_val) {
						// We found column differing info, so make a note of the column name.
						$diff_columns[] = $check_column;
						break;
					}
				}
			}
			
			$new_indexes = array_diff(array_keys($current_indexes), array_keys($prev_indexes));
			$missing_indexes = array_diff(array_keys($prev_indexes), array_keys($current_indexes));
			
			if ($prev_data === NULL) {
				$new_rows = array();
				$missing_rows = array();
				$diff_rows = array();
			} else {
				$new_rows = array_diff(array_keys($current_data), array_keys($prev_data));
				$missing_rows = array_diff(array_keys($prev_data), array_keys($current_data));

				$diff_rows = array();
				foreach (array_intersect(array_keys($current_data), array_keys($prev_data)) as $check_data) {
					$prev_info = $prev_data[$check_data];
					$curr_info = $current_data[$check_data];

					foreach ($prev_info as $info_key => $info_val) {
						$diff_row_columns = array();
						if ($info_val != $curr_info[$info_key]) {
							$diff_row_columns[$info_key] = $curr_info[$info_key];
						}
						if ((bool) $diff_row_columns) {
							$diff_rows[$check_data] = $diff_row_columns;
						}
					}
				}
			}
			
			if (
				(bool) $new_columns OR
				(bool) $missing_columns OR
				(bool) $diff_columns OR
				(bool) $new_indexes OR
				(bool) $missing_indexes OR
				(bool) $new_rows OR
				(bool) $missing_rows OR
				(bool) $diff_rows
			) {
				echo "\t", '$this->table(\'', $table_name, '\')', PHP_EOL;
				
				if ((bool) $missing_columns) {
					echo "\t\t", '// Removed Columns', PHP_EOL;
					foreach ($missing_columns as $column) {
						echo "\t\t", sprintf('->drop_column(\'%s\')', $column), PHP_EOL;
					}
				}
				
				if ((bool) $new_columns) {
					$column_names = array_keys($current_columns);

					echo "\t\t", '// New Columns', PHP_EOL;
					foreach ($new_columns as $column) {
						// Find the index of this column within the table.
						$idx = array_search($column, $column_names);

						if ($idx === FALSE) {
							// We can't find the column - very weird!
							throw new Exception(sprintf('Failed to locate current column \'%s\'.', $column));
						}

						if ($idx === 0) {
							// 0 means it's the first column, so pass FALSE (it's not after anything).
							$after = FALSE;
						} elseif ($idx < count($current_columns) - 1) {
							// Pass the previous column name.
							$after = $column_names[$idx - 1];
						} else {
							// It's the last column in the table, so pass NULL.
							$after = NULL;
						}

						echo "\t\t", parse_field_info($current_columns[$column], FALSE, $after), PHP_EOL;
					}
				}

				if ((bool) $diff_columns) {
					echo "\t\t", '// Altered Columns', PHP_EOL;
					foreach ($diff_columns as $column) {
						echo "\t\t", parse_field_info($current_columns[$column], TRUE), PHP_EOL;
					}
				}
				
				if ((bool) $missing_indexes) {
					echo "\t\t", '// Removed Indexes', PHP_EOL;
					foreach ($missing_indexes as $index) {
						echo "\t\t", sprintf('->drop_index(\'%s\')', $index), PHP_EOL;
					}
				}
				
				if ((bool) $new_indexes) {
					echo "\t\t", '// New Indexes', PHP_EOL;
					foreach ($new_indexes as $index) {
						$unique = FALSE;
						$index_fields = array();
						foreach ($current_indexes[$index] as $index_info) {
							$index_fields[] = $index_info->Column_name;
							$unique = $index_info->Non_unique == '0';
						}
						echo "\t\t", sprintf(
							'->index(\'%s\', array(\'%s\')%s)',
							$index, implode('\', \'', $index_fields),
							$unique ? ', array(\'unique\' => TRUE)' : FALSE
						), PHP_EOL;
					}
				}
				
				if ((bool) $missing_rows OR (bool) $new_rows OR (bool) $diff_rows) {
					$primary_columns = $current_table->primary_columns();
					
					if (count($primary_columns) == 1) {
						$primary_column = $primary_columns[0];
				
						if ((bool) $missing_rows) {
							echo "\t\t", '// Removed Rows', PHP_EOL;
							foreach ($missing_rows as $key_value) {
								echo "\t\t", sprintf(
									'->delete(array(\'%s\' => %s))',
									$primary_column, sql::escape($key_value)
								), PHP_EOL;
							}
						}
				
						if ((bool) $new_rows) {
							echo "\t\t", '// New Rows', PHP_EOL;
							foreach ($new_rows as $key_value) {
								$data = $current_data[$key_value];
								$data_array = array();
								foreach ($data as $field => $value) {
									$data_array[] = sprintf(
										'\'%s\' => %s',
										$field, sql::escape($value)
									);
								}
								echo "\t\t", sprintf(
									'->insert(array(%s))',
									implode(', ', $data_array)
								), PHP_EOL;
							}
						}

						if ((bool) $diff_rows) {
							echo "\t\t", '// Altered Rows', PHP_EOL;
							foreach ($diff_rows as $primary_val => $changed_columns) {
								require(LADDER_SYSPATH.'templates/altered_rows.php');
							}
						}
					}
				}
				
				echo "\t;", PHP_EOL, PHP_EOL;
			}
		}
	}
}

function parse_field_info($field_info, $alter = FALSE, $after = NULL) {
	$parser = FieldParser::factory($field_info);
	
	// Build up the options string.
	$options = 'array(';
	
	if ((bool) $parser->limit) {
		if (strpos($parser->limit, ',') === FALSE) {
			$options .= sprintf('\'limit\' => %d, ', (int) $parser->limit);
		} else {
			$options .= sprintf('\'limit\' => \'%s\', ', $parser->limit);
		}
	}
	
	$options .= sprintf('\'null\' => %s, ', $parser->null ? 'TRUE' : 'FALSE');
	$options .= sprintf('\'default\' => %s, ', sql::escape($parser->default));

	if ((bool) $after) {
		$options .= sprintf('\'after\' => \'%s\', ', $after);
	} elseif ($after === FALSE) {
		$options .= '\'first\' => TRUE, ';
	}

	if ((bool) $parser->enum_options) {
		$options .= sprintf('\'options\' => array(%s), ', implode(', ', array_map('sql::escape', $parser->enum_options)));
	}
	
	// Trim the trailing comma-space, close the array.
	$options = substr($options, 0, -2).')';
	
	// Output the migration code.
	return sprintf(
		'->%scolumn(\'%s\', \'%s\', %s)',
		(bool) $alter ? 'alter_' : FALSE,
		$parser->name, strtolower($parser->type), $options
	);
}


class FieldParser {
	public static function factory($info) {
		return new FieldParser($info);
	}

	protected $info;
	protected $name;
	protected $type;
	protected $limit;
	protected $null;
	protected $default;
	protected $enum_options;

	public function __construct($info) {
		$this->info = (array) $info;
		$this->parse();
	}

	public function __destruct() {
	}

	public function __get($key) {
		static $safe_list = array('name', 'type', 'limit', 'null', 'default', 'enum_options');
		if (in_array($key, $safe_list)) {
			return $this->$key;
		}
	}

	protected function parse() {
		$type = NULL;

		foreach ($this->info as $k => $v) {
			switch ($k) {
				case 'Field':
					$this->name = $v;
					break;

				case 'Type':
					$type = $v;
					break;

				case 'Null':
					$this->null = ($v == 'YES');
					break;

				case 'Default':
					$this->default = $v;
					break;
			}
		}

		// Post-process the type.
		if ((bool) preg_match('/([a-z]+)\(([\d,]+)\)/i', $type, $matches)) {
			$this->type = $matches[1];
			$this->limit = $matches[2];
			$this->enum_options = array();
		} elseif ((bool) preg_match('/(enum)\((.*?)\)/i', $type, $matches)) {
			$this->type = $matches[1];
			$this->limit = NULL;
			$this->parse_enum_options($matches[2]);
		}

		// Map short-hand types to their long equivalent.
		if ($this->type == 'int') {
			$this->type = 'integer';
		}

		// Check the parsed type exists in the SQL helper.
		if (! array_key_exists($this->type, sql::get_default())) {
			throw new Exception('Unknown field type: ' . $this->type);
		}
	}

	protected function parse_enum_options($options) {
		// 1 = outside quotes
		// 2 = inside quotes
		$state = 1;

		// Current option (built on each iteration).
		$current = '';

		// Grab the length once, not on every iteration.
		$len = strlen($options);

		// Reset the ivar.
		$this->enum_options = array();

		for ($i = 0; $i <= $len; ++$i) {
			if ($i < $len) {
				// Grab the character at the specified index.
				$c = $options[$i];
			} else {
				// We've gone past the end, mark with a NULL.
				$c = NULL;
			}

			if ($c == '\'') {
				// Flip the state when we encounter a quote.
				$state = $state == 1 ? 2 : 1;
				continue;
			}

			if ((($c == ',') OR ($c === NULL)) AND ($state == 1)) {
				// If there's a comma (or we reached the end), and we're outside of quotes, we have a value.
				$this->enum_options[] = $current;
				$current = '';
			} elseif ($state == 2) {
				// We're inside quotes, so append the character.
				$current .= $c;
			}
		}
	}
}
