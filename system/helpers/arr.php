<?php

class arr {
	public static function val($arr, $key, $default = FALSE) {
		if (array_key_exists($key, $arr))
			return $arr[$key];
		else
			return $default;
	}

	/**
	 * Get a key => value pairing.
	 */
	public static function pair($arr, $key, $value) {
		$rows = array();
		foreach ($arr as $row)
			$rows[$row->$key] = $row->$value;
		return $rows;
	}

	/**
	 * Reindex an array using a key from the array.
	 */
	public static function index($arr, $key) {
		$rows = array();
		foreach ($arr as $row)
			$rows[$row->$key] = $row;
		return $rows;
	}

	/**
	 * Return an array of single keys from another array.
	 */
	public static function single($arr, $key) {
		$rows = array();
		foreach ($arr as $row)
			$rows[] = $row->$key;
		return $rows;
	}
}
