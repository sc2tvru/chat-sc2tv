<?php
require_once 'core.php';

DumpModeratorsDetails();
DumpComplainsList();

/**
 *	получение данных по модераторам и запись их в memfs и memcache
 *	@return array массив с ключами moderatorsDetails и error
 *	moderatorsDetails[ uid ] = array( name => '', bansCount => '' )
 *	содержит uid, имя модератора и, возможно, кол-во банов bansCount,
 *	которое устанавливается в chat.php при бане
 */
function DumpModeratorsDetails() {
	global $memcache;
	//$memcache->Delete( MODERATORS_DETAILS_MEMCACHE_KEY );
	$moderatorsDetails = $memcache->Get( MODERATORS_DETAILS_MEMCACHE_KEY );
	
	// данных в memcache нет, проверяем файл
	if ( $moderatorsDetails === false ) {
		if ( file_exists( CHAT_MODERATORS_DETAILS ) ) {
			$moderatorsData = file_get_contents( CHAT_MODERATORS_DETAILS );
			if ( $moderatorsData != '' ) {
				$moderatorsData = substr( $moderatorsData, 21, mb_strlen( $moderatorsData ) - 22 );
				$moderatorsDetails = json_decode( $moderatorsData, TRUE );
			}
		}
		else {
			//получаем из базы
			$queryString = '
				SELECT users.uid, name
				FROM users
				INNER JOIN users_roles using(uid)
				WHERE rid IN (3,4,5)
				AND status = 1
				GROUP BY users.uid';
			
			$db = GetDb();
			$queryResult = $db->Query( $queryString );
			
			if ( $queryResult === false ) {
				SaveForDebug( $queryResult );
				$result = array(
					'moderatorsDetails' => '',
					'error' => CHAT_RUNTIME_ERROR . ' am history 2'
				);
				return $result;
			}
			
			while( $moderatorDetail = $queryResult->fetch_assoc() ) {
				$moderatorsDetails[ $moderatorDetail[ 'uid' ] ][ 'name' ] =
					$moderatorDetail[ 'name' ];
			}
		}
	}
	
	$memcache->Set( MODERATORS_DETAILS_MEMCACHE_KEY, $moderatorsDetails,
		CHAT_MODERATORS_DETAILS_TTL );
	
	$dataJson = json_encode( $moderatorsDetails );
	$dataJS = 'var moderatorsDetails = ' . $dataJson;
	
	file_put_contents( CHAT_MODERATORS_DETAILS, $dataJS );
}

	
/**
 *	получение списка жалоб на баны из memcache
 */
function DumpComplainsList() {
	global $memcache;
	$complainsList = $memcache->Get( COMPLAINS_LIST_MEMCACHE_KEY );
	
	$result = array();
	
	if ( $complainsList != false ) {
		foreach( $complainsList as $banKey => $complainsForBan ) {
			if ( $complainsForBan[ 'count' ] >= COMPLAINS_NEEDED ) {
				$result[ $banKey ]['complains'] = $complainsForBan[ 'complains'];
			}
		}
	}
	
	$dataJson = json_encode( $result );
	$dataJS = 'var complainsList = ' . $dataJson;
	
	file_put_contents( CHAT_COMPLAINS_FOR_BANS, $dataJS );
}
?>