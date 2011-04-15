<?php

$imports = array(
	'classes' => array(
		'database',
		'grant_manager',
		'ladder',
		'migration',
		'table',
	),
	'helpers' => array(
		'config',
		'arr',
		'sql',
		'template',
	),
);

foreach ($imports as $folder => $imports)
	foreach ($imports as $import)
		require_once(LADDER_SYSPATH.$folder.'/'.$import.'.php');
