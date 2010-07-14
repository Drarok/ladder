<?php

cli::log('info', 'Migration Status');

$latest_id = Migration::latest_id();
if (! cli::option('verbose')) {
	cli::log(
		'info', 'Latest Available Migration: %d', $latest_id
	);
} else {
	cli::log(
		'info', 'Latest Available Migration: %d (%s)',
		$latest_id, Migration::name($latest_id)
	);
}

$latest_id = (int) Migration_Model::latest()->primary_key_value;
if (! cli::option('verbose')) {
	cli::log(
		'info', 'Latest Applied Migration: %d', $latest_id
	);
} else {
	cli::log(
		'info', 'Latest Applied Migration: %d (%s)',
		$latest_id, Migration::name($latest_id)
	);
}

// Grab the latest migration from the database.
$latest_db = Migration_Model::latest();

// Get the list of migrations from the database.
$db_migrations = ORM::factory('migration')->select_list(NULL, 'migration');

// Compare the two.
$missing_ids = array_diff(Migration::get_ids(), $db_migrations);

// Work out the status identifier.
if (! (bool) $missing_ids) {
	// This database is up-to-date.
	$status = 'Up-to-date';
} elseif (count($db_migrations) < count(Migration::get_ids())) {
	// This database is out-of-date.
	$status = 'Out of date';
} elseif (count($db_migrations) > count(Migration::get_ids())) {
	// This database is more recent than the latest!
	$status = 'Migrations database integrity failure';
} else {
	// This should be impossible to reach.
	$status = 'E';
}

if (cli::option('verbose')) {
	foreach ($missing_ids as $missing_id) {
		cli::log(
			'info', 'Missing Migration %d: %s',
			(int) $missing_id, Migration::name($missing_id)
		);
	}
}
