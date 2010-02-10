<?php

$imports = array(
	'classes' => array(
		'database',
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
		require_once(SYSPATH.$folder.'/'.$import.EXT);
