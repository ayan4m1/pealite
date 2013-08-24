<?php
/**
 * EVE API Market Data Fetch
 * by ayan4m1 <andrew@bulletlogic.com>
 *
 * data courtesy eve-marketdata.com
 */

$start_time = microtime(true);

$pdo = new PDO('pgsql:host=localhost;dbname=eve', 'eve', 'omghax');

overwrite_data($pdo, 'items_buying_jita', 'items_buying');
overwrite_data($pdo, 'items_selling_jita', 'items_selling');
merge_data($pdo, 'items_history_theforge_90', 'items_history');

$total_time = number_format(microtime(true) - $start_time, 4);
echo "Ended run in ${total_time}s" . PHP_EOL;

/*
 *
 * FUNCTIONS BELOW
 *
 */

function get_url($url) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$ret = gzdecode(curl_exec($ch));
	curl_close($ch);
	return $ret;
}

function get_inserts($url, $table) {
	$data = get_url("http://eve-marketdata.com/developers/mysql_${url}.txt.gz");
	//$data = get_url("http://localhost/mysql_${url}.txt.gz");
	$matches = FALSE;
	preg_match_all("/^INSERT INTO `$table` VALUES .*;$/m", $data, $matches);
	return $matches[0];
}

function overwrite_data($pdo, $url, $table) {
	$pdo->beginTransaction();
	echo "Truncating table $table..." . PHP_EOL;
	$pdo->exec("truncate table $table");

	$inserts = get_inserts($url, $table);
	if (count($inserts) > 0) {
		$raw_count = 0;
		foreach($inserts as $insert) {
			$raw_new = $pdo->exec(str_replace("`$table`", "\"$table\"", $insert));
			if ($raw_new === false) {
				$pdo->rollBack();
				echo "Rolled back due to error" . PHP_EOL;
				var_dump($pdo->errorInfo());
				return;
			} else {
				$raw_count += $raw_new;
			}
		}
	}

	$pdo->commit();
	echo "Inserted $raw_count rows" . PHP_EOL;
}

function merge_data($pdo, $url, $table) {
	$pdo->beginTransaction();
	$pdo->exec("set temp_buffers = 200");
	$pdo->exec("create temporary table ${table}_temp (like $table) on commit drop");

	$inserts = get_inserts($url, $table);
	if (count($inserts) > 0) {
		$raw_count = 0;
		foreach($inserts as $insert) {
			$raw_new = $pdo->exec(str_replace("`$table`", "\"${table}_temp\"", $insert));
			if ($raw_new === false) {
				$pdo->rollBack();
				echo "Rolled back due to error" . PHP_EOL;
				var_dump($pdo->errorInfo());
				return;
			} else {
				$raw_count += $raw_new;
			}
		}
	} else {
		return;
	}

	$delete_count = $pdo->exec(<<<EndSQL
delete from $table dst
using (
  select
    region_id,
    type_id,
    date,
    created
  from ${table}_temp
) src
where
  dst.region_id = src.region_id
  and dst.type_id = src.type_id
  and dst.date = src.date
  and dst.created != src.created
EndSQL
	);

	$insert_count = $pdo->exec(<<<EndSQL
insert into $table (
  select * from ${table}_temp
  except
  select * from $table where date > now() - interval '100' day
)
EndSQL
	);

	$pdo->commit();
	echo "Found $raw_count rows, removed $delete_count stale rows and inserted $insert_count rows" . PHP_EOL;
}
