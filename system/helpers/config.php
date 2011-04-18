<?php

class Config {
	protected static $cache = array();

	public static $config_name = 'default';
	public static $general_files = array('config', 'editor', 'table');

	public static function item($name, $default = FALSE) {
		$parts = explode('.', $name, 2);

		$filename = array_shift($parts);

		if ((bool) $parts) {
			$key = array_shift($parts);
		} else {
			$key = TRUE;
		}
		
		if (! array_key_exists($filename, self::$cache)) {
			$file_path = LADDER_APPPATH.'config/'.$filename.'.php';
			
			if (! file_exists($file_path)) {
				throw new Exception(sprintf('Missing config file: "%s"', $file_path));
			}
			
			require_once(LADDER_APPPATH.'config/'.$filename.'.php');

			if (in_array($filename, self::$general_files)) {
				// General files don't have a sub-key for each self::$config_name.
				self::$cache[$filename] = $config;
			} else {
				// Check the requested config exists.
				if (! array_key_exists(self::$config_name, $config)) {
 					throw new Exception(sprintf(
						'Invalid config name \'%s\' in file \'%s\'.',
						self::$config_name, $filename
					));
				}

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
	
	public static function kohana() {
		// Bring in the Kohana stuff...
		$start_time = microtime(TRUE);
		
		// Store a copy of the argv values.
		$real_argv = $_SERVER['argv'];
		
		// Set up the argv as Kohana expects it.
		$index_path = self::item('config.kohana-index');
		$_SERVER['argv'] = array(
			realpath($index_path),
			'ladder'
		);
		$_SERVER['argc'] = count($_SERVER['argv']);
				
		try {
			ob_start();
			require_once($index_path);
			ob_end_flush();
			echo sprintf('Loaded Kohana in %.3fs', microtime(TRUE) - $start_time), PHP_EOL;
			
			$key_prefix = 'database.'.self::$config_name.'.connection.';
			self::set_item('database.hostname', Kohana::config($key_prefix.'host'));
			self::set_item('database.port', Kohana::config($key_prefix.'port'));
			self::set_item('database.username', Kohana::config($key_prefix.'user'));
			self::set_item('database.password', Kohana::config($key_prefix.'pass'));
			self::set_item('database.database', Kohana::config($key_prefix.'database'));
		} catch (Exception $e) {
			echo 'Failed to import Kohana: ', $e->getMessage(), PHP_EOL;
		}
		
		// Set the error and exception handlers back to our own, as Kohana changes them.
		set_error_handler(array('ladder', 'error_handler'));
		set_exception_handler(array('ladder', 'exception_handler'));
		
		// Restore the "real" argv.
		$_SERVER['argv'] = $real_argv;
		$_SERVER['argc'] = count($real_argv);
	}
}
