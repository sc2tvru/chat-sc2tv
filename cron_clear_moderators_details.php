<?php
require_once 'core.php';
require_once 'utils.php';
global $memcache;
$memcache->Delete( MODERATORS_DETAILS_MEMCACHE_KEY );
SaveForDebug( 'cron clear moderators details' );
?>