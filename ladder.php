#!/usr/bin/env php
<?php

// This hack is to get the directory that the file was run in, rather than
// using __DIR__ which resolves symlinks.
$application_path = realpath(getcwd() . DIRECTORY_SEPARATOR . dirname($argv[0]));

// The system path we actually want to resolve the symlink.
$system_path = __DIR__ . '/system';

$start_time = microtime(TRUE);

define('DS', DIRECTORY_SEPARATOR);
define('LADDER_APPPATH', realpath($application_path).DS);
define('LADDER_SYSPATH', realpath($system_path).DS);

require_once(LADDER_SYSPATH.'core/bootstrap.php');