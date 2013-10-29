<?php

class template {
	/**
	 * Return a formatted migration file.
	 */
	public static function migration($classname) {
		return str_replace(
			array('{{MIGRATION_CLASS}}', '{{LADDER_VERSION}}'),
			array($classname, LADDER_VERSION),
			file_get_contents(LADDER_SYSPATH.'templates/migration.php')
		);
	}

	public static function data() {
		return file_get_contents(LADDER_SYSPATH.'templates/data.php');
	}
}
