<?php
require_once 'core.php';
if(
	!isset($_POST['from']) ||
	!isset($_POST['channel_id']) || 
	(int)$_POST['channel_id'] < 0 ||
	!isset($_POST['message']) || 
	!isset($_POST['signature']) || 
	$_POST['signature'] != hash('sha512', $_POST['from'].$_POST['channel_id'].$_POST['message'].FS_TO_CHAT_KEY)
) {
	print 'Wrong input.';
	exit;
}
require_once 'chat.php';
global $memcache;
$chat = new Chat( $memcache );
$chat->SetDatabase();
$chat->GetAuthInfo($_POST['from']);
if(isset($chat->user[ 'error' ]) && $chat->user[ 'error' ] !== ''){
	print $chat->user['error'];
	exit;
}

if($res = $chat->WriteMessage($_POST['message'])){
	$chat->WriteChannelCache((int)$_POST['channel_id']);
	print $res;
	exit;
}
else {
	print 'WriteMessage false';
	exit;
}
?>