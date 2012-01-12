<?php
require_once 'core.php';

if( !isset( $_COOKIE[ DRUPAL_SESSION ] ) || 
	preg_match( '/[^a-z\d]+/i', $_COOKIE[ DRUPAL_SESSION ] ) ) {
	exit( 'You are not logged in' );
}

global $memcache;

$memcache->Delete( $_COOKIE[ DRUPAL_SESSION ] );
$memcache->Delete( 'Chat_uid_' . $_COOKIE[ 'drupal_uid' ] . '_BanInfo' );
echo 'All done. Try to relogin.';
?>