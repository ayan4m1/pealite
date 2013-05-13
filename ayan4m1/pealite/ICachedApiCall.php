<?php

namespace ayan4m1\pealite;

interface ICachedApiCall extends IApiCall {
	public function getHash();
	public function getCached();
}
