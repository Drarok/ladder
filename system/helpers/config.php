<?php

class Config {
	protected static $cache = array();

	public static $config_name = 'default';
	public static $general_files = array('editor', 'table');

	public static function item($name, $default = FALSE) {
		$parts = explode('.', $name, 2);

		$filename = array_shift($parts);

		if ((bool) $parts) {
			$key = array_shift($parts);
		} else {
			$key = TRUE;
		}

		if (! array_key_exists($filename, self::$cache)) {
			$file_path = APPPATH.'config/'.$filename.EXT;

			if (! file_exists($file_path)) {
				throw new Exception(sprintf('Missing config file: "%s"', $file_path));
			}

			require_once(APPPATH.'config/'.$filename.EXT);

			if (in_array($filename, self::$general_files)) {
				// General files don't have a sub-key for each self::$config_name.
				self::$cache[$filename] = $config;
			} else {
				// Check the requested config exists.
				if (! array_key_exists(self::$config_name, $config))
					throw new Exception('Invalid config name: '.self::$config_name);

				// Save it to the cache.
				self::$cache[$filename] = $config[self::$config_name];
			}
		}

		// If no key part, just return the whole array.
		if ($key === TRUE) {
			return self::$cache[$filename];
		}

		return array_key_exists($key, self::$cache[$filename])
			? self::$cache[$filename][$key]
			: $default;
	}

	public static function set_config($config) {
		self::$config_name = $config;
	}

	public static function set_item($name, $value) {
		list($filename, $key) = explode('.', $name, 2);

		// If nothing loaded, "load" an empty file.
		if (! array_key_exists($filename, self::$cache))
			self::$cache[$filename] = array();

		// Set the key.
		self::$cache[$filename][$key] = $value;
	}

	public static function clear() {
		self::$cache = array();
	}
}
