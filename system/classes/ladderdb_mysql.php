<?php

class LadderDB_MySQL extends LadderDB {
	public function escape_value($value) {
		return mysql_real_escape_string($value, $this->connect());
	}

	public function insert_id() {
		return mysql_insert_id($this->connect());
	}

	protected function _connect($host, $port, $username, $password) {
		if ($port) {
			$host .= ':' . $port;
		}

		$conn = mysql_connect($host, $username, $password);

		if (! (bool) $conn) {
			throw new Exception('Unable to connect to database at '.$host.' '.mysql_error());
		}

		return $conn;
	}

	protected function _disconnect() {
		if ($this->conn) {
			mysql_close($this->conn);
		}
	}

	protected function _query($sql) {
		$conn = $this->connect();

		$res = mysql_query($sql, $conn);

		if (! (bool) $res) {
			$error = mysql_error($conn);

			$warnings = array();

			// See if we need to ask for warnings information.
			if (strpos($error, 'Check warnings') !== FALSE) {
				$warn_query = mysql_query('SHOW WARNINGS', $conn);

				while ($warn_row = mysql_fetch_object($warn_query)) {
					$warnings[] = $warn_row->Level.' - '.$warn_row->Message;
				}
			}

			throw new Exception($error.' '.implode(', ', $warnings));
		} else {
			if (is_bool($res)) {
				return $res;
			} else {
				return new LadderDB_Result_MySQL($res);
			}
		}
	}

	protected function _select_db($name) {
		return mysql_select_db($name, $this->connect());
	}
}
