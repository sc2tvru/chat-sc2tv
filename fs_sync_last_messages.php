<?php
date_default_timezone_set('Europe/Moscow');
require_once 'core.php';
if(!isset($_POST['key']) || $_POST['key'] !== FS_TO_CHAT_KEY){
	exit;
}

$db = new MySqlDb( CHAT_DB_HOST, CHAT_DB_NAME, CHAT_DB_USER, CHAT_DB_PASSWORD );
$queryString = 'SELECT * FROM `chat_message` WHERE `date` > "' . date('Y-m-d H:i:s', CURRENT_TIME - 30) . '" ORDER BY `id` DESC';
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
print json_encode( $messages );
?>
