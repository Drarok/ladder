<?php

global $params;

// Get info from the filesystem about our migrations.
$migrations = Migration::get_migrations(TRUE);
$latest_id = Migration::get_latest_migration_id();

echo "Migration Status\n";
echo 'Latest migration is ', $latest_id;
if ($params['verbose']) {
	echo ' (', substr($migrations[$latest_id], 0, -16), ')';
}
echo "\n";

$db = Database::factory();

while ($db->next_database(FALSE)) {
	// Grab the latest migration.
	$max_migration = $db->get_current_migration();

	// Initialise our array of migrations to apply.
	$apply = array();

	// Get the migrations from this database.
	$db_migrations = $db->get_migrations();

	// Loop over each filesystem migration and see if it's applied.
	foreach ($migrations as $migration_id => $migration_class) {
		if (! in_array($migration_id, $db_migrations)) {
			$apply[$migration_id] = $migration_class;
		}
	}

	// Work out the status identifier.
	if (! (bool) $apply) {
		// This database is up-to-date.
		$status = '=';
	} elseif (count($db_migrations) < count($migrations)) {
		// This database is out-of-date.
		$status = '<';
	} elseif (count($db_migrations) > count($migrations)) {
		// This database is more recent than the latest!
		$status = '!';
	} else {
		// This should be impossible to reach.
		$status = 'E';
	}

	echo $status, "\t", $db->name, ': ', $max_migration, "\n";

	if ($params['verbose']) {
		foreach ($apply as $migration_id => $migration_class) {
			echo "\t\t", substr($migration_class, 0, -16), ': ', $migration_id, "\n";
		}
	}
}
