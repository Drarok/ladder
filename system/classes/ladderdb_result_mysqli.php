<?php

class LadderDB_Result_MySQLi extends LadderDB_Result {
	public function fetch() {
		return mysqli_fetch_assoc($this->res);
	}

	public function fetch_array() {
		return mysqli_fetch_array($this->res);
	}

	public function fetch_assoc() {
		return mysqli_fetch_assoc($this->res);
	}

	public function fetch_object() {
		return mysqli_fetch_object($this->res);
	}

	public function fetch_row() {
		return mysqli_fetch_row($this->res);
	}

	public function num_rows() {
		return mysqli_num_rows($this->res);
	}
}
