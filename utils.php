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
		
		$out = date( 'd M H:i:s', CURRENT_TIME ).' - ip '.$_SERVER[ 'REMOTE_ADDR' ]
			.' - ref '.$_SERVER[ 'HTTP_REFERER' ]."\n $errfile, line $errline, code $errno, $errstr\n\n$serverInfo\n\n";
		
		if ( count( $_POST ) ) {
			$out .= var_export( $_POST, true );
		}
		
		fwrite( $logFile, $out );
		fflush( $logFile );
		flock( $logFile, LOCK_UN );
	}
	
	fclose( $logFile );
	
	return false;
}


function SaveForDebug( $str ) {
	$logFile = fopen( DEBUG_FILE, 'a' );
	
	if ( flock( $logFile, LOCK_EX | LOCK_NB ) ) {
		$str = date( 'd M H:i:s', CURRENT_TIME )
			. ' - '. $_SERVER[ 'REMOTE_ADDR' ]
			. ' - ' . $_SERVER[ 'HTTP_USER_AGENT' ]
			. ' - ref '. $_SERVER[ 'HTTP_REFERER' ]. "\n" . $str;
		
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
?>