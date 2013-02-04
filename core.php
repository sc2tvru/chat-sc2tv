<?php
/**
 * Ядро чата
 * подключаются только необходимые файлы
 * выполняется только необходимый код
*/

require_once 'chat_config.php';

ini_set('session.cookie_domain', CHAT_COOKIE_DOMAIN); 

// поскольку во многих местах нужно текущее время, его полезно вынести в константу
define( 'CURRENT_TIME', time() );
date_default_timezone_set( CHAT_TIMEZONE );
define( 'CURRENT_DATE', date( 'Y-m-d H:i:s', CURRENT_TIME ) );

require_once 'memcache.php';
require_once 'db.php';

$memcache = new ChatMemcache;

// TODO а нужно ли?
error_reporting( E_ALL );
//ini_set('max_execution_time', 5);
header('Content-type: text/html; charset=utf-8');
mb_internal_encoding( 'UTF-8' );
mb_regex_encoding( 'UTF-8' );
?>