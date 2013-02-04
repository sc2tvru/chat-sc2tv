<?php
require_once 'core.php';
global $memcache;
$memcache->Delete( MODERATORS_DETAILS_MEMCACHE_KEY );
?>