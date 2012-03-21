<?php
/**
 * получение переменной для работы с базой либо ее создание в случае отсутствия
 */
function GetDb(){
	global $db;
	if ( !isset( $db ) ) {
		$db = new MySqlDb( CHAT_DB_HOST, CHAT_DB_NAME, CHAT_DB_USER, CHAT_DB_PASSWORD, CHAT_DB_CONNECT_TIMEOUT );
	}
	return $db;
}

/**
 * класс для работы с MySQL 
 *
 */
class MySqlDb {
	public $mysqli;
	
	public function __construct( $dbHost, $dbName, $dbUser, $dbPassword, $dbConnectTimeout = 5 ) {
		$this->mysqli = mysqli_init();
		
		if ( !$this->mysqli ) {
			die( 'mysqli_init failed' );
		}
		
		if ( !$this->mysqli->options( MYSQLI_OPT_CONNECT_TIMEOUT, $dbConnectTimeout ) ) {
			die( 'Setting MYSQLI_OPT_CONNECT_TIMEOUT failed' );
		}
		
		if ( !$this->mysqli->real_connect( $dbHost, $dbUser, $dbPassword, $dbName ) ) {
			die( 'mysqli Connect Error' );
		}
		
		$this->mysqli->query( 'SET NAMES utf8' );
		return $this->mysqli;
	}


	public function Query( $queryString ) {
		$queryString = trim( $queryString );
		$result = $this->mysqli->query( $queryString );
		if ( $result ) {
			return $result;
		}
	}


	public function PrepareParams() {
		$params = func_get_args();
		
		/*
		if ( get_magic_quotes_gpc() ) {
			$params = array_map( 'stripslashes', $params );
		}*/
		
		foreach ( $params as $param ) {
			$param = trim( $param );
			// TODO php 5.4.0 добавить ENT_SUBSTITUTE ?
			$param = htmlspecialchars( $param, ENT_QUOTES, 'UTF-8' );
			$param = $this->mysqli->real_escape_string( $param );
			$cleanParams[] = $param;
		}
		
		return $cleanParams;
	}
}
?>