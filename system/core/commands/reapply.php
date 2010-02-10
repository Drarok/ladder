<?php

$num = $params['migrate-to'];
$file = current(glob(sprintf(APPPATH.'migrations/%05d-*.php', (int) $num)));

$name = substr(basename($file, '.php'), 6);
$name = implode('_', array_map('ucfirst', explode('_', $name)));
$name .= '_Migration_'.sprintf('%05d', (int) $num);
var_dump($name);

$db = Database::factory();

require_once($file);

while ($db->next_database()) {
	echo $db->name, '... ', "\n";;

	echo "\t", 'Downgrading... ', "\n";
	try {
		$mig = new $name($db);
		$mig->_down();
		unset($mig);
	} catch (Exception $e) {
		echo 'Error: ', $e->getMessage(), "\n";
	}

	echo "\t", 'Upgrading... ', "\n";
	try {
		$mig = new $name($db);
		$mig->_up();

		// Run the test method if there is one and we're meant to.
		if ((bool) $params['run-tests'] AND method_exists($mig, 'test')) {
			$mig->test();
		}
		unset($mig);
	} catch (Exception $e) {
		echo 'Error: ', $e->getMessage(), "\n";
	}

	// Double-check that this migration is flagged as applied.
	try {
		$db->query(sprintf('INSERT INTO `migrations` SET `migration`=%d, `applied`=NOW()', (int) $num));
	} catch(Exception $e) {
		// Squelch the error, as it's expected sometimes.
	}

	echo "\tDone\n";
	unset($mig);
}
