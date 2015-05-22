<?php
date_default_timezone_set('Europe/Moscow');
require_once 'core.php';
if(!isset($_POST['key']) || $_POST['key'] !== FS_TO_CHAT_KEY){
	exit;
}
global $memcache;
$memcache_key = 'fs_sync_last_time';
$last_time = $memcache->Get( $memcache_key );	
if ( $last_time === FALSE ) {
	$last_time = CURRENT_TIME - 300;
}
$db = new MySqlDb( CHAT_DB_HOST, CHAT_DB_NAME, CHAT_DB_USER, CHAT_DB_PASSWORD );
$queryString = 'SELECT * FROM `chat_message` WHERE `date` > "' . date('Y-m-d H:i:s', $last_time) . '" ORDER BY `id` DESC';
$queryResult = $db->Query( $queryString );
if ( $queryResult === FALSE ) {
	exit;
}
if($memcache->memcache->replace($memcache_key, CURRENT_TIME, false, 300) === FALSE){
	$memcache->Set(
		$memcache_key,
		CURRENT_TIME,
		300
	);
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
print json_encode( $messages );
?>
