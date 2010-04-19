<?php

// Get all the migrations from the filesystem.
$migrations = Migration::get_migrations();

// Get a Database instance.
$db = Database::factory();

// Loop over each database.
while ($db->next_database()) {
	$db_migrations = $db->get_migrations();

	foreach (array_diff($db_migrations, $migrations) as $db_id) {
		echo 'Removing invalid migration ', $db_id, "\n";
		$db->remove_migration($db_id);
	}
}
