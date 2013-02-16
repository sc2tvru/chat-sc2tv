<?php
//
exit;
require_once 'core.php';
require_once 'utils.php';

if( empty( $_COOKIE[ DRUPAL_SESSION ] ) || preg_match( '/[^a-z\d]+/i', $_COOKIE[ DRUPAL_SESSION ] ) ) {
	exit;
}

$drupalSession = $_COOKIE[ DRUPAL_SESSION ];
$chatAuthMemcacheKey = 'ChatUserInfo_' . $drupalSession;

$memcacheAuthInfo = $memcache->Get( $chatAuthMemcacheKey );
var_dump( $memcacheAuthInfo );

$memcache->Delete( $chatAuthMemcacheKey );

$memcacheAuthInfo = $memcache->Get( $chatAuthMemcacheKey );
var_dump( $memcacheAuthInfo );

exit;
// relogin banned
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