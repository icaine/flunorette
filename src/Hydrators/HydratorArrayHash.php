<?php

namespace Flunorette\Hydrators;

use Flunorette\Statement;
use Nette\ArrayHash;

class HydratorArrayHash extends Hydrator {

	/** @var bool */
	public $fetchAll = true;

	function __construct($fetchAll = true) {
		$this->fetchAll = $fetchAll;
	}

	public function hydrate(Statement $statement) {
		$res = null;
		if ($this->fetchAll) {
			foreach ($statement as $key => $value) {
				$res[$key] = ArrayHash::from($value);
			}
		} else {
			$res = ArrayHash::from($statement->fetch());
		}
		return $res;
	}

}
