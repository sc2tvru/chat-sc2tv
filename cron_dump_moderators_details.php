<?php
require_once 'core.php';

DumpModeratorsDetails();
DumpComplainsList();

/**
 *	получение данных по модераторам и запись их в memfs и memcache
 *	moderatorsDetails[ uid ] = array( name => '', bansCount => '' )
 *	содержит uid, имя модератора и, возможно, кол-во банов bansCount,
 *	которое устанавливается в chat.php при бане
 */
function DumpModeratorsDetails() {
	global $memcache;
	//$memcache->Delete( MODERATORS_DETAILS_MEMCACHE_KEY );
	$moderatorsDetails = $memcache->Get( MODERATORS_DETAILS_MEMCACHE_KEY );
	
	// данных в memcache нет, берем из базы
	if ( $moderatorsDetails === FALSE ) {
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
		
		if ( $queryResult === FALSE ) {
			SaveForDebug( CHAT_RUNTIME_ERROR . ' cron dump moderrators details 1' );
		}
		
		while( $moderatorDetail = $queryResult->fetch_assoc() ) {
			$moderatorsDetails[ $moderatorDetail[ 'uid' ] ][ 'name' ] =
				$moderatorDetail[ 'name' ];
		}
		SaveForDebug( 'moderatorsDetails dump from bd' );
	}
	
	$memcache->Set( MODERATORS_DETAILS_MEMCACHE_KEY, $moderatorsDetails,
		CHAT_MODERATORS_DETAILS_TTL );
	
	$dataJS = 'var moderatorsDetails = ' . json_encode( $moderatorsDetails );
	file_put_contents( CHAT_MODERATORS_DETAILS, $dataJS );
}

	
/**
 *	получение списка жалоб на баны из memcache
 */
function DumpComplainsList() {
	global $memcache;
	$complainsList = $memcache->Get( COMPLAINS_LIST_MEMCACHE_KEY );
	
	$result = array();
	
	if ( $complainsList !== FALSE ) {
		foreach( $complainsList as $banKey => $complainsForBan ) {
			if ( $complainsForBan[ 'count' ] >= COMPLAINS_NEEDED ) {
				$result[ $banKey ]['complains'] = $complainsForBan[ 'complains'];
			}
		}
	}
	
	$dataJS = 'var complainsList = ' . json_encode( $result );
	file_put_contents( CHAT_COMPLAINS_FOR_BANS, $dataJS );
}
?>