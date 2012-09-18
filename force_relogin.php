<?php
exit;
require_once 'core.php';
require_once 'utils.php';

$uid = (int)$_GET[ 'uid' ];
$banInfoMemcacheKey = 'Chat_uid_' . $uid . '_BanInfo';

$banInfo = $memcache->Get( $banInfoMemcacheKey );
var_dump( $banInfo );

$memcache->Set(
	$banInfoMemcacheKey,
	array(
		'needUpdate' => 1,
		'needRelogin' => 1
	),
	259200
);
 
$banInfo = $memcache->Get( $banInfoMemcacheKey );
var_dump( $banInfo );
?>