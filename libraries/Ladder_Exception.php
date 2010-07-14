<?php

class Ladder_Exception extends Exception {
	public function __construct($message, $arguments = NULL) {
		// Get the remaining arguments after the message.
		$arguments = array_slice(func_get_args(), 1);
		
		// If there are arguments, format the message with them.
		if ((bool) $arguments) {
			$message = vsprintf($message, $arguments);
		}
		
		// Pass to the parent.
		parent::__construct($message);
	}
}