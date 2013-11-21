<?php
/*
 * выгрузка stream_id и stream_title из базы в json для модераторов чата 
 */
require_once 'chat_config.php';
require_once 'db.php';

$db = new MySqlDb( CHAT_DB_HOST, CHAT_DB_NAME, CHAT_DB_USER, CHAT_DB_PASSWORD );

$queryStream = 'SELECT `node`.`nid` AS `stream_id`, `node`.`title` AS `stream_title`, `node`.`created` AS `timeCreated`
FROM `content_type_stream`, `node`, `users`
WHERE `users`.`uid` = `node`.`uid`
AND `content_type_stream`.`nid`=`node`.`nid`
AND NOW() >`content_type_stream`.`field_stream_time_value`
AND `node`.`status` = 1
ORDER BY `timeCreated` DESC LIMIT 20';

$queryUserStream = 'SELECT `node`.`nid` AS `stream_id`, `node`.`title` AS `stream_title`, `node`.`created` AS `timeCreated`
FROM `content_type_userstream`, `node`, `users`
WHERE `users`.`uid` = `node`.`uid`
AND `content_type_userstream`.`nid`=`node`.`nid`
ORDER BY `timeCreated` DESC LIMIT 20';

$data[] = array(
	'channelId' => '0',
	'channelTitle' => 'main'
);
$data[] = array(
	'channelId' => PRIME_CHANNEL_ID,
	'channelTitle' => 'Prime Time'
);

$data = array_merge(
	$data,
	GetDataByQuery( $queryStream ),
	GetDataByQuery( $queryUserStream )
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