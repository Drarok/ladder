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
		cli::log('error', 'Exception: %s', $exception->getMessage());
		cli::log('error', 'File: %s (%d)', $exception->getFile(), $exception->getLine());
		
		// Loop over each trace.
		foreach ($exception->getTrace() as $stack_level => $trace) {
			// Get info from the trace.
			$file = array_key_exists('file', $trace) ? $trace['file'] : FALSE;
			$line = array_key_exists('line', $trace) ? $trace['line'] : FALSE;
			
			// Output trace info.
			cli::log('error', 'Stack %d: %s (%d)', $stack_level, $file, $line);
		}

		exit(1);
	}
	
	public function __call($method, $arguments) {
		$filename = Kohana::find_file('ladder_commands', cli::command());
		
		if (! (bool) $filename) {
			$this->help();
			return FALSE;
		}

		echo 'Executing ', $filename, "\n";
		require($filename);
	}
}
