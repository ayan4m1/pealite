<?php

namespace ayan4m1\pealite\calls;

use ayan4m1\pealite\CharApiCall;
use ayan4m1\pealite\ApiCallState;

class CharWalletJournal extends CharApiCall {
	function __construct($apiKey, $apiCode) {
		parent::__construct($apiKey, $apiCode);
		$this->parameters['rowCount'] = 2560;
	}

	public function setRowCount($rowCount) {
		$this->parameters['rowCount'] = $rowCount;
	}

	public function setFromId($fromId) {
		$this->parameters['fromId'] = $fromId;
	}

	public function getFromId() {
		return isset($this->parameters['fromId']) ? $this->parameters['fromId'] : null;
	}
}
