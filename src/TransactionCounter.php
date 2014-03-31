<?php

namespace Flunorette;

use PDO;
use PDOException;

class TransactionCounter {

	/** @var PDO */
	protected $pdo;

	/** @var int */
	protected $counter = 0;

	function __construct(PDO $pdo) {
		$this->pdo = $pdo;
	}

	public function getCount() {
		return $this->counter;
	}

	/** @return bool */
	public function beginTransaction() {
		if ($this->counter === 0) {
			if (!($this->pdo->beginTransaction() && $this->pdo->inTransaction())) {
				return false;
			}
		}
		$this->counter++;
		return true;
	}

	/** @return bool */
	public function commit() {
		if ($this->counter === 0) {
			throw new PDOException('No transaction is started.');
		}
		$ok = true;
		if ($this->counter - 1 === 0) {
			$ok = $this->pdo->commit();
		}
		if ($ok) {
			$this->counter--;
		}
		return $ok;
	}

	/** @return bool */
	public function rollBack() {
		if ($this->pdo->rollBack()) {
			$this->counter = 0;
			return true;
		}
		return false;
	}

}
