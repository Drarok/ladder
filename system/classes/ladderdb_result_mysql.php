<?php

class LadderDB_Result_MySQL extends LadderDB_Result {
	public function fetch() {
		return mysql_fetch_assoc($this->res);
	}

	public function fetch_array() {
		return mysql_fetch_array($this->res);
	}

	public function fetch_assoc() {
		return mysql_fetch_assoc($this->res);
	}

	public function fetch_object() {
		return mysql_fetch_object($this->res);
	}

	public function fetch_row() {
		return mysql_fetch_row($this->res);
	}

	public function num_rows() {
		return mysql_num_rows($this->res);
	}
}
