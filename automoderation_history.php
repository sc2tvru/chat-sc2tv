<?php
/**
 * Доступ к истории автомодерации
 * @author shr
 *
 */
class ChatAutomoderationHistory {
	
	private $db, $memcache;
	
	function __construct( $memcacheObject ) {
		$this->memcache = $memcacheObject;
	}
	
	/**
	 *	получение истории автомодерации
	 *	@param channelId id канала
	 *	@param startDate дата начала
	 *	@param endDate дата конца
	 *	@param userNames имена пользователей, разделенные ;
	 *	@param historyCache имя файла для сохранения кэша, если не задано, будет
	 *	использоваться 
	 */
	public function Get( $channelId, $startDate, $endDate, $userNames = '', $historyCache = ''  ) {
		$startDate = $this->PrepareDate( $startDate );
		$endDate = $this->PrepareDate( $endDate );
		
		if( $startDate == 0 || $endDate == 0 ||
			( strtotime( $endDate ) - strtotime( $startDate ) > CHAT_HISTORY_MAX_TIME_DIFFERENCE ) ) {
			$result = array(
				'messages' => '',
				'error' => CHAT_HISTORY_CHECK_PARAMS
			);
			return $result;
		}
		
		$options = '';
		$userNamesCopy = '';
		
		if ( $userNames != '' ) {
			$userNames = $this->PrepareNickNames( $userNames );
			$userNamesCopy = $userNames;
			$userNames = explode( ';', $userNames );
			
			if ( count( $userNames ) > 0 ) {
				$options = '(';
				$operator = '';
				
				$this->db = GetDb();
				
				foreach ( $userNames as $currentNick ) {
					list( $currentNick ) = $this->db->PrepareParams( $currentNick );
					$options .= $operator .' mu.name = "'. $currentNick .'"';
					if ( $operator === '' ) {
						$operator = ' OR ';
					}
				}
				
				$options .= ') AND ';
			}
		}
		
		$channelOptions = '';
		if ( !( $channelId == '' || $channelId == 'all' ) ) {
			$channelId = (int)$channelId;
			$options .= 'channelId = "'. $channelId .'" AND ';
		}
		
		$queryString = '
			SELECT chat_ban.id, ru.uid as bannedUserId, ru.name as userName,
			chat_ban.banExpirationTime, chat_ban.banMessageId, chat_ban.banTime,
			chat_ban.banDuration, chat_ban.banModificationUserId,
			chat_message.message as bannedForMessage, chat_ban.status as chatBanStatus,
			chat_ban.banReasonId, mu.name as moderatorName, banModificationReason
			FROM chat_ban
			INNER JOIN users AS ru ON ru.uid = chat_ban.uid
			INNER JOIN users AS mu ON mu.uid = chat_ban. moderatorId
			LEFT OUTER JOIN chat_message on chat_message.id = banMessageId
			WHERE '. $options .'
			banTime BETWEEN "'. strtotime( $startDate ) .'" AND "'. strtotime( $endDate ).'"';
		//echo '<!--' . $queryString . '-->';
		
		$this->db = GetDb();
		$queryResult = $this->db->Query( $queryString );
		
		if ( $queryResult === false ) {
			SaveForDebug( $queryResult );
			$result = array(
				'messages' => '',
				'error' => CHAT_RUNTIME_ERROR . ' am history 1'
			);
			return $result;
		}
		
		$messages = array();

		while( $msg = $queryResult->fetch_assoc() ) {
			$messages[] = $msg;
		}
		
		$dataJson = json_encode( array( 'messages' => $messages ) );
		
		if ( $historyCache == '' ) {
			$historyCache = $channelId . '_' . $startDate . '_' . $endDate
				. '_' . $userNamesCopy . '.json';
			$historyCache = preg_replace( '#[\s]+#uis', '_',  $historyCache );
		}
		
		$historyCache = CHAT_AUTOMODERATION_HISTORY_MEMFS_DIR . $historyCache;
		file_put_contents( $historyCache, $dataJson );
		touch( $historyCache );
		
		$result = array(
			'messages' => $messages,
			'error' => ''
		);
		
		return $result;
	}
	
	
	/**
	 *	получение данных по модераторам и запись их в memfs
	 *	@return array массив с ключами moderatorsDetails и error
	 *	moderatorsDetails[ uid ] = array( name => '', bansCount => '' )
	 *	содержит uid, имя модератора и, возможно, кол-во банов bansCount,
	 *	которое устанавливается в chat.php при бане
	 */
	public function GetModeratorsDetails() {
		$modetatorsDetails = $this->memcache->Get( MODERATORS_DETAILS_MEMCACHE_KEY );
		
		// данных нет, получаем из базы
		if ( $modetatorsDetails === false ) {
			$queryString = '
				SELECT users.uid, name
				FROM users
				INNER JOIN users_roles using(uid)
				WHERE rid IN (3,4,5)
				AND status = 1
				GROUP BY users.uid';
			
			$this->db = GetDb();
			$queryResult = $this->db->Query( $queryString );
			
			if ( $queryResult === false ) {
				SaveForDebug( $queryResult );
				$result = array(
					'moderatorsDetails' => '',
					'error' => CHAT_RUNTIME_ERROR . ' am history 2'
				);
				return $result;
			}
			
			while( $moderatorDetail = $queryResult->fetch_assoc() ) {
				$modetatorsDetails[ $moderatorDetail[ 'uid' ] ][ 'name' ] =
					$moderatorDetail[ 'name' ];
			}
			
			$this->memcache->Set( MODERATORS_DETAILS_MEMCACHE_KEY, $modetatorsDetails,
				CHAT_MODERATORS_DETAILS_TTL );
		}
		
		$dataJson = json_encode( array( 'moderatorsDetails' => $modetatorsDetails ) );
		
		file_put_contents( CHAT_MODERATORS_DETAILS, $dataJson );
		touch( CHAT_MODERATORS_DETAILS );
		
		$result = array(
			'moderatorsDetails' => $modetatorsDetails,
			'error' => ''
		);
		return $result;
	}
	
	
	
	/**
	 *	получение списка жалоб на баны из memcache
	 */
	public function GetComplainsList() {
		$complainsList = $this->memcache->Get( COMPLAINS_LIST_MEMCACHE_KEY );
		
		$result = array();
		
		if ( $complainsList != false ) {
			foreach( $complainsList as $banKey => $complainsForBan ) {
				if ( $complainsForBan[ 'count' ] >= COMPLAINS_NEEDED ) {
					$result[] = $complainsForBan[ 'complains'];
				}
			}
		}
		
		$dataJson = json_encode( array( 'complainsList' => $result ) );
		
		file_put_contents( CHAT_COMPLAINS_FOR_BANS, $dataJson );
		touch( CHAT_COMPLAINS_FOR_BANS );
		
		return $result;
	}
	
	
	private function PrepareNickNames( $userNames ){
		$userNames = urldecode( $userNames );
		// удаление на всякий случай символов, кроме разрешенных и whitespaces
		$userNames = preg_replace( '/[^\x20-\x7E\x{400}-\x{45F}\x{490}\x{491}\x{207}\x{239}]+/us', '',  $userNames );
		$userNames = preg_replace( '#[\s]+#uis', ' ',  $userNames );
		return $userNames;
	}
	
	
	private function PrepareDate( $date ){
		// удаление на всякий случай символов, кроме разрешенных и whitespaces
		$date = preg_replace( '#[^-:\d\s]+#uis', '',  $date );
		$date = preg_replace( '#[\s]+#uis', ' ',  $date );
		
		// со страницы истории дата запрашивается без секунд
		return $date . ':00';
	}
}
?>