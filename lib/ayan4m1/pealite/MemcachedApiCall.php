<?php

namespace ayan4m1\pealite;

use ayan4m1\pealite\CachedApiCall;

class MemcachedApiCall extends CachedApiCall implements ICachedApiCall {
	private $memcached;
	private $cached;

	public function __construct($apiKey, $apiCode, $corporate = false) {
		parent::__construct($apiKey, $apiCode, $corporate);
		$this->memcached = new \Memcached();
		$this->memcached->addServer('localhost', 11211);
	}

	public function execute() {
		$cachedCall = $this->memcached->get($apiCall->getHash());
		if ($cachedCall !== false) {
			$this->cached = true;
			return $cachedCall;
		} else {
			$this->cached = false;
			$this->execute();
			$this->memcached->set($apiCall->getHash(), $apiCall, $apiCall->getExpiresTime());
			return $apiCall;
		}
	}

	public function getHash() {
		return $this->method . $this->apiKey;
	}

	public function getCached() {
		return $cached;
	}

}
