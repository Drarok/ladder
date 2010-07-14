<?php

global $params;

// Get info from the filesystem about our migrations.
$migration_ids = Migration::get_migration_ids();
$migration_count = count($migration_ids);
$migration_names = Migration::get_migration_names();
$latest_id = Migration::get_latest_migration_id();

echo "Migration Status\n";
echo "\t", 'Latest Available Migration: ', $latest_id;
if ($params['verbose']) {
	echo ' (', $migration_names[$latest_id], ')';
}
echo "\n";

$db = Database::instance();

// Grab the latest migration from the database.
$max_migration = ORM::factory('migration')->latest_id();

// Get the list of migrations from the database.
$db_migrations = ORM::factory('migration')->select_list();

// Compare the two.
$missing_ids = array_diff($migration_ids, $db_migrations);

// Work out the status identifier.
if (! (bool) $missing_ids) {
	// This database is up-to-date.
	$status = 'Up-to-date';
} elseif (count($db_migrations) < $migration_count) {
	// This database is out-of-date.
	$status = 'Out of date';
} elseif (count($db_migrations) > $migration_count) {
	// This database is more recent than the latest!
	$status = 'Migrations database integrity failure';
} else {
	// This should be impossible to reach.
	$status = 'E';
}

echo "\t", 'Latest Database Migration: ', $max_migration, "\n";
echo "\t", 'Status: ', $status, "\n";

if (cli::option('verbose')) {
	foreach ($missing_ids as $missing_id) {
		echo "\t\t", $missing_id, ': ', $migration_names[$missing_id], "\n";
	}
}
