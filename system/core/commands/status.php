<?php

global $params;

// Grab all the migrations and sort them.
$migrations = glob(APPPATH.'migrations'.DS.'*.php');
sort($migrations);

// Initialise our second array.
$keyed_migrations = array();

// Loop over each migration, fetching info.
foreach ($migrations as $migration) {
	// Split the id and migration.
	list($id, $migration) = explode('-', basename($migration, EXT), 2);

	// Force it to be an integer (also remember for later).
	$latest_id = $id = (int) $id;

	// Store it in our second array.
	$latest_name = $keyed_migrations[$id] = $migration;
}

// Swap the second array into the first and destroy it.
$migrations = $keyed_migrations;
unset($keyed_migrations);

// Get the numeric part from the last one.
$ids = array_keys($migrations);

echo "Migration Status\n";
echo 'Latest migration is ', $latest_id;
if ($params['verbose']) {
	echo ' (', $latest_name, ')';
}
echo "\n";

$db = Database::factory();

while ($db->next_database()) {
	// Grab the latest migration.
	$max_migration = $db->get_current_migration();

	// Initialise our array of migrations to apply.
	$apply = array();

	// Work out the status identifier.
	if ($latest_id === $max_migration) {
		// This database is up-to-date.
		$status = '=';
	} elseif ($latest_id > $max_migration) {
		// This database is out-of-date.
		$status = '<';

		// If verbose, output the missing migration names.
		if ($params['verbose']) {
			foreach ($migrations as $migration_id => $migration_name) {
				if ($migration_id > $max_migration) {
					$apply[] = $migration_name;
				}
			}
		}
	} elseif ($latest_id < $max_migration) {
		// This database is more recent than the latest!
		$status = '!';
	} else {
		// This should be impossible to reach.
		$status = 'E';
	}

	echo $status, "\t", $db->name, ': ', $max_migration, "\n";

	// Output the ones we need to apply in verbose mode.
	if ($params['verbose'] and (bool) $apply) {
		foreach ($apply as $id => $name) {
			echo "\t\t", $name, "\n";
		}
	}
}
