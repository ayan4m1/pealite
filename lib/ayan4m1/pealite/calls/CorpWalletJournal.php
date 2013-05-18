<?php

namespace ayan4m1\pealite\calls;

use ayan4m1\pealite\MemcachedApiCall;

class CorpWalletJournal extends CorpApiCall {
	private $fromId;
	private $accountKey = 10000;
	private $rowCount = 2560;

	public function execute() {
		$this->parameters['accountKey'] = $this->accountKey;
		$this->parameters['rowCount'] = $this->rowCount;
		if (isset($this->fromId)) {
			$this->parameters['fromID'] = $this->fromId;
		}

		parent::execute();
	}

	public function setAccountKey($accountKey) {
		$this->accountKey = $accountKey;
	}

	public function setFromId($fromId) {
		$this->fromId = $fromId;
	}

	public function getFromId() {
		return $this->fromId;
	}

	public function getHash() {
		return parent::getHash() . $this->fromId;
	}
}
