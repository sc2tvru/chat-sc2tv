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
	 *	@param int channelId id канала
	 *	@param string startDate дата начала
	 *	@param string endDate дата конца
	 *	@param string userNames имена модераторов, разделенные ;
	 *	@param string bannedNickNames имена забаненных пользователей, разделенные ;
	 *	@param boolean isModeratorRequest флаг true, если запрос от модератора, иначе false
	 *	@param string historyCache имя файла для сохранения кэша, если не задано, будет
	 *	использоваться last.json
	 */
	public function Get( $channelId, $startDate, $endDate, $userNames = '', $bannedNickNames = '', $isModeratorRequest = false, $historyCache = ''  ) {
		$startDate = $this->PrepareDate( $startDate );
		$endDate = $this->PrepareDate( $endDate );
		
		if ( $isModeratorRequest == true ) {
			$timeDifference = CHAT_HISTORY_MAX_TIME_DIFFERENCE_MODERATOR;
		}
		else {
			$timeDifference = CHAT_HISTORY_MAX_TIME_DIFFERENCE;
		}
		
		if ( $startDate == 0 || $endDate == 0 ||
			( strtotime( $endDate ) - strtotime( $startDate ) > $timeDifference ) ) {
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
					
					// если длина логина больше максимальной, прислали что-то не то
					if ( mb_strlen( $currentNick ) > CHAT_MAX_USERNAME_LENGTH ) {
						SaveForDebug( $userNamesCopy ."\n\n". $currentNick );
						$result = array(
							'messages' => '',
							'error' => CHAT_USERNAME_TOO_LONG
						);
						return $result;
					}
					
					$options .= $operator .' mu.name = "'. $currentNick .'"';
					if ( $operator === '' ) {
						$operator = ' OR ';
					}
				}
				
				$options .= ') AND ';
			}
		}
		
		$bannedNickNamesCopy = '';
		
		if ( $bannedNickNames != '' ) {
			$bannedNickNames = $this->PrepareNickNames( $bannedNickNames );
			$bannedNickNamesCopy = $bannedNickNames;
			$bannedNickNames = explode( ';', $bannedNickNames );
			
			if ( count( $bannedNickNames ) > 0 ) {
				$options .= '(';
				$operator = '';
				
				$this->db = GetDb();
				
				foreach ( $bannedNickNames as $currentNick ) {
					list( $currentNick ) = $this->db->PrepareParams( $currentNick );
					
					// если длина логина больше максимальной, прислали что-то не то
					if ( mb_strlen( $currentNick ) > CHAT_MAX_USERNAME_LENGTH ) {
						SaveForDebug( $userNamesCopy ."\n\n". $currentNick );
						$result = array(
							'messages' => '',
							'error' => CHAT_USERNAME_TOO_LONG
						);
						return $result;
					}
					
					$options .= $operator .' ru.name = "'. $currentNick .'"';
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
				. '_' . $userNamesCopy . '_' . $bannedNickNamesCopy . '.json';
			$historyCache = preg_replace( '#[\s]+#uis', '_',  $historyCache );
		}
		
		$historyCache = CHAT_AUTOMODERATION_HISTORY_MEMFS_DIR . $historyCache;
		file_put_contents( $historyCache, $dataJson );
		
		$historyCacheGz = $historyCache . '.gz';
		$historyCacheGzFile = gzopen( $historyCacheGz, 'w' );
		gzwrite( $historyCacheGzFile, $dataJson );
		gzclose( $historyCacheGzFile );
		
		$result = array(
			'messages' => $messages,
			'error' => ''
		);
		
		return $result;
	}
	
	
	/**
	 *	получение данных по модераторам и запись их в memfs и memcache
	 *	@return array массив с ключами moderatorsDetails и error
	 *	moderatorsDetails[ uid ] = array( name => '', bansCount => '' )
	 *	содержит uid, имя модератора и, возможно, кол-во банов bansCount,
	 *	которое устанавливается в chat.php при бане
	 */
	public function GetModeratorsDetails() {
		//$this->memcache->Delete( MODERATORS_DETAILS_MEMCACHE_KEY );
		$moderatorsDetails = $this->memcache->Get( MODERATORS_DETAILS_MEMCACHE_KEY );
		
		// данных в memcache нет, проверяем файл
		if ( $moderatorsDetails === false ) {
			if ( file_exists( CHAT_MODERATORS_DETAILS ) ) {
				$moderatorsData = file_get_contents( CHAT_MODERATORS_DETAILS );
				if ( $moderatorsData != '' ) {
					$moderatorsData = substr( $moderatorsData, 21, mb_strlen( $moderatorsData ) - 22 );
					$moderatorsDetails = json_decode( $moderatorsData, TRUE );
					SaveForDebug( var_export( $moderatorsDetails, true ) );
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
					$moderatorsDetails[ $moderatorDetail[ 'uid' ] ][ 'name' ] =
						$moderatorDetail[ 'name' ];
				}
			}
		}
		
		$this->memcache->Set( MODERATORS_DETAILS_MEMCACHE_KEY, $moderatorsDetails,
			CHAT_MODERATORS_DETAILS_TTL );
		
		$dataJson = json_encode( array( 'moderatorsDetails' => $moderatorsDetails ) );
		
		file_put_contents( CHAT_MODERATORS_DETAILS, $dataJson );
		
		$moderatorsDetailsGz = CHAT_MODERATORS_DETAILS . '.gz';
		$moderatorsDetailsGzFile = gzopen( $moderatorsDetailsGz, 'w' );
		gzwrite( $moderatorsDetailsGzFile, $dataJson );
		gzclose( $moderatorsDetailsGzFile );
		
		$result = array(
			'moderatorsDetails' => $moderatorsDetails,
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
		
		$complainsGz = CHAT_COMPLAINS_FOR_BANS . '.gz';
		$complainsGzFile = gzopen( $complainsGz, 'w' );
		gzwrite( $complainsGzFile, $dataJson );
		gzclose( $complainsGzFile );
		
		return $result;
	}
	
	
	private function PrepareNickNames( $userNames ){
		$userNames = urldecode( $userNames );
		// удаление на всякий случай символов, кроме разрешенных и whitespaces
		$userNames = preg_replace( '/[^\x20-\x7E\x{400}-\x{45F}\x{490}\x{491}\x{207}\x{239}]+|[\/]+/us', '',  $userNames );
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