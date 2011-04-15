<?php

// Get all the migrations from the filesystem.
$migrations = Migration::get_migration_ids();

// Get a Database instance.
$db = LadderDB::factory();

// Loop over each database.
while ($db->next_database()) {
	// Get the migrations from this database.
	$db_migrations = $db->get_migrations();

	// Find any that are invalid.
	$bad_ids = array_diff($db_migrations, $migrations);

	// Remove any bad ones, or just output 'Verified' if none.
	if ((bool) $bad_ids) {
		foreach ($bad_ids as $db_id) {
			echo 'Removing invalid migration ', $db_id, "\n";
			$db->remove_migration($db_id);
		}
	} else {
		echo "\t", 'Verified', "\n";
	}
}
