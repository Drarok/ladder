<?php

// Bring in the classes.
require_once(SYSPATH.'classes'.DS.'hashdb'.EXT);
require_once(SYSPATH.'classes'.DS.'hash'.EXT);

$hashdb = new HashDB(APPPATH.'migrations'.DS.'migrations.hash', FALSE);
echo sprintf(
	'Loaded %d hashes',
	count($hashdb)
), "\n";

$frac = microtime(TRUE);
$frac = $frac - floor($frac);
$frac = substr((string) $frac, 1);
$hashdb->add(date('Y-m-d H:i:s').$frac);
$hashdb->save();

foreach ($hashdb->hashes() as $hash) {
	echo sprintf(
		'%s = %s (%s)',
		$hash->abbreviate(), $hash->sha1(), $hash->data()
	), "\n";
}