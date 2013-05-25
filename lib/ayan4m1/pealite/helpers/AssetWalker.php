<?php

namespace ayan4m1\pealite\helpers;

class AssetWalker implements IWalker {
	public static function walk($call) {
		// exec call if it has not been executed yet
		if ($call->getState() == ApiCallState::READY) {
			$call->execute();
		}

		// ensure first call is valid
		if ($call->getState() != ApiCallState::SUCCESS) {
			// todo: handle this failure
			return false;
		}

		// walk until we encounter an error or an empty rowset
		$respXml = $call->getResponseXml();
		$rows = array();
		while (isset($respXml->result->rowset->row) && $respXml->result->rowset->row->count() > 0) {
			// add data for return and keep track of the lowest refID
			$updated = false;
			foreach($respXml->result->rowset->row as $row) {
				$rows[] = $row;
				$fromId = $call->getFromId();
				if (!isset($fromId) || ((int)$row['refID'] < $fromId)) {
					$call->setFromId((int)$row['refID']);
					$updated = true;
				}
			}

			// continue walking if fromId was updated
			if ($updated) {
				$call->execute();
				if (count($call->getErrors()) > 0) {
					// todo: error handling
					return $rows;
				}

				// update $respXml with new data
				$respXml = $call->getResponseXml();
			}
		}
		return $rows;
	}

	private static function recurse($rowset, $rows) {
		
	}
}
