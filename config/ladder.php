<?php

$config = array(
	'migrations_table' => 'migrations',
	
	'create' => array(
		'auto_edit' => TRUE,
		'editor' => array_key_exists('EDITOR', $_ENV) ? $_ENV['EDITOR'] : 'vim',
	),
);
