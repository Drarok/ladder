<?php

/**
 * Cherry-pick style migrations.
 */

$migration_id = (int) $params['migrate-to'];

if (! (bool) $migration_id OR $migration_id == '99999') {
	echo 'Invalid migration id: ', $migration_id, "\n";
	exit(1);
}

$db = Database::factory();

while ($db->next_database()) {
	// We must instantiate a fresh one because of should_run.
	$migration = Migration::factory($db, $migration_id);

	if (! (bool) $migration) {
		echo 'Error: Failed to get migration!', "\n";
	} else {
		if ($db->has_migration($migration_id)) {
			echo 'This database already contains migration ', $migration_id, "\n";
			continue;
		}

		echo 'Upgrading...', "\n";
		try {
			$migration->_up();
			$db->add_migration($migration_id);
		} catch (Exception $e) {
			echo 'Error: ', $e->getMessage(), "\n";
		}
	}
}
