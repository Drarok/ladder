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
		// Don't run if the database already has this migration.
		if ($db->has_migration($migration_id)) {
			echo 'This database already contains migration ', $migration_id, "\n";
			continue;
		}

		// Initialise our success var.
		$success = FALSE;

		// Output some info.
		echo 'Upgrading...', "\n";

		// Attempt to run the migration.
		try {
			// Run the up method.
			$migration->_up();

			// Check if we need to run the test method.
			if ($params['run-tests'] AND method_exists($migration, 'test')) {
				// Make sure it's been run.
				$migration->execute();

				// Run the tests.
				echo 'Testing...', "\n";
				$migration->test();
			}

			// Run the destructor.
			unset($migration);

			// Success!
			$success = TRUE;
		} catch (Exception $e) {
			echo 'Error: ', $e->getMessage(), "\n";
		}

		// If it succeeded, or --force is specified, update the migrations.
		if ($success OR $params['force']) {
			try {
				$db->add_migration($migration_id);
			} catch (Exception $e) {
				echo 'Error: ', $e->getMessage(), "\n";
			}
		}
	}
}
