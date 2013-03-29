<?php
/**
 * вспомогательные функции
*/

if ( LOG_ERRORS ) {
	set_error_handler( 'ChatErrorHandler' );
}


function ChatErrorHandler( $errno = '', $errstr = '', $errfile = '', $errline = ''  ) {
	$logFile = fopen( ERROR_FILE, 'a' );
	
	if ( flock( $logFile, LOCK_EX | LOCK_NB ) ) {
		$serverInfo = var_export( $_SERVER, true );
		
		$out = date( 'd M H:i:s', CURRENT_TIME );
		
		if ( isset( $_SERVER[ 'REMOTE_ADDR' ] ) ) {
			$out .= ' - ip ' . $_SERVER[ 'REMOTE_ADDR' ];
			
			if ( isset( $_SERVER[ 'HTTP_REFERER' ] ) ) {
				$out .= ' - ref '. $_SERVER[ 'HTTP_REFERER' ];
			}
		}
		
		$out .= "\n $errfile, line $errline, code $errno, $errstr\n\n$serverInfo\n\n";
		
		if ( count( $_POST ) ) {
			$out .= var_export( $_POST, true ) . "\n";
		}
		
		$jsonError = json_last_error();
		
		if ( $jsonError ) {
			switch ( $jsonError ) {
				case JSON_ERROR_NONE:
					$out .= 'json error: - Ошибок нет';
				break;
				case JSON_ERROR_DEPTH:
					$out .= 'json error: - Достигнута максимальная глубина стека';
				break;
				case JSON_ERROR_STATE_MISMATCH:
					$out .= 'json error: - Некорректные разряды или не совпадение режимов';
				break;
				case JSON_ERROR_CTRL_CHAR:
					$out .= 'json error: - Некорректный управляющий символ';
				break;
				case JSON_ERROR_SYNTAX:
					$out .= 'json error: - Синтаксическая ошибка, не корректный JSON';
				break;
				case JSON_ERROR_UTF8:
					$out .= 'json error: - Некорректные символы UTF-8, возможно неверная кодировка';
				break;
				default:
					$out .= 'json error: - Неизвестная ошибка';
				break;
			}
		}
		
		fwrite( $logFile, $out );
		fflush( $logFile );
		flock( $logFile, LOCK_UN );
	}
	
	fclose( $logFile );
	
	return false;
}


function SaveForDebug( $debugStr ) {
	$logFile = fopen( DEBUG_FILE, 'a' );
	
	if ( flock( $logFile, LOCK_EX | LOCK_NB ) ) {
		$serverInfo = var_export( $_SERVER, true );
		
		$str = date( 'd M H:i:s', CURRENT_TIME );
		
		if ( isset( $_SERVER[ 'REMOTE_ADDR' ] ) ) {
			$str .= ' - ip ' . $_SERVER[ 'REMOTE_ADDR' ];
			
			if ( isset( $_SERVER[ 'HTTP_REFERER' ] ) ) {
				$str .= ' - ref '. $_SERVER[ 'HTTP_REFERER' ];
			}
			
			if ( isset( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) {
				$str .= ' - ua ' . $_SERVER[ 'HTTP_USER_AGENT' ];
			}
		}
		
		$str .= "\ndebug: $debugStr\n\n$serverInfo\n\n";
		
		if ( count( $_POST ) ) {
			$str .= "\n" . var_export( $_POST, true ). "\n";
		}
		
		fwrite( $logFile, $str. "\n\n" );
		fflush( $logFile );
		flock( $logFile, LOCK_UN ); 
	}
	
	fclose( $logFile );
}

/**
 *	генерация токена
 *	@param string $str
 *	@return string 
 */
function GenerateSecurityToken( $str ) {
	// способ хэширования взят из форума и модуля Drupal vbbridge
	$salt = GenerateSalt();
	$token = md5( md5( $str ) . $salt );
	
	return $token;
}


/**
 * генерация соли
 * @param int $length длина строки
 * @return string
 */
function GenerateSalt( $length = 3 ) {
	$salt = '';
	
	for ( $i = 0; $i < $length; $i++ ) {
		$salt .= chr( rand( 32, 127 ) );
	}
	
	return $salt;
}

/**
 * загрузка статистики по модераторам из файла
 * нужна, когда значение в memcache больше недоступно
 */
function GetModeratorDetailsFromFile() {
	SaveForDebug( 'read moderatorsDetails from file' );
	
	if ( !file_exists( CHAT_MODERATORS_DETAILS ) ) {
		return FALSE;
	}
	
	$fileData = file_get_contents( CHAT_MODERATORS_DETAILS );
	// смещение 24 - js часть 'var moderatorsDetails = ' не нужна
	$jsonData = json_decode( mb_substr( $fileData, 24 ), true );
	
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		return FALSE;
	}
	
	SaveForDebug( 'read moderatorsDetails from file - success' );
	
	return $jsonData;
}
?>