<?php
/*
 * выгрузка stream_id и stream_title из базы в json для списка каналов в истории
 */
require_once 'chat_config.php';
require_once 'db.php';

$db = new MySqlDb( CHAT_DB_HOST, CHAT_DB_NAME, CHAT_DB_USER, CHAT_DB_PASSWORD );

$queryStreamChannel = 'SELECT `node`.`nid` AS `stream_id`,
`node`.`title` AS `stream_title`
FROM `content_type_stream_channel`, `node`, `users`
WHERE `users`.`uid` = `node`.`uid`
AND `content_type_stream_channel`.`nid`=`node`.`nid`
AND `node`.`status` = 1
ORDER BY `node`.`changed` DESC LIMIT 100';

$queryPrimeTimeStreamNoRubric = 'SELECT `node`.`nid` AS `stream_id`,
`node`.`title` AS `stream_title`
FROM `content_type_prime_stream`, `node`, `users`
WHERE `users`.`uid` = `node`.`uid`
AND `content_type_prime_stream`.`nid`=`node`.`nid`
AND NOW() >`content_type_prime_stream`.`field_prime_time_value`
AND `node`.`status` = 1
AND `content_type_prime_stream`.`field_prime_rubric_value` = 0
ORDER BY `node`.`created` DESC LIMIT 20';

$data[] = array(
	'channelId' => '0',
	'channelTitle' => 'main'
);
$data[] = array(
	'channelId' => PRIME_TIME_CHANNEL_ID,
	'channelTitle' => PRIME_TIME_CHANNEL_TITLE
);

$data = array_merge(
	$data,
	GetDataByQuery( $queryStreamChannel ),
	GetDataByQuery( $queryPrimeTimeStreamNoRubric )
);

$dataJson = json_encode( array( 'channel' => $data ) );
$channelsFile = CHAT_MEMFS_DIR . '/channels_history.json';
file_put_contents( $channelsFile, $dataJson );

function GetDataByQuery( $queryString ) {
	global $db;
	$queryResult = $db->Query( $queryString );
	
	if ( $queryResult === false ) {
		return false;
	}

	$data = array();
	$maxLength = 100;
	
	while ( $channel = $queryResult->fetch_assoc() ) {
		if ( mb_strlen( $channel[ 'stream_title' ] ) > $maxLength ) {
			$channel[ 'stream_title' ] = mb_substr( $channel[ 'stream_title' ], 0, $maxLength ).'...';
		}
		$data[] = array(
			'channelId' => $channel[ 'stream_id' ],
			'channelTitle' => $channel[ 'stream_title' ]
		);
	}
	return $data;
}
?>