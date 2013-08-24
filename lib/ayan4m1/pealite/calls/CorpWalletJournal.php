<?php

namespace ayan4m1\pealite\calls;

use ayan4m1\pealite\CorpApiCall;

class CorpWalletJournal extends CorpApiCall {
	function __construct($apiKey, $apiCode) {
		parent::__construct($apiKey, $apiCode);
		$this->parameters['accountKey'] = 1000;
		$this->parameters['rowCount'] = 2560;
	}

	public function getAccountKey($accountKey) {
		return $this->parameters['accountKey'];
	}

	public function setAccountKey($accountKey) {
		$this->parameters['accountKey'] = $accountKey;
	}

	public function setFromId($fromId) {
		$this->parameters['fromId'] = $fromId;
	}

	public function getFromId() {
		return $this->parameters['fromId'];
	}
}
