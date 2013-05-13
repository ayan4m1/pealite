<?php

namespace ayan4m1\pealite;

interface IApiCall {
	public function execute();
	public function getState();
	public function getErrors();
	public function getResponse();
	public function getResponseXml();
	public function getRequestTime();
	public function getExpiresTime();
}
