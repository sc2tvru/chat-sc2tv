<?php
//
exit;
require_once 'core.php';
require_once 'utils.php';
/*
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
exit;
*/

global $memcache;

$memcache->Set(
	PRIME_TIME_ADVERT_FROM_STREAMER,
	'streamer test 15',
	15
);

$memcache->Set(
	PRIME_TIME_ADVERT_FROM_USER,
	'user 30 :s:notbad: :s:kawai: :s:joyful:',
	30
);

$test = $memcache->Get( PRIME_TIME_ADVERT_FROM_STREAMER );
var_dump( $test );

$test = $memcache->Get( PRIME_TIME_ADVERT_FROM_USER );
var_dump( $test );

exit;
$memcache->Delete( MODERATORS_DETAILS_MEMCACHE_KEY );

$moderatorsDetails = $memcache->Get( MODERATORS_DETAILS_MEMCACHE_KEY );
var_dump( $moderatorsDetails );

$moderatorsDetails = $memcache->Get( MODERATORS_DETAILS_MEMCACHE_KEY );

var_dump( GetModeratorDetailsFromFile() );

exit;
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