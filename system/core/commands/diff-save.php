<?php

/**
 * Simple database diffing.
 * This command simply saves the current state.
 */

$db = LadderDB::factory();

function data_diff($table_name) {
	$diff_data = Config::item('diff.diff-data');

	// If tables are set globally, return that.
	if (is_bool($diff_data)) {
		return $diff_data;
	}

	// Check it's an array?
	if (is_array($diff_data)) {
		if (array_key_exists($table_name, $diff_data)) {
			// If the table is specified, return that.
			return (bool) $diff_data[$table_name];
		} elseif (array_key_exists('*', $diff_data)) {
			// Attempt to fall back to the '*' key.
			return (bool) $diff_data['*'];
		} else {
			// Can't find anything, so assume FALSE.
			return FALSE;
		}
	}

	// If we get here, assume no data diffing.
	return FALSE;
}

while ($db->next_database()) {
	$cache = LocalCache::factory($db->name);

	$table_info = $cache->get();
	
	if ((bool) $table_info AND ! (bool) $params['force']) {
		echo sprintf('There is already saved table info for database %s. Use --force to overwrite.', $db->name), PHP_EOL;
		continue;
	}

	// Throw away any data that was previously saved.
	$cache->clear();

	// Don't store any state for these tables.
	$system_tables = array(
		Config::item('database.migrations_table', 'migrations'),
		Config::item('database.kvdata_table', 'migrations_kvdata'),
		Config::item('database.kvdata_table', 'migrations_kvdata') . '_old',
	);
	
	foreach ($db->get_tables() as $table_name) {
		// Skip system tables.
		if (in_array($table_name, $system_tables)) {
			continue;
		}
		
		// Initialise the current info to empty.
		$info = array();
		
		// Get a Table instance for the current table.
		$table = Table::factory($table_name, TRUE);
		
		// Get its columns, indexes, and row data.
		$info['columns'] = $table->get_columns();
		$info['indexes'] = $table->get_indexes();
		
		/**
		 * Get the actual data (!) - very experimental, and only supported
		 * on tables with a single-column PRIMARY KEY.
		 */
		if (data_diff($table_name)) {
			$info['data'] = $table->select_primary();
		}
		
		// Store into the cache.
		$cache->set('table_'.$table_name, $info);
	}
	
	// Make sure we save once we're finished.
	$cache->save();
}
