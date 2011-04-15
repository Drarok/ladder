<?php

class template {
	/**
	 * Return a formatted migration file.
	 */
	public static function migration($name) {
		return str_replace(
			array('MIGRATION_NAME', 'LADDER_VERSION'),
			array($name, LADDER_VERSION),
			file_get_contents(LADDER_SYSPATH.'templates/migration.php')
		);
	}

	public static function data() {
		return file_get_contents(LADDER_SYSPATH.'templates/data.php');
	}
}
