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
		if ( $isModeratorRequest == true ) {
			$timeDifference = CHAT_HISTORY_MAX_TIME_DIFFERENCE_MODERATOR;
		}
		else {
			$timeDifference = CHAT_HISTORY_MAX_TIME_DIFFERENCE;
		}
		
		$startDate = $this->PrepareDate( $startDate );
		$endDate = $this->PrepareDate( $endDate );
		$startTime = strtotime( $startDate );
		$endTime = strtotime( $endDate );
		
		if( $startTime === FALSE || $endTime === FALSE ||
			( $endTime - $startTime > $timeDifference ) ) {
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
		
		$historyCache = CHAT_AUTOMODERATION_HISTORY_MEMFS_DIR . '/' . $historyCache;
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