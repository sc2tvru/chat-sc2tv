<?php
require_once 'core.php';
require_once 'utils.php';
global $memcache;
$memcache->Delete( MODERATORS_DETAILS_MEMCACHE_KEY );
unlink( CHAT_MODERATORS_DETAILS );
SaveForDebug( 'cron clear moderators details' );
?>