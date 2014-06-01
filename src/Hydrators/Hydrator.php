<?php

namespace Flunorette\Hydrators;

use Flunorette\Statement;

abstract class Hydrator {

	final public function __invoke() {
		return call_user_func_array(array($this, 'hydrate'), func_get_args());
	}

	abstract public function hydrate(Statement $statement);

}
