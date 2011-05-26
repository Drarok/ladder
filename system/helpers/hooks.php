<?php

abstract class hooks {
	const DATABASE_CONNECT = 'database.connect';
	const DATABASE_DISCONNECT = 'database.disconnect';
	
	protected static $hooks = array(
		self::DATABASE_CONNECT => array(),
		self::DATABASE_DISCONNECT => array(),
	);
	
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