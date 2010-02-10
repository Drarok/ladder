<?php

class Database {
	protected $conn;
	protected $databases;
	protected $database_id;
	
	protected static $instance;
	
	public static function factory() {
		if (! self::$instance)
			new Database;
		
		return self::$instance;
	}

	public function __construct() {
		$this->connect();

		if (is_string($this->databases = Config::item('database.database')))
			$this->databases = array($this->databases);

		$this->database_id = -1;
		
		if (! self::$instance)
			self::$instance = $this;
	}

	public function __get($key) {
		if ($key === 'database') {
			echo 'Warning: Database->database is deprecated. Use Database->name', "\n";

			// Grab the current call stack.
			$stack = debug_backtrace(TRUE);

			// Output info.
			echo "\t", 'Debug Backtrace:', "\n";
			foreach ($stack as $info) {
				$file_path = $info['file'];
				if (substr($file_path, 0, strlen(APPPATH)) == APPPATH) {
					$file_path = substr($file_path, strlen(APPPATH));
					$file_path = 'APPPATH'.DS.$file_path;
				} elseif (substr($file_path, 0, strlen(SYSPATH)) == SYSPATH) {
					$file_path = substr($file_path, strlen(SYSPATH));
					$file_path = 'SYSPATH'.DS.$file_path;
				}

				// Is it a class method that was called?
				if (array_key_exists('type', $info)) {
					if ($info['type'] == '::') {
						$prefix = $info['class'].'::';
					} else {
						$prefix = get_class($info['object']).'->';
					}
				} else {
					$prefix = '';
				}

				$function = $prefix.$info['function'];

				echo "\t", sprintf('File: %s, line: %d, function: %s', $file_path, $info['line'], $function), "\n";
			}

			echo "\n";

			// Finally return.
			return $this->databases[$this->database_id];
		}

		if ($key === 'name') {
			return $this->databases[$this->database_id];
		}
	}

	protected function connect() {
		$port = Config::item('database.port');
		if ((bool) $port)
			$port = ':'.$port;

		$this->conn = mysql_connect(
			$host = Config::item('database.hostname').$port,
			Config::item('database.username'),
			Config::item('database.password')
		);

		if (! (bool) $this->conn)
			throw new Exception('Unable to connect to database at '.$host.' '.mysql_error());

		echo 'Connected to ', $host, "\n";

		if ($this->database_id > -1) {
			if (! mysql_select_db($this->name, $this->conn))
				throw new Exception('Invalid database: '.$this->name);
		}
	}

	protected function disconnect() {
		mysql_close($this->conn);
		$this->conn = FALSE;
	}

	public function reconnect() {
		$this->disconnect();
		$this->connect();
	}

	public function query($sql, $show_sql = NULL) {
		global $params;
		
		if ($show_sql === NULL)
			$show_sql = array_key_exists('show-sql', $params);
		
		if ($show_sql)
			echo $sql, "\n";
		
		$res = mysql_query($sql, $this->conn);

		if (! (bool) $res) {
			throw new Exception(mysql_error($this->conn));
		} else {
			return $res;
		}
	}

	public function next_database() {
		++$this->database_id;

		if ($this->database_id < count($this->databases)) {
			if (! mysql_select_db($this->name, $this->conn))
				throw new Exception('Invalid database: '.$this->name);
			return TRUE;
		} else {
			return FALSE;
		};
	}
}
