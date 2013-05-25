<?php

namespace ayan4m1\pealite;

abstract class ApiCall implements IApiCall {
	protected $method;
	protected $apiKey;
	protected $apiCode;

	protected $parameters;
	protected $response;
	protected $state;
	protected $errors;

	protected $requestTime;
	protected $expiresTime;

	const BASE_URL = "https://api.eveonline.com/";

	public function __construct($apiKey, $apiCode) {
		$this->method = $this->getMethodName();
		$this->apiKey = $apiKey;
		$this->apiCode = $apiCode;
		$this->errors = array();
		$this->parameters = array();
		$this->state = ApiCallState::READY;
	}

	private function getMethodName() {
		$className = get_class($this);
		$className = substr($className, strrpos($className, '\\') + 1);
		$tokens = preg_split('/([A-Z]{1}[a-z0-9]*)/', $className, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		$object = strtolower(array_shift($tokens));
		$method = implode($tokens);
		return $object . '/' . $method;
	}

	public function execute() {
		$this->state = ApiCallState::EXECUTING;
		$apiUrl = self::BASE_URL . $this->method . '.xml.aspx?';
		$apiUrl .= 'keyID=' . $this->apiKey . '&';
		$apiUrl .= 'vCode=' . $this->apiCode;
		foreach($this->parameters as $key => $value) {
			if (isset($value)) {
				$apiUrl .= '&' . $key . '=' . $value;
			}
		}

		$curl = curl_init($apiUrl);
		curl_setopt($curl, CURLINFO_HEADER_OUT, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		
		$this->response = curl_exec($curl);
		$respCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		if ($respCode != 200) {
			$this->state = ERROR_HTTP;
			$this->errors[] = "HTTP Error: Response code " . $respCode;
			return;
		}

		libxml_use_internal_errors(true);
		$respXml = simplexml_load_string($this->response);
		$errors = libxml_get_errors();
		if (count($errors) > 0) {
			$this->state = ApiCallState::ERROR_XML;
			foreach($errors as $error) {
				$this->errors[] = "XML " . $error->level . " [l" . $error->line . ",c" . $error->column . "]: " . $error->message . " (code " . $error->code . ")";
			}
			libxml_clear_errors();
			return;
		}

		if (isset($respXml->error)) {
			$this->state = ApiCallState::ERROR_API;
			$this->errors[] = "API Error: " . $respXml->error . " (code " . $respXml->error['code'] . ")";
			return;
		}

		$date = date_create_from_format('Y-m-d H:i:s', $respXml->currentTime);
		$this->requestTime = $date->getTimestamp();
		$date = date_create_from_format('Y-m-d H:i:s', $respXml->cachedUntil);
		$this->expiresTime = $date->getTimestamp();

		$this->state = ApiCallState::SUCCESS;
	}

	public function getState() {
		return $this->state;
	}

	public function getErrors() {
		return $this->errors;
	}

	public function getResponse() {
		return $this->response;
	}

	public function getResponseXml() {
		return simplexml_load_string($this->response);
	}

	public function getRequestTime() {
		return $this->requestTime;
	}

	public function getExpiresTime() {
		return $this->expiresTime;
	}
}
