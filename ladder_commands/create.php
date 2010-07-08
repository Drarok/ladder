<?php

// Fetch the path from the config.
$migrations_path = Kohana::config('ladder.migrations_path');

// Always check the directory exists first.
if (! is_dir($migrations_path)) {
	throw new Exception('Invalid directory: '.$migrations_path);
}

// Find all the files we should work with.
$files = Migration::get_migration_paths();

// Grab the id of the latest migration.
$migration_id = Migration::get_latest_migration_id();

// Try to use unnamed arg 2 if no name passed.
if (! (bool) $migration_name = cli::option('name')) {
	$migration_name = cli::argument(1);
}

if (! (bool) $migration_name) {
	throw new Exception('You must specify a migration name');
}

// Generate the next migration id.
$new_id = sprintf('%05d', 1 + (int) $migration_id);

// Build the new filename.
$file_name = $new_id.'-'.$migration_name.EXT;

// Translate filename to classname.
$migration_name = Migration::class_name($file_name);

// Save the file and let the user know.
$migration_file_path = realpath($migrations_path).DS.$file_name;

$migration_view = View::factory('ladder/migration')
	->set('migration_name', $migration_name)
;

file_put_contents($migration_file_path, $migration_view->render());
echo 'Created ', $migration_file_path, ".\n";

// Edit it if the options are set.
if (Kohana::config('ladder.create.auto_edit')) {
	if ((bool) $editor = Kohana::config('ladder.create.editor')) {
		shell_exec($editor.' '.$migration_file_path);
	} else {
		echo 'No editor set.', "\n";
	}
}