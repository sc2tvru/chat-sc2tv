<?php
require_once 'core.php';
if(!isset($_POST['channels']) || !isset($_POST['key']) || $_POST['key'] != DRUPAL_TO_CHAT_KEY) exit;
require_once 'chat.php';
global $memcache;
$chat = new Chat( $memcache );
$chat->SetDatabase();
if(!is_array($_POST['channels'])){
	$channels = array($_POST['channels']);
}
else {
	$channels = $_POST['channels'];
}
foreach($channels as $channel){
	$chat->WriteChannelCache((int)$channel);
}
?>