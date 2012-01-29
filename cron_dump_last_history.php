<?php
require_once 'core.php';
require_once 'utils.php';
require_once 'history.php';

// со страницы истории дата запрашивается без секунд
$dateFormat = 'Y-m-d H:i';

$history = new ChatHistory();

// по всем каналам за последний час
$startDate = date( $dateFormat, CURRENT_TIME - 3600 );
$endDate = date( $dateFormat, CURRENT_TIME );

$result = $history->Get( '', $startDate, $endDate, '', 'last.json' );
?>