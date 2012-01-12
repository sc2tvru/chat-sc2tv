<?php
/**
 * получение переменной для работы с базой либо ее создание в случае отсутствия
 */
function GetDb(){
	global $db;
	if ( !isset( $db ) ) {
		$db = new MySqlDb( CHAT_DB_HOST, CHAT_DB_NAME, CHAT_DB_USER, CHAT_DB_PASSWORD );
	}
	return $db;
}

/**
 * класс для работы с MySQL 
 *
 */
class MySqlDb {
	public $mysqli;
	
	public function __construct( $dbHost, $dbName, $dbUser, $dbPassword ) {
		$this->mysqli = new mysqli( $dbHost, $dbUser, $dbPassword, $dbName );
		
		if ( !$this->mysqli->connect_error ) {
			$this->mysqli->query( 'SET NAMES utf8' );
			return $this->mysqli;
		}
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