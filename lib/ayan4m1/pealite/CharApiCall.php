<?php

namespace ayan4m1\pealite;

class CharApiCall extends MemcachedApiCall {
	private $charId;

	public function execute() {
		$this->parameters['characterID'] = $this->charId;
		parent::execute();
	}

	public function setCharId($charId) {
		$this->charId = $charId;
	}
}
