<?php

namespace Flunorette;

interface IQueryObject {

	public function getQuery();

	public function getParameters();

	public function getHash();

}
