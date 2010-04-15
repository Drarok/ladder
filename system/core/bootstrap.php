<?php

// Make sure short tags are usable.
if (! (bool) ini_get('short_open_tag')) {
	die('Please enable PHP\'s short_open_tag option.');
}

// Set the version number.
define('LADDER_VERSION', trim(file_get_contents(SYSPATH.'VERSION')));

// Import all the other classes etc. Use autoload later?
require_once(SYSPATH.'core/imports'.EXT);

// Set up defaults for the command-line here.
global $params;

$params = array(
	'config' => 'default',
	'name' => FALSE,
	'migrate-to' => 99999,
	'with-data' => FALSE,
	'simulate' => FALSE,
	'database' => FALSE,
	'run-tests' => FALSE,
	'show-sql' => FALSE,
	'verbose' => FALSE,
);

// Grab all the params from the command-line.
$unnamed_id = 0;
foreach ($_SERVER['argv'] as $arg) {
	if ('--' !== substr($arg, 0, 2)) {
		// Unnamed params...
		if ($unnamed_id == 2)
			$params['migrate-to'] = $arg;
		$args[$unnamed_id++] = $arg;
	} else {
		// Named params...
		if (strpos($arg, '=') !== FALSE) {
			list($key, $val) = explode('=', substr($arg, 2), 2);
			$params[$key] = $val;
		} else {
			$params[substr($arg, 2)] = TRUE;
		}
	}
}

array_shift($args); // Ignore the filename at args[0].

// Grab the command off the arg list.
$command = array_shift($args);

// Set the default config key to use.
Config::set_config($params['config']);

// Load the selected config details.
Config::item('database.database');

// Allow command-line override of the database.
if ((bool) $params['database'])
	Config::set_item('database.database', $params['database']);

// Initialise the SQL helper.
sql::init();

// Decide what to do based on the command passed.
if (file_exists($command_file_name = SYSPATH.'core/commands/'.$command.EXT)) {
	require_once($command_file_name);
} else {
?>
Invalid command: '<?=$command?>'
Usage: php ladder.php <command> [options]

<command> must be one of the following:
<?php
	$commands = glob(SYSPATH.'core/commands/*.php');
	sort($commands);
	foreach ($commands as $command_file)
		echo "\t", basename($command_file, '.php'), "\n";
?>

[options] can be any of the following:
	--config=<config_name>    - set the database config to use.
	--name=<migration_name>   - set the name of the new migration. Only valid for 'create' command.
	--with-data               - import the data along with the migration (stored in the 'data' folder).
	--simulate                - don't run the migration, but do update the migrations table.
	--database=<name>         - set the database name to run against (bypassing the config).
	--run-tests               - run any test() methods in the migrations that are processed.
	--show-sql                - output any sql queries before they are executed.
	--verbose                 - include extra output (only used by "status" at present).
<?php
};
