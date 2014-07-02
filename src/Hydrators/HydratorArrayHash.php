<?php

namespace Flunorette\Hydrators;

use Flunorette\Statement;
use Nette\ArrayHash;
use PDO;

class HydratorArrayHash extends Hydrator {

	/**
	 * Whether to normalize values (e.g. dates to DateTime objects)
	 * @var bool
	 */
	public $normalize = true;

	function __construct($normalize = true) {
		$this->normalize = $normalize;
	}

	public function hydrate(Statement $statement) {
		$res = $statement->fetchAll(PDO::FETCH_ASSOC);
		if (false !== $res) {
			if ($this->normalize) {
				foreach ($res as $key => $row) {
					$res[$key] = ArrayHash::from($statement->normalizeRow($row));
				}
			} else {
				foreach ($res as $key => $row) {
					$res[$key] = ArrayHash::from($row);
				}
			}
		}
		return $res;
	}

}
