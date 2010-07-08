<?php

class Ladder_Controller extends Controller {
	public function __construct() {
		if (PHP_SAPI != 'cli') {
			throw new Exception('This controller is not accessible from the web');
		}
		
		set_error_handler(array($this, '_error_handler'));
		set_exception_handler(array($this, '_exception_handler'));
		
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
	 * Output some help text here.
	 */
	public function index() {
		echo 'Help Text Here.', "\n";
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
