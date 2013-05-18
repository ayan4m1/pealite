<?php
/*
 * EVE API Market Fetch
 * 
 * by ayan4m1 <andrew@bulletlogic.com>
 */ 

// you are free to load classes however you like
// this script assumes you use Composer, so change this if not
include('vendor/autoload.php');

use ayan4m1\pealite\calls\CharWalletJournal;
use ayan4m1\pealite\calls\EveRefTypes;
use ayan4m1\pealite\helpers\JournalWalker;

$start_time = microtime(true);

$pdo = new PDO('mysql:host=localhost;dbname=eve', 'root', 'wi9NNYara');

$stmt = $pdo->prepare('select ak.account_id, ak.api_key, ak.api_code, ak.player_entity_id from api_key ak join player_entity pe on ak.player_entity_id = pe.id where pe.is_character = 1 order by ak.account_id, ak.last_check');
$stmt->execute();
foreach($stmt->fetchAll() as $apiRow) {
	$journal = new CharWalletJournal($apiRow['api_key'], $apiRow['api_code']);
	$journal->setCharId($apiRow['player_entity_id']);
	$rows = JournalWalker::fetchAll($journal);
	if (count($journal->getErrors()) > 0) {
		echo "Could not fetch journal " . $apiRow['account_id'] . " key " . $apiRow['api_key'] . " because of error(s)." . PHP_EOL;
		print_r($journal->getErrors());
		continue;
	}

	$insStmt = $pdo->prepare("insert into journal_entry values (:refId, :accountId, :date, :refTypeId, :firstOwner, :secondOwner, :argName, :argId, :argReason, :amount, :balance, :taxOwner, :taxAmount)");
	$peInsStmt = $pdo->prepare("insert ignore into player_entity values (:id, :name, true)");
	foreach($rows as $row) {
		$peInsStmt->bindValue(':id', $row['ownerID1']);
		$peInsStmt->bindValue(':name', $row['ownerName1']);
		$peInsStmt->execute();
		$peInsStmt->bindValue(':id', $row['ownerID2']);
		$peInsStmt->bindValue(':name', $row['ownerName2']);
		$peInsStmt->execute();	

		$insStmt->bindValue(':accountId', $apiRow['account_id'], PDO::PARAM_INT);
		$insStmt->bindValue(':refId', $row['refID'], PDO::PARAM_INT);
		$insStmt->bindValue(':date', $row['date']);
		$insStmt->bindValue(':refTypeId', $row['refTypeID'], PDO::PARAM_INT);
		$insStmt->bindValue(':firstOwner', $row['ownerID1'], PDO::PARAM_INT);
		$insStmt->bindValue(':secondOwner', $row['ownerID2'], PDO::PARAM_INT);
		$insStmt->bindValue(':argName', $row['argName1'], PDO::PARAM_STR);
		$insStmt->bindValue(':argId', $row['argID1'], PDO::PARAM_INT);
		$insStmt->bindValue(':argReason', $row['reason'], PDO::PARAM_STR);
		$insStmt->bindValue(':amount', $row['amount']);
		$insStmt->bindValue(':balance', $row['balance']);
		$insStmt->bindValue(':taxOwner', $row['taxReceiverID']);
		$insStmt->bindValue(':taxAmount', $row['taxAmount']);
		$insStmt->execute();
		echo "Inserted " . $insStmt->rowCount() . " row(s) for key " . $row['refID'] . PHP_EOL;
	}
}

echo "Ended run in " . number_format(microtime(true) - $start_time, 4) . "s" . PHP_EOL;

?>
