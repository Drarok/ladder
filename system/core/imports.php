<?php

$imports = array(
	'classes' => array(
		'ladderdb',
		'grant_manager',
		'ladder',
		'migration',
		'table',
		'kvdatacache',
		'localcache',
	),
	'helpers' => array(
		'hooks',
		'config',
		'arr',
		'sql',
		'template',
	),
);

foreach ($imports as $folder => $imports)
	foreach ($imports as $import)
		require_once(LADDER_SYSPATH.$folder.'/'.$import.'.php');
