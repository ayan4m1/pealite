<?php

namespace ayan4m1\pealite;

class MemcachedApiCall extends ApiCall implements ICachedApiCall {
	private $memcached;
	private $cached;

	public function __construct($apiKey, $apiCode) {
		parent::__construct($apiKey, $apiCode);
		$this->memcached = new \Memcached();
		$this->memcached->addServer('localhost', 11211);
	}

	public function execute() {
		$cachedCall = $this->memcached->get($this->getHash());
		if ($cachedCall !== false) {
			$this->parameters = $cachedCall->parameters;
			$this->response = $cachedCall->getResponse();
			$this->state = $cachedCall->getState();
			$this->errors = $cachedCall->getErrors();
			$this->cached = true;
		} else {
			$this->cached = false;
			parent::execute();
			$this->memcached->set($this->getHash(), $this, $this->getExpiresTime());
		}
	}

	public function getHash() {
		return $this->method . $this->apiKey . array_reduce($this->parameters, function($a, $b) {
			return $a . (isset($b) ? '.' . $b : '');
		});
	}

	public function getCached() {
		return $cached;
	}

}
