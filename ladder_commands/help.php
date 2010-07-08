<?php

if  (cli::command() != 'help') {
	echo 'Invalid command: ', cli::command(), "\n";
}

?>

Usage: php index.php ladder <command> [options]

<command> must be one of the following:
<?php
	$commands = Kohana::list_files('ladder_commands');
	sort($commands);
	foreach ($commands as $command_file) {
		echo "\t", basename($command_file, '.php'), "\n";
	}
?>

[options] can be any of the following:
	--with-data               - import the data along with the migration (stored in the 'data' folder).
	--simulate                - don't run the migration, but do update the migrations table.
	--run-tests               - run any test() methods in the migrations that are processed.
	--show-sql                - output any sql queries before they are executed.
	--verbose                 - include extra output (only used by "status" at present).
	--force                   - force operations to run (applies to 'add', 'remove', and 'reapply').
