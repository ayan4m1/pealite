<?php

namespace ayan4m1\pealite;

use ayan4m1\pealite\ApiCallState;

abstract class ApiCall implements IApiCall {
	private $method;
	private $corporate;

	private $apiKey;
	private $apiCode;
	private $charId;

	private $response;
	private $state;
	private $errors;

	private $requestTime;
	private $expiresTime;

	const BASE_URL = "https://api.eveonline.com/";
	const CHAR_PREFIX = "char";
	const CORP_PREFIX = "corp";

	public function __construct($apiKey, $apiCode, $corporate = false) {
		$this->method = str_replace(__NAMESPACE__ . '\\', '', get_class($this));
		$this->corporate = $corporate;
		$this->apiKey = $apiKey;
		$this->apiCode = $apiCode;
		$this->errors = array();
		$this->state = ApiCallState::READY;
	}

	public function setCharId($charId) {
		if (!$this->corporate) {
			$this->charId = $charId;
		}
	}

	public function execute() {
		$this->state = ApiCallState::EXECUTING;
		$apiUrl = self::BASE_URL . (($this->corporate === true) ? self::CORP_PREFIX : self::CHAR_PREFIX) . "/" . $this->method . ".xml.aspx?";
		$apiUrl .= "keyID=" . $this->apiKey . "&";
		$apiUrl .= "vCode=" . $this->apiCode;
		if (!empty($this->charId)) {
			$apiUrl .= "&characterId=" . $this->charId;
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
		return isset($expiresTime) ? $expiresTime : time();
	}
}
