<?php

namespace Flunorette;

class Exception extends \Exception {

}

class InvalidArgumentException extends Exception {

}

class InvalidStateException extends Exception {

}

class UndeclaredColumnException extends InvalidStateException {

}

class DriverException extends \PDOException {

	/** @var string */
	public $queryString;

	/**
	 * @returns self
	 */
	public static function from(\PDOException $src) {
		$e = new static($src->message, null, $src);
		if (!$src->errorInfo && preg_match('#SQLSTATE\[(.*?)\] \[(.*?)\] (.*)#A', $src->message, $m)) {
			$m[2] = (int) $m[2];
			$e->errorInfo = array_slice($m, 1);
			$e->code = $m[1];
		} else {
			$e->errorInfo = $src->errorInfo;
			$e->code = $src->code;
		}
		return $e;
	}

	/**
	 * @returns int|string|NULL  Driver-specific error code
	 */
	public function getDriverCode() {
		return isset($this->errorInfo[1]) ? $this->errorInfo[1] : null;
	}

	/**
	 * @returns string|NULL  SQLSTATE error code
	 */
	public function getSqlState() {
		return isset($this->errorInfo[0]) ? $this->errorInfo[0] : null;
	}

	/**
	 * @returns string|NULL  SQL command
	 */
	public function getQueryString() {
		return $this->queryString;
	}

}

/**
 * Server connection related errors.
 */
class ConnectionException extends DriverException {

}

/**
 * Base class for all constraint violation related exceptions.
 */
class ConstraintViolationException extends DriverException {

}

/**
 * Exception for a foreign key constraint violation.
 */
class ForeignKeyConstraintViolationException extends ConstraintViolationException {

}

/**
 * Exception for a NOT NULL constraint violation.
 */
class NotNullConstraintViolationException extends ConstraintViolationException {

}

/**
 * Exception for a unique constraint violation.
 */
class UniqueConstraintViolationException extends ConstraintViolationException {

}
