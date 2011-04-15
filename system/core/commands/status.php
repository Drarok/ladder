<?php

global $params;

// Get info from the filesystem about our migrations.
$migration_ids = Migration::get_migration_ids();
$migration_count = count($migration_ids);
$migration_names = Migration::get_migration_names();
$latest_id = Migration::get_latest_migration_id();

echo "Migration Status\n";
echo 'Latest migration is ', $latest_id;
if ($params['verbose']) {
	echo ' (', $migration_names[$latest_id], ')';
}
echo "\n";

$db = LadderDB::factory();

while ($db->next_database(FALSE)) {
	// Grab the latest migration.
	$max_migration = $db->get_current_migration();

	// Get the migrations from this database.
	$db_migrations = $db->get_migrations();

	// Compare the two.
	$missing_ids = array_diff($migration_ids, $db_migrations);

	// Work out the status identifier.
	if (! (bool) $missing_ids) {
		// This database is up-to-date.
		$status = '=';
	} elseif (count($db_migrations) < $migration_count) {
		// This database is out-of-date.
		$status = '<';
	} elseif (count($db_migrations) > $migration_count) {
		// This database is more recent than the latest!
		$status = '!';
	} else {
		// This should be impossible to reach.
		$status = 'E';
	}

	echo $status, "\t", $db->name, ': ', $max_migration, "\n";

	if ($params['verbose']) {
		foreach ($missing_ids as $missing_id) {
			echo "\t\t", $missing_id, ': ', $migration_names[$missing_id], "\n";
		}
	}
}
