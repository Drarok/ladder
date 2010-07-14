<?php

abstract class cli {
	protected static $command;
	
	/**
	 * Return the nth non-option argument.
	 */
	public static function argument($index) {
		$count = 0;
		
		foreach (array_slice($_SERVER['argv'], 2) as $arg) {
			// No leading or trailing whitespace.
			if (! (bool) $arg = trim($arg)) {
				continue;
			}
			
			// Skip arguments with leading hyphens.
			if ($arg[0] == '-') {
				continue;
			}
			
			// If we got this far, we have a valid argument.
			if ($count == $index) {
				return $arg;
			} else {
				$count++;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Return the 'command' - the 1st non-option argument.
	 */
	public static function command() {
		// Set the command if it's empty.
		if (! (bool) self::$command) {
			// Get the 1st argument, if that's empty, set to 'help'.
			if (! (bool) self::$command = self::argument(0)) {
				self::$command = 'help';
			}
		}
		
		return self::$command;
		
	}
	
	/**
	 * Parse the '--option' and '--option=value' arguments.
	 */
	public static function option($name, $default = FALSE) {
		foreach (array_slice($_SERVER['argv'], 2) as $arg) {
			// Ignore blank arguments.
			if (! (bool) $arg = trim($arg)) {
				continue;
			}
			
			// If it's not an option argument, skip it.
			if (substr($arg, 0, 2) != '--') {
				continue;
			}
			
			// It's a boolean TRUE if no '=' part.
			if (strpos($arg, '=') === FALSE) {
				$arg_name = substr($arg, 2);
				$arg_value = TRUE;
			} else {
				// Split the '--command=value' into 'command' and 'value'.
				list($arg_name, $arg_value) = explode('=', substr($arg, 2), 2);
			}
			
			if (strtolower($arg_name) == strtolower($name)) {
				return $arg_value;
			}
		}
	}
}