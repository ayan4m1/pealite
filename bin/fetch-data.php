<?php
/*
 * EVE API Data Fetch
 * 
 * by ayan4m1 <andrew@bulletlogic.com>
 */ 

// you are free to load classes however you like
// this script assumes you use Composer, so change this if not
include('vendor/autoload.php');

use ayan4m1\pealite\calls\CharCharacterSheet;
use ayan4m1\pealite\calls\CharAssetList;
use ayan4m1\pealite\calls\CorpAssetList;
use ayan4m1\pealite\calls\CharWalletJournal;
use ayan4m1\pealite\calls\CorpWalletJournal;
use ayan4m1\pealite\calls\EveRefTypes;
use ayan4m1\pealite\calls\CorpCorporationSheet;
use ayan4m1\pealite\helpers\JournalWalker;

$start_time = microtime(true);

$pdo = new PDO('pgsql:host=localhost;dbname=eve', 'eve', 'omghax');

// merge latest journal ref types
/*$refTypes = new EveRefTypes('123', '456');
$refTypes->execute();
$respXml = $refTypes->getResponseXml();
$stmt = $pdo->prepare('insert into journal_entry_type values (:id, :name) on duplicate key update name = :name');
foreach($respXml->result->rowset->row as $row) {
	$stmt->bindValue(':id', $row['refTypeID']);
	$stmt->bindValue(':name', $row['refTypeName']);
	$stmt->execute();
	echo "Merged row " . $row['refTypeID'] . ", " . $row['refTypeName'];
}*/

$stmt = $pdo->prepare(<<<EndSQL
select
  ak.id api_key,
  ak.code api_code,
  akpe.player_entity_id,
  akpe.corporate
from
  api_key ak
  join api_key_player_entity akpe on ak.id = akpe.api_key_id
EndSQL
);
$stmt->execute();

$updStmt = $pdo->prepare('update api_key_player_entity set last_check = now() where api_key_id = :api_key_id and player_entity_id = :player_entity_id');
foreach($stmt->fetchAll() as $apiRow) {
	characters($apiRow);
	wallet_divisions($apiRow);
	journal($apiRow);
	orders($apiRow);
	//assets($apiRow);

	$updStmt->bindValue(':api_key_id', $apiRow['api_key']);
	$updStmt->bindValue(':player_entity_id', $apiRow['player_entity_id']);
	$updStmt->execute();
}

echo "Ended run in " . number_format(microtime(true) - $start_time, 4) . "s" . PHP_EOL;

/*
 *
 * FUNCTIONS BELOW
 *
 */

function characters($apiRow) {
	global $pdo;

	if ($apiRow['corporate']) {
		return;
	}

	$charSheet = new CharCharacterSheet($apiRow['api_key'], $apiRow['api_code']);
	$charSheet->setCharId($apiRow['player_entity_id']);
	$charSheet->execute();
	if (count($charSheet->getErrors()) > 0) {
		return;
	}

	$pdo->beginTransaction();
	$respXml = $charSheet->getResponseXml();
	foreach($respXml->result->rowset as $rowset) {
		if ($rowset['name'] != 'skills') {
			continue;
		}

		$stmt = $pdo->prepare(<<<EndSQL
delete from player_entity_skill
where player_entity_id = :player_entity_id
EndSQL
		);
		$stmt->bindValue(':player_entity_id', $apiRow['player_entity_id']);
		$stmt->execute();

		$insCount = 0;
		$insStmt = $pdo->prepare(<<<EndSQL
insert into player_entity_skill (
  player_entity_id,
  skill_type_id,
  skill_level
) values (
  :player_entity_id,
  :skill_type_id,
  :skill_level
)
EndSQL
		);

		foreach($rowset->row as $row) {
			$insStmt->bindValue(':player_entity_id', (int)$apiRow['player_entity_id']);
			$insStmt->bindValue(':skill_type_id', (int)$row['typeID']);
			$insStmt->bindValue(':skill_level', (int)$row['level']);
			if ($insStmt->execute()) {
				$insCount++;
			} else {
				$pdo->rollBack();
				echo "Exited char sheet update due to error!" . PHP_EOL;
				return;
			}
		}
	}

	$pdo->commit();
	echo "Refreshed $insCount skills for player entity " . $apiRow['player_entity_id'] . PHP_EOL;
	flush();
}

function wallet_divisions($apiRow) {
	global $pdo;

	if (!$apiRow['corporate']) {
		return;
	}

	$corpSheet = new CorpCorporationSheet($apiRow['api_key'], $apiRow['api_code']);
	$corpSheet->execute();

	if (count($corpSheet->getErrors()) > 0) {
		return;
	}

	$respXml = $corpSheet->getResponseXml();
	foreach($respXml->result->rowset as $rowset) {
		if ($rowset['name'] != 'walletDivisions') {
			continue;
		}

		$stmt = $pdo->prepare('insert into account_key (player_entity_id, account_key_id, name) values (:player_entity_id, :account_key_id, :name)');
		$updStmt = $pdo->prepare('update account_key set name = :name where player_entity_id = :player_entity_id and account_key_id = :account_key_id and name != :name');

		foreach($rowset->row as $row) {
			$vars = array(
				':name' => (string)$row['description'],
				':player_entity_id' => $apiRow['player_entity_id'],
				':account_key_id' => (int)$row['accountKey']
			);
			$stmt->execute($vars);
			if ($stmt->rowCount() == 0) {
				$updStmt->execute($vars);
				if ($updStmt->rowCount() > 0) {
					echo "Updated existing wallet division" . PHP_EOL;
				}
			} else {
				echo "Inserted new wallet division" . PHP_EOL;
			}
			flush();
		}
	}
}

function journal($apiRow) {
	if ($apiRow['corporate']) {
		for($accountKey = 1000; $accountKey <= 1006; $accountKey++) {
			$journal = new CorpWalletJournal($apiRow['api_key'], $apiRow['api_code']);
			$journal->setAccountKey($accountKey);
			walk_journal($apiRow, $journal);
		}
	} else {
		$journal = new CharWalletJournal($apiRow['api_key'], $apiRow['api_code']);
		$journal->setCharId($apiRow['player_entity_id']);
		walk_journal($apiRow, $journal);
	}
}

function walk_journal($apiRow, $journal) {
	global $pdo;

	// use JournalWalker to get as many results as possible
	$rows = JournalWalker::walk($journal);
	if (count($journal->getErrors()) > 0) {
		echo "Could not fetch journal for key " . $apiRow['api_key'] . " entity " . $apiRow['player_entity_id'] . " because of error(s)." . PHP_EOL;
		var_dump($journal->getErrors());
		return;
	}

	$insStmt = $pdo->prepare("insert into journal_entry (player_entity_id, datestamp, type_id, first_entity_id, second_entity_id, argument_name, argument_id, argument_reason, amount, balance, tax_party_id, tax_amount, account_key_id) values (:playerEntityId, :datestamp, :refTypeId, :firstOwner, :secondOwner, :argName, :argId, :argReason, :amount, :balance, :taxOwner, :taxAmount, :accountKey)");
	$peInsStmt = $pdo->prepare("insert into player_entity values (:id, :name)");
	foreach($rows as $row) {
		$peInsStmt->bindValue(':id', $row['ownerID1']);
		$peInsStmt->bindValue(':name', $row['ownerName1']);
		$peInsStmt->execute();
		$peInsStmt->bindValue(':id', $row['ownerID2']);
		$peInsStmt->bindValue(':name', $row['ownerName2']);
		$peInsStmt->execute();

		$insStmt->bindValue(':playerEntityId', $apiRow['player_entity_id']);
		$insStmt->bindValue(':datestamp', (string)$row['date']);
		$insStmt->bindValue(':refTypeId', (int)$row['refTypeID']);
		$insStmt->bindValue(':firstOwner', (int)$row['ownerID1']);
		$insStmt->bindValue(':secondOwner', (int)$row['ownerID2']);
		$insStmt->bindValue(':argName', (string)$row['argName1']);
		$insStmt->bindValue(':argId', (int)$row['argID1']);
		$insStmt->bindValue(':argReason', (string)$row['reason']);
		$insStmt->bindValue(':amount', (float)$row['amount']);
		$insStmt->bindValue(':balance', (float)$row['balance']);
		$insStmt->bindValue(':taxOwner', (int)$row['taxReceiverID']);
		$insStmt->bindValue(':taxAmount', (float)$row['taxAmount']);
		if ($apiRow['corporate']) {
			$insStmt->bindValue(':accountKey', (int)$journal->getAccountKey());
		} else {
			$insStmt->bindValue(':accountKey', null, PDO::PARAM_INT);
		}
		$insStmt->execute();

		if ($insStmt->rowCount() > 0) {
			echo "Inserted " . $insStmt->rowCount() . " row(s) for key " . $row['refID'] . PHP_EOL;
			flush();
		}
	}
}

function orders($apiRow) {

}

function assets($apiRow) {
	global $pdo;

	if ($apiRow['corporate']) {
		$assets = new CorpAssetList($apiRow['api_key'], $apiRow['api_code']);
	} else {
		$assets = new CharAssetList($apiRow['api_key'], $apiRow['api_code']);
		$assets->setCharId($apiRow['player_entity_id']);
	}

	$assets->execute();
	if (count($assets->getErrors()) > 0) {
		echo "Could not fetch asset list " . $apiRow['account_id'] . " key " . $apiRow['api_key'] . " because of error(s)." . PHP_EOL;
		print_r($assets->getErrors());
		return;
	}

	$stmt = $pdo->prepare("insert into asset_entry (player_entity_id, type_id, station_id, container_id, flag_id, quantity) values (:playerEntityId, :typeId, :stationId, :containerId, :flagId, :quantity) returning id");
	$respXml = $assets->getResponseXml();
	walk_assets($apiRow['player_entity_id'], $stmt, $respXml->result->rowset);
}

function walk_assets($playerEntityId, $stmt, $rowset, $parentId = null) {
	foreach($rowset->row as $row) {
		$stmt->bindValue(':playerEntityId', $playerEntityId);
		$stmt->bindValue(':typeId', (int)$row['typeID']);
		if ($parentId != null) {
			$stmt->bindValue(':containerId', (int)$parentId);
			$stmt->bindValue(':stationId', null, PDO::PARAM_INT);
		} else {
			$stmt->bindValue(':containerId', null, PDO::PARAM_INT);
			$stmt->bindValue(':stationId', (int)$row['locationID']);
		}
		$stmt->bindValue(':flagId', (int)$row['flag']);
		$stmt->bindValue(':quantity', (int)$row['quantity']);
		$stmt->execute();
		$result = $stmt->fetch();

		if ($row->rowset->count() > 0) {
			foreach($row->rowset as $recurseRowset) {
				walk_assets($playerEntityId, $stmt, $recurseRowset, $result['id']);
			}
		}

		echo "Inserted " . $stmt->rowCount() . " asset entries" . PHP_EOL;
	}
}

?>
