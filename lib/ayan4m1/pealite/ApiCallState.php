<?php

namespace ayan4m1\pealite;

class ApiCallState {
	const READY = 1;
	const EXECUTING = 2;
	const SUCCESS = 4;
	const ERROR_XML = 8;
	const ERROR_HTTP = 16;
	const ERROR_API = 32;
	const ERROR_UNKNOWN = 64;
}
