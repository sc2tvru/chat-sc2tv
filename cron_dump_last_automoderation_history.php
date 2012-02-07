<?php
require_once 'core.php';
require_once 'utils.php';
require_once 'automoderation_history.php';

// со страницы истории дата запрашивается без секунд
$dateFormat = 'Y-m-d H:i';

global $memcache;
$history = new ChatAutomoderationHistory( $memcache );

// по всем каналам
$startDate = date( $dateFormat, CURRENT_TIME - 3600*6 );
$endDate = date( $dateFormat, CURRENT_TIME );

$result = $history->Get( '', $startDate, $endDate, '', '', false, 'last.json' );
?>