<?php

namespace ayan4m1\pealite\calls;

use ayan4m1\pealite\CharApiCall;
use ayan4m1\pealite\ApiCallState;

class CharWalletJournal extends CharApiCall {
	private $fromId;
	private $rowCount = 2560;

	public function execute() {
		$this->parameters['rowCount'] = $this->rowCount;
		if (isset($this->fromId)) {
			$this->parameters['fromID'] = $this->fromId;
		}

		parent::execute();
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
