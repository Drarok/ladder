<?php

class Ladder_Controller extends Controller {
	public function __construct() {
		if (PHP_SAPI != 'cli') {
			throw new Exception('This controller is not accessible from the web');
		}
		
		// Set up error and exception handlers.
		set_error_handler(array($this, '_error_handler'));
		set_exception_handler(array($this, '_exception_handler'));
		
		// Define the constants we need.
		define('DS', DIRECTORY_SEPARATOR);
		
		// Kick off the parent constructor now.
		parent::__construct();
	}
	
	public function _error_handler() {
		echo 'Error:', "\n";
		var_dump(func_get_args());
		echo "\n";
	}
	
	public function _exception_handler($exception) {
		echo 'Exception:', "\n";
		echo $exception->getMessage(), "\n";
	}
	
	/**
	 * Run the help command if no command/method specified.
	 */
	public function index() {
		$this->help();
	}
	
	public function __call($method, $arguments) {
		$filename = Kohana::find_file('ladder_commands', $method);
		
		if (! (bool) $filename) {
			throw new Exception('Failed to find ladder command: '.$method);
		}

		echo 'Executing ', $filename, "\n";
		require($filename);
	}
}
