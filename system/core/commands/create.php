<?php

// Always check the directory exists.
if (! is_dir(LADDER_APPPATH.'migrations')) {
	mkdir(LADDER_APPPATH.'migrations');
}

// Find all the files we should work with.
$files = glob(LADDER_APPPATH.'migrations/*.php');

// Order by filename, as sometimes they come back in a different order.
sort($files);

// Grab the migration id from the last item in the array.
list($migration_id) = explode('-', basename(end($files)));

// Try to use unnamed arg 2 if no name passed.
if ($params['name'] === FALSE)
	$params['name'] = $params['migrate-to'];

// Add one to it by chopping out just the bit we need.
$new_id = sprintf('%05d', 1 + (int) $migration_id);

// Build the new filename.
$file_name = $new_id.'-'.$params['name'].'.php';

// Translate filename to classname.
$migration_name = Migration::class_name($file_name);

// Save the file and let the user know.
$migration_file_path = LADDER_APPPATH.'migrations/'.$file_name;
file_put_contents($migration_file_path, template::migration($migration_name));
echo 'Created ', $file_name, ".\n";

// Edit it if the options are set.
if (TRUE === Config::item('editor.auto-edit') AND (bool) $editor = Config::item('editor.editor')) {
	shell_exec($editor.' '.$migration_file_path);
}