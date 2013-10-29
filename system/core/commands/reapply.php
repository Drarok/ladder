<?php

// Validate the parameters.
$migration_id = (int) $params['migrate-to'];
if (! (bool) $migration_id) {
	die('Invalid migration: '.$migration_id);
}

// Include the migration file.
require_once(Migration::file_name($migration_id));

// Get a Database instance.
$db = LadderDB::factory();

// Loop over each database.
while ($db->next_database()) {
	// Only downgrade if the migration is applied, or --force was used.
	if ($db->has_migration($migration_id) OR $params['force']) {
		echo "\t", 'Downgrading... ', "\n";

		try {
			$mig = Migration::factory($db, $migration_id);
			$mig->_down();
			unset($mig);
		} catch (Exception $e) {
			echo 'Error: ', $e->getMessage(), "\n";
		}

		// Mark the migration as removed.
		try {
			$db->remove_migration($migration_id);
		} catch (Exception $e) {
			// Ignore the error, as --force will cause one.
		}
	}

	// Only upgrade if the migration isn't already applied... Or --force was used.
	if (! $db->has_migration($migration_id) OR $params['force']) {
		echo "\t", 'Upgrading... ', "\n";
		try {
			$mig = Migration::factory($db, $migration_id);
			$mig->_up();

			// Run the test method if there is one and we're meant to.
			if ((bool) $params['run-tests'] AND method_exists($mig, 'test')) {
				$mig->execute();
				echo "\t", 'Testing...', "\n";
				$mig->test();
			}
			unset($mig);
		} catch (Exception $e) {
			echo 'Error: ', $e->getMessage(), "\n";
		}

		// Double-check that this migration is flagged as applied.
		try {
			$db->add_migration($migration_id);
		} catch(Exception $e) {
			// Squelch the error, as it's expected sometimes.
		}
	}

	echo "\tDone\n";
	unset($mig); // Is this redundant?
}
