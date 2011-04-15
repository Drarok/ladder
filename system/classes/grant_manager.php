<?php

class Grant_Manager {
	protected static $instance;

	protected $db;
	protected $current_user;

	/**
	 * Singleton access.
	 */
	public static function instance() {
		if (! (bool) self::$instance) {
			self::$instance = new Grant_Manager;
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db = LadderDB::factory();
	}

	/**
	 * Ensure the passed username is in the format 'user@host'.
	 * @param $username string
	 */
	protected function valid_username($username) {
		if ($username === NULL) {
			$username = $this->current_user;
		} else {
			$this->current_user = $username;
		}

		if (strpos($username, '@') === FALSE) {
			throw new Exception('You must use the format \'username@hostname\'');
		} else {
			return explode('@', $username, 2);
		}
	}

	/**
	 * Create a new user.
	 * Chainable.
	 * @param $username string
	 * @param $password string
	 */
	public function create_user($username, $password) {
		// Require 'user@host'.
		list($username, $host) = $this->valid_username($username);

		// Create the user.
		$this->db->query(sprintf(
			'CREATE USER %s@%s IDENTIFIED BY %s',
			sql::escape($username), sql::escape($host),
			sql::escape($password)
		));

		return $this;
	}

	/**
	 * Drop an existing user.
	 * Chainable.
	 * @param $username string
	 */
	public function drop_user($username) {
		list($username, $host) = $this->valid_username($username);

		$this->db->query(sprintf(
			'DROP USER %s@%s',
			sql::escape($username), sql::escape($host)
		));

		return $this;
	}

	/**
	 * Set the user for subsequent grant/revoke calls.
	 * Chainable.
	 * @param $username string
	 */
	public function user($username) {
		$this->valid_username($username);
		return $this;
	}

	/**
	 * Grant privilege(s) to a user.
	 * Chainable.
	 * @param $username string
	 * @param $privileges string|array
	 * @param $object string
	 */
	public function grant($privileges, $object, $username = NULL) {
		// Require 'user@host'.
		list($username, $host) = $this->valid_username($username);

		// Prefix with current database if no dot in object.
		if (strpos($object, '.') === FALSE) {
			$object = $this->db->name.'.'.$object;
		}

		// Make sure the object is escaped.
		$object = sql::escape_identifier($object);

		// Perform the grant.
		$this->db->query(sprintf(
			'GRANT %s ON %s TO %s@%s',
			implode(', ', (array) $privileges), $object,
			sql::escape($username), sql::escape($host)
		));

		return $this;
	}

	/**
	 * Revoke privilege(s) from a user.
	 * Chainable.
	 * @param $privileges string|array
	 * @param $object string
	 * @param $username[optional] string
	 */
	public function revoke($privileges, $object, $username = NULL) {
		list($username, $host) = $this->valid_username($username);

		// Prefix with current database if no dot in object.
		if (strpos($object, '.') === FALSE) {
			$object = $this->db->name.'.'.$object;
		}

		// Make sure the object is escaped.
		$object = sql::escape_identifier($object);

		// Perform the revoke.
		$this->db->query(sprintf(
			'REVOKE %s ON %s FROM %s@%s',
			implode(', ', (array) $privileges), $object,
			sql::escape($username), sql::escape($host)
		));

		return $this;
	}

	/**
	 * Revoke all privileges from a user.
	 * @chainable
	 * @param $username[optional] string
	 */
	public function revoke_all($username = NULL) {
		list($username, $host) = $this->valid_username($username);

		$this->db->query(sprintf(
			'REVOKE ALL PRIVILEGES, GRANT OPTION FROM %s@%s',
			sql::escape($username), sql::escape($host)
		));

		return $this;
	}
}
