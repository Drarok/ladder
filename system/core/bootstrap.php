<?php

// Make sure short tags are usable.
if (! (bool) ini_get('short_open_tag')) {
	die('Please enable PHP\'s short_open_tag option.');
}

// Set the version number.
define('LADDER_VERSION', trim(file_get_contents(LADDER_SYSPATH.'VERSION')));

// Import all the other classes etc. Use autoload later?
require_once(LADDER_SYSPATH.'core/imports.php');

// Set the error and exception handlers as early as possible.
set_error_handler(array('ladder', 'error_handler'));
set_exception_handler(array('ladder', 'exception_handler'));

// Set up defaults for the command-line here.
global $params;

$params = array(
	'config' => Config::item('config.config', 'default'),
	'name' => FALSE,
	'migrate-to' => 99999,
	'with-data' => Config::item('config.with-data', TRUE),
	'simulate' => FALSE,
	'database' => FALSE,
	'run-tests' => Config::item('config.run-tests', FALSE),
	'show-sql' => Config::item('config.show-sql', FALSE),
	'verbose' => Config::item('config.verbose', FALSE),
	'force' => FALSE,
	'version' => FALSE,
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

// Grab the command off the arg list, hack to 'version' if none passed with --version.
if (! (bool) $command = array_shift($args) AND $params['version']) {
	$command = 'version';
}

// Set the default config key to use.
Config::set_config($params['config']);

// Allow Kohana imports to function early on.
if ((bool) Config::item('config.kohana-index')) {
	Config::kohana();
}

// Load the selected config details.
Config::item('database');

// Allow command-line override of the database.
if ((bool) $params['database'])
	Config::set_item('database.database', $params['database']);

// Initialise the SQL helper.
sql::init();

// Decide what to do based on the command passed.
if (file_exists($command_file_name = LADDER_SYSPATH.'core/commands/'.$command.'.php')) {
	require_once($command_file_name);
} else {
?>
Invalid command: '<?php echo $command; ?>'
Usage: php ladder.php <command> [options]

<command> must be one of the following:
<?php
	$commands = glob(LADDER_SYSPATH.'core/commands/*.php');
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
	--force                   - force operations to run (applies to 'add', 'remove', and 'reapply').
<?php
};
