<?php

// Always check the directory exists.
if (! is_dir(LADDER_APPPATH.'migrations')) {
	mkdir(LADDER_APPPATH.'migrations');
}

// Try to use unnamed arg if no name passed.
if ($params['name'] === FALSE) {
	global $args;
	if (! empty($args[0])) {
		$params['name'] = $args[0];
	}
}

if (! $params['name']) {
	throw new Exception('No migration name supplied.');
}

// Validate the name.
if (! preg_match('/[a-zA-Z0-9_-]/', $params['name'])) {
	throw new Exception('Migration names must use a-z, 0-9, underscore and hyphen only.');
}

// Calculate the new id.
if (! Config::item('config.timestamp-ids')) {
	// Calculate the next sequential migration id.
	$files = glob(LADDER_APPPATH.'migrations/*.php');
	sort($files);
	list($migration_id) = explode('-', basename(end($files)));
	$new_id = sprintf('%05d', 1 + (int) $migration_id);
} else {
	// Just use a timestamp.
	$new_id = time();
}

// Build the new filename.
$file_name = $new_id . '-' . $params['name'] . '.php';

// Translate filename to classname.
$migration_name = Migration::class_name($file_name);

// Save the file and let the user know.
$migration_file_path = LADDER_APPPATH.'migrations/'.$file_name;
file_put_contents($migration_file_path, template::migration($migration_name));
echo 'Created ', $file_name, ".\n";

// Edit it if the options are set.
if (TRUE === Config::item('editor.auto-edit') AND (bool) $editor = Config::item('editor.editor')) {
	shell_exec(escapeshellcmd($editor).' '.escapeshellarg($migration_file_path));
}
