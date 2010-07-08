<?php

// Get all the migrations from the filesystem.
$migrations = Migration::get_migration_ids();

// Get a Database instance.
$db = Database::instance();

// Get the migrations from this database.
$db_migrations = ORM::factory('migration')
	->select_list()
;

// Find any that are invalid.
$bad_ids = array_diff($db_migrations, $migrations);

// Remove any bad ones, or just output 'Verified' if none.
if ((bool) $bad_ids) {
	foreach ($bad_ids as $db_id) {
		echo 'Removing invalid migration ', $db_id, "\n";
		$db->remove_migration($db_id);
	}
}

echo "\t", 'Verified', "\n";