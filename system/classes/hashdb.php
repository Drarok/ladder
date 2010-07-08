<?php

class HashDB implements Countable {
	protected $file_name;
	protected $hashes = array();

	public function __construct($file = NULL, $fail_if_not_exists = TRUE) {
		if ((bool) $file) {
			$this->load($file, $fail_if_not_exists);
		}
	}

	/**
	 * @accessor array hashes
	 */
	public function hashes() {
		return $this->hashes;
	}
	
	public function count() {
		return count($this->hashes);
	}

	public function save($file = FALSE) {
		if  ($file === FALSE) {
			$file = $this->file_name;
		}
		
		if (! (bool) $file) {
			throw new Exception('No filename specified for save()');
		}
		
		file_put_contents($file, serialize($this->hashes));
	}

	public function load($file, $fail_if_not_exists = TRUE) {
		if (! file_exists($file)) {
			if ((bool) $fail_if_not_exists) {
				throw new Exception('File not found: '.$file);
			} else {
				$this->file_name = $file;
				$this->clear();
				return;
			}
		}
		
		$this->file_name = $file;
		$this->hashes = unserialize(file_get_contents($file));
	}

	public function clear() {
		$this->hashes = array();
	}

	/**
	 * Check to see if the specified Hash is stored.
	 */
	public function has(Hash $hash) {
		return array_key_exists($hash->sha1(), $this->hashes);
	}

	/**
	 * Add some data to the hash db and return the new Hash instance.
	 */
	public function add($data) {
		if (! is_string($data)) {
			throw new Exception('You must pass string data to HashDB->add()');
		}
		
		// Create a new object.
		$hash = new Hash($this, $data);
		
		// Check it's not already stored.
		if ($this->has($hash)) {
			throw new Exception('Non-unique hashes are not supported');
		}
		
		// Store it once checks are passed.
		$this->hashes[$hash->sha1()] = $hash;
		
		// Return the new object.
		return $hash;
	}

	/**
	 * Return a shortened version of the hash.
	 */
	public function abbreviate(Hash $hash) {
		if (! $this->has($hash)) {
			throw new Exception('Hash must already be stored to abbreviate');
		}
		
		// Minimum hash abbreviation length.
		$length = 6;
		
		do {
			// Get the start of the requested hash.
			$abbreviation = substr($hash->sha1(), 0, $length);
			
			// Check how many hashes match.
			$matches = 0;
			foreach ($this->hashes as $stored_key => $stored_hash) {
				if (substr($stored_key, 0, $length) == $abbreviation) {
					$matches++;
				}
			}
			
			// Increase the length each loop.
			$length++;
		} while ($matches != 1);
		
		return $abbreviation;
	}
	
	/**
	 * Try to locate a hash from its abbreviation.
	 */
	public function find($abbreviation) {
		// Initialise.
		$hash = FALSE;
		
		// Hash abbreviation length.
		$length = strlen($abbreviation);
		
		// Traverse the array looking for matching hashes.
		$hashes = 0;
		foreach ($this->hashes as $stored_key => $stored_hash) {
			if (substr($stored_key, 0, $length) == $abbreviation) {
				$hashes++;
				$hash = $stored_hash;
			}
		}
		
		if ($hashes != 1) {
			throw new Exception(sprintf(
				'Found %d hashes matching %s',
				count($hashes), $abbreviation
			));
		}
		
		return $hash;
	}
}