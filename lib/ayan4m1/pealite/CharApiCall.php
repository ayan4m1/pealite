<?php

namespace ayan4m1\pealite;

class CharApiCall extends MemcachedApiCall {
	function __construct($apiKey, $apiCode) {
		parent::__construct($apiKey, $apiCode);
		$this->parameters['characterID'] = null;
	}

	public function setCharId($charId) {
		$this->parameters['characterID'] = $charId;
	}
}
