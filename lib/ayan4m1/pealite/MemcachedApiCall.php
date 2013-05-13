<?php

namespace ayan4m1\pealite;

class MemcachedApiCall extends ApiCall implements ICachedApiCall {
	private $memcached;
	private $cached;

	public function __construct($apiKey, $apiCode, $corporate = false) {
		parent::__construct($apiKey, $apiCode, $corporate);
		$this->memcached = new \Memcached();
		$this->memcached->addServer('localhost', 11211);
	}

	public function execute() {
		$cachedCall = $this->memcached->get($this->getHash());
		if ($cachedCall !== false) {
			// todo: a more efficient system for setting these properties
			foreach(get_object_vars($cachedCall) as $key => $value) {
				if (!is_object($value)) {
					$this->$key = $value;
				}
			}
			$this->cached = true;
		} else {
			$this->cached = false;
			parent::execute();
			$this->memcached->set($this->getHash(), $this, $this->getExpiresTime());
		}
	}

	public function getHash() {
		return $this->method . $this->apiKey;
	}

	public function getCached() {
		return $cached;
	}

}
