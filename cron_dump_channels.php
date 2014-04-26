<?php
/*
 * выгрузка stream_id и stream_title из базы в channels.json
 */
require_once 'chat_config.php';
require_once 'db.php';

$db = new MySqlDb( CHAT_DB_HOST, CHAT_DB_NAME, CHAT_DB_USER, CHAT_DB_PASSWORD );

$queryPrimeTimeStream = 'SELECT `node`.`nid` AS `stream_id`,
`node`.`title` AS `stream_title`,
`node`.`created` AS `timeCreated`,
`users`.`name` as `streamer_name`,
`content_type_prime_stream`.`field_prime_rubric_value` as `rubric`
FROM `content_type_prime_stream`, `node`, `users`
WHERE `content_type_prime_stream`.`field_prime_is_over_value`=0
AND `users`.`uid` = `node`.`uid`
AND `content_type_prime_stream`.`nid`=`node`.`nid`
AND UNIX_TIMESTAMP(NOW()) > (
	UNIX_TIMESTAMP(`content_type_prime_stream`.`field_prime_time_value`) +
	`content_type_prime_stream`.`field_prime_time_offset`
)
AND `node`.`status` = 1
ORDER BY `timeCreated` DESC';

$queryStreamChannel = 'SELECT `node`.`nid` AS `stream_id`,
`node`.`title` AS `stream_title`,
`node`.`created` AS `timeCreated`,
`users`.`name` as `streamer_name`
FROM `content_type_stream_channel`, `node`, `users`
WHERE `content_type_stream_channel`.`field_stream_channel_status_value`=1
AND `users`.`uid` = `node`.`uid`
AND `content_type_stream_channel`.`nid`=`node`.`nid`
ORDER BY `timeCreated` DESC';

$data[] = array(
	'channelId' => '0',
	'channelTitle' => 'main'
);

$data = array_merge(
	$data,
	GetDataByQuery( $queryPrimeTimeStream, $isPrimeTimeQuery = TRUE ),
	GetDataByQuery( $queryStreamChannel ),
);

$dataJson = json_encode( array( 'channel' => $data ) );
$channelsFile = CHAT_MEMFS_DIR . '/channels.json';
file_put_contents( $channelsFile, $dataJson );

function GetDataByQuery( $queryString, $isPrimeTimeQuery = FALSE ) {
	global $db;
	$queryResult = $db->Query( $queryString );
	
	if ( $queryResult === false ) {
		return false;
	}
	
	$data = array();
	$maxLength = 100;
	$primeTimeStreamsCount = 0;
	
	while ( $channel = $queryResult->fetch_assoc() ) {
		if ( mb_strlen( $channel[ 'stream_title' ] ) > $maxLength ) {
			$channel[ 'stream_title' ] = mb_substr( $channel[ 'stream_title' ], 0,
				$maxLength ) . '...';
		}
		if ( $isPrimeTimeQuery && $channel[ 'rubric' ] > 0
			&& $primeTimeStreamsCount < PRIME_TIME_STREAMS_AT_ONE_TIME ) {
			$primeTimeStreamsCount++;
			$data[] = array(
				'channelId' => PRIME_TIME_CHANNEL_ID,
				'channelTitle' => $channel[ 'stream_title' ],
				'streamerName' => $channel[ 'streamer_name' ]
			);
		}
		else {
			$data[] = array(
				'channelId' => $channel[ 'stream_id' ],
				'channelTitle' => $channel[ 'stream_title' ],
				'streamerName' => $channel[ 'streamer_name' ]
			);
		}
	}
	
	return $data;
}
?>