<?php

echo "Migration Status\n";

$db = Database::factory();

while ($db->next_database()) {
	$db->check_migrations_table();
	$res = $db->query('SELECT MAX(`migration`) AS `max_migration` FROM `migrations`');
	$row = mysql_fetch_object($res);
	echo "\t", $db->name, ': ', (int) $row->max_migration, "\n";
}
