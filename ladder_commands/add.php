<?php

/**
 * Cherry-pick style migrations.
 */

// Use the second unnamed argument as the id.
if (! (bool) $migration_id = (int) cli::argument(1)) {
	throw new Ladder_Exception('Invalid migration id: %d', $migration_id);
}

// Get a database instance.
$db = Database::instance();

// Get the migration.
$migration = Migration::factory($migration_id);

if (! (bool) $migration) {
	throw new Ladder_Exception('Failed to get migration %d', $migration_id);
}

// Don't run if the database already has this migration.
if (Migration_Model::exists($migration_id)) {
	cli::log('info', 'This database already contains migration %d', $migration_id);
	return;
}

// Attempt to run the migration.
try {
	// Initialise the success flag.
	$success = FALSE;
	
	// Run the up method.
	$migration->_up();

	// Run the destructor.
	unset($migration);

	// Success!
	$success = TRUE;
} catch (Exception $e) {
	cli::log('error', '%s', $e->getMessage());
}

// If it succeeded, or --force is specified, update the migrations.
if ($success OR cli::option('force')) {
	try {
		Migration_Model::create($migration_id);
	} catch (Exception $e) {
		cli::log('error', 'Failed to save migration state. %s', $e->getMessage());
	}
}