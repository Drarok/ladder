<?php

// Grab all the migrations and sort them.
$migrations = glob(APPPATH.'migrations'.DS.'*.php');
sort($migrations);

// Split the numeric part off the last one.
list($latest_id) = explode('-', basename(end($migrations)), 2);

// Make sure it's an integer;
$latest_id = (int) $latest_id;

echo "Migration Status\n";
echo 'Latest migration is ', $latest_id, "\n";

$db = Database::factory();

while ($db->next_database()) {
	$db->check_migrations_table();
	$res = $db->query('SELECT MAX(`migration`) AS `max_migration` FROM `migrations`');
	$row = mysql_fetch_object($res);

	// Grab the latest migration as an integer.
	$max_migration = (int) $row->max_migration;

	// Work out the status identifier.
	if ($latest_id === $max_migration) {
		// This database is up-to-date.
		$status = '=';
	} elseif ($latest_id > $max_migration) {
		// This database is out-of-date.
		$status = '<';
	} elseif ($latest_id < $max_migration) {
		// This database is more recent than the latest!
		$status = '!';
	} else {
		// This should be impossible to reach.
		$status = 'E';
	}

	echo $status, "\t", $db->name, ': ', $max_migration, "\n";
}
