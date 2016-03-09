<?php

abstract class LadderDB_Result {
	protected $res;

	abstract public function fetch();
	abstract public function fetch_array();
	abstract public function fetch_assoc();
	abstract public function fetch_object();
	abstract public function fetch_row();
	abstract public function num_rows();

	public function __construct($res) {
		$this->res = $res;
	}
}
