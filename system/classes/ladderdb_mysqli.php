<?php

class LadderDB_MySQLi extends LadderDB {
	public function escape_value($value) {
		return mysqli_real_escape_string($this->connect(), $value);
	}

	public function insert_id() {
		return mysqli_insert_id($this->connect());
	}

	protected function _connect($host, $port, $username, $password) {
		$conn = mysqli_connect($host, $username, $password, $this->name, $port);

		if (! (bool) $conn) {
			throw new Exception('Unable to connect to database at '.$host.' '.mysqli_error($this->conn));
		}

		return $conn;
	}

	protected function _disconnect() {
		if ($this->conn) {
			mysqli_close($this->conn);
		}
	}

	protected function _query($sql) {
		$conn = $this->connect();

		$res = mysqli_query($conn, $sql);

		if (! (bool) $res) {
			$error = mysqli_error($conn);

			$warnings = array();

			// See if we need to ask for warnings information.
			if (strpos($error, 'Check warnings') !== FALSE) {
				$warn_query = mysqli_query($conn, 'SHOW WARNINGS');

				while ($warn_row = mysqli_fetch_object($warn_query)) {
					$warnings[] = $warn_row->Level.' - '.$warn_row->Message;
				}
			}

			throw new Exception($error.' '.implode(', ', $warnings));
		} else {
			if (is_bool($res)) {
				return $res;
			} else {
				return new LadderDB_Result_MySQLi($res);
			}
		}
	}

	protected function _select_db($name) {
		return mysqli_select_db($this->connect(), $name);
	}
}
