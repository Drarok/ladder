<?php

/**
 * Cherry-pick style migrations.
 */

$migration_id = (int) $params['migrate-to'];

if (! (bool) $migration_id) {
	echo 'Invalid migration id: ', $migration_id, "\n";
	exit(1);
}

$db = LadderDB::factory();

while ($db->next_database()) {
	// We must instantiate a fresh one because of should_run.
	$migration = Migration::factory($db, $migration_id);

	if (! (bool) $migration) {
		echo "\t", 'Error: Failed to get migration!', "\n";
	} else {
		if (! $db->has_migration($migration_id)) {
			echo "\t", 'This database does not contain migration ', $migration_id, "\n";
			continue;
		}

		// Initialise the success var.
		$success = FALSE;

		// Output info.
		echo "\t", 'Downgrading...', "\n";

		// Attempt to run the migration.
		try {
			// Run the down method first.
			$migration->_down();

			// Force the destructor to run inside the 'try' block.
			unset($migration);

			// Success!
			$success = TRUE;
		} catch (Exception $e) {
			echo "\t", 'Error: ', $e->getMessage(), "\n";
		}

		// If it succeeded, or --force is specified, update the migrations.
		if ($success OR $params['force']) {
			try {
				$db->remove_migration($migration_id);
			} catch (Exception $e) {
				echo "\t", 'Error: ', $e->getMessage(), "\n";
			}
		}
	}
}
