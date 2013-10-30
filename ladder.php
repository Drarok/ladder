#!/usr/bin/env php
<?php

// This hack is to get the directory that Ladder is being used in,
// based on default Composer settings.
$application_path = implode(DIRECTORY_SEPARATOR, array(
	getcwd(),
	'ladder',
));

// The system path we actually want to resolve the symlink.
$system_path = __DIR__ . '/system';

if (realpath($application_path) === FALSE) {
	echo 'Missing application path: ', $application_path, PHP_EOL;
	exit(1);
}

if (realpath($system_path) === FALSE) {
	echo 'Missing system path: ', $system_path, PHP_EOL;
	exit(1);
}

$start_time = microtime(TRUE);

define('DS', DIRECTORY_SEPARATOR);
define('LADDER_APPPATH', realpath($application_path).DS);
define('LADDER_SYSPATH', realpath($system_path).DS);

require_once(LADDER_SYSPATH.'core/bootstrap.php');