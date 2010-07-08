<?php

abstract class cli {
	protected static $command;
	
	/**
	 * Return the first non-option argument.
	 */
	public static function command() {
		if ((bool) self::$command) {
			return self::$command;
		}
		
		foreach (array_slice($_SERVER['argv'], 2) as $index => $arg) {
			// No leading or trailing whitespace.
			if (! (bool) $arg = trim($arg)) {
				continue;
			}
			
			// Skip arguments with leading hyphens.
			if ($arg[0] == '-') {
				continue;
			}
			
			// If we got this far, we have a winner.
			return self::$command = $arg;
		}
		
		return self::$command = 'help';
	}
}