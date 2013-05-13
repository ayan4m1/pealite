pealite
=======

The goal of this project is to realize the most compact, clean interface for reliably and efficiently fetching data from the EVE API.

prerequisites
=============

* memcached
* php-memcached
* php-curl

todo
====

* add all API methods with proper parameters
* GET and POST call styles
* extend configuration options for cURL
* add support for additional common/popular cache mechanisms (APC, Redis)
* provide common data serialization  helpers (JSON, YAML)

usage example
=============

````
<?php

// all calls are cached with memcached (localhost:11211) by default
use ayan4m1\pealite\CharWalletJournal;

// fetch personal wallet journal
$call = new WalletJournal(12345, 'Verification Code');
$call->setCharId('112233');

// getState returns an ApiCallState constant
use ayan4m1\pealite\ApiCallState;

// getErrors will contain helpful error information
if ($call->getState() !== ApiCallState::SUCCESS) {
	echo "Something went wrong!" . PHP_EOL;
}
if (count($call->getErrors()) > 0) {
	var_dump($call->getErrors());die;
}

// the call contains some useful information
print_r($call->getResponse());

// unix timestamps
print_r($call->getRequestTime());
print_r($call->getExpiresTime());

// you can obtain a SimpleXMLDocument with getResponseXml()
$results = $call->getResponseXml();
foreach($results->result->rowset->row as $row) {
	print_r($row);
}

?>
````
