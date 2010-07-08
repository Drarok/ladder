<?php

class Hash {
	protected $db;
	protected $data;
	protected $sha1;

	public function __construct(HashDB $db, $data) {
		$this->db = $db;
		$this->data = $data;
	}

	public function data() {
		return $this->data;
	}

	public function sha1() {
		if (! (bool) $this->sha1) {
			$this->sha1 = sha1($this->data);
		}

		return $this->sha1;
	}

	public function abbreviate() {
		return $this->db->abbreviate($this);
	}
}
