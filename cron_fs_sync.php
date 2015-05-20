<?php
require_once 'chat_config.php';
require_once 'db.php';
date_default_timezone_set('Europe/Moscow');
$db = new MySqlDb( CHAT_DB_HOST, CHAT_DB_NAME, CHAT_DB_USER, CHAT_DB_PASSWORD );
$queryString = 'SELECT * FROM `chat_message` WHERE `date` > "' . date('Y-m-d H:i:s', time() - 300) . '" ORDER BY `id` DESC';
$queryResult = $db->Query( $queryString );
if ( $queryResult === FALSE ) {
	exit;
}
$messages = array();
while ( $msg = $queryResult->fetch_assoc() ) {
	$messages[] = array(
		'id' => (int)$msg['id'],
		'channel' => (int)$msg['channelId'],
		'from' => (int)$msg['uid'],
		'message' => $msg['message'],
		'date' => $msg['date']
	);
}
$dataJson = json_encode( $messages );
$channelsFile = CHAT_MEMFS_DIR . '/fs_sync.json';
file_put_contents( $channelsFile, $dataJson );
?>
