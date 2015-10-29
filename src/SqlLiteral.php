<?php

namespace Flunorette;

class SqlLiteral implements IQueryObject {

	/** @var string */
	private $value;

	/** @var array */
	private $parameters;

	public function __construct($value, $parameters = array()) {
		$this->value = (string) $value;
		$this->parameters = is_array($parameters) ? $parameters : array($parameters);
	}

	/**
	 * @return array
	 */
	public function getParameters() {
		return $this->parameters;
	}

	public function getQuery() {
		return $this->value;
	}

	public function getHash() {
		return md5(serialize(array($this->value, Helpers::hashParams($this->parameters))));
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->value;
	}

}
