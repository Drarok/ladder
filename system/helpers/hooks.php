<?php

abstract class hooks {
	const COMMAND_START = 'system.command_start';
	const COMMAND_END = 'system.command_end';
	const DATABASE_CONNECT = 'database.connect';
	const DATABASE_CHANGED = 'database.changed';
	const DATABASE_DISCONNECT = 'database.disconnect';
	const MIGRATION_UP = 'migration.up';
	const MIGRATION_DOWN = 'migration.down';
	const SYSTEM_END = 'system.end';
	
	protected static $hooks = array(
		self::COMMAND_START => array(),
		self::COMMAND_END => array(),
		self::DATABASE_CONNECT => array(),
		self::DATABASE_CHANGED => array(),
		self::DATABASE_DISCONNECT => array(),
		self::MIGRATION_UP => array(),
		self::MIGRATION_DOWN => array(),
		self::SYSTEM_END => array(),
	);
	
	/**
	 * Find any hooks in LADDER_APPPATH/hooks, and load each one.
	 */
	public static function init() {
		foreach (glob($path = LADDER_APPPATH.'hooks'.DS.'*.php') as $hook_path) {
			echo 'Loading hook: ', $hook_path, PHP_EOL;
			require_once($hook_path);
		}
	}
	
	public static function add_hook($id, $callable) {
		if (! array_key_exists($id, self::$hooks)) {
			throw new Exception(sprintf('Invalid hook id: %s', $id));
		}
		
		if (! is_callable($callable)) {
			throw new Exception('Non-callable argument passed to add_hook.');
		}
		
		self::$hooks[$id][] = $callable;
	}
	
	public static function run_hooks($id) {
		if (! array_key_exists($id, self::$hooks)) {
			throw new Exception(sprintf('Invalid hook id: %s', $id));
		}
		
		foreach (self::$hooks[$id] as $callable) {
			call_user_func($callable);
		}
	}
}