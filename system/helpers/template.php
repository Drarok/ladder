<?php

class template {
	/**
	 * Return a formatted migration file.
	 */
	public static function migration($name) {
		return str_replace('MIGRATION_NAME', $name, file_get_contents(SYSPATH.'templates/migration.php'));
	}

	public static function data() {
		return file_get_contents(SYSPATH.'templates/data.php');
	}
}
