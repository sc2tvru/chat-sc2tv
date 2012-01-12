<?php
class ChatHistory {
	
	private $db;
	
	
	/**
	 *	получение истории
	 *	@param channelId id канала
	 *	@param startDate дата начала
	 *	@param endDate дата конца
	 *	@param userNames имена пользователей, разделенные ;
	 *	@param historyCache имя файла для сохранения кэша, если не задано, будет
	 *	использоваться 
	 */
	public function Get( $channelId, $startDate, $endDate, $userNames = '', $historyCache = '' ) {
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
			$userNames = $this->PrepareUserNames( $userNames );
			$userNamesCopy = $userNames;
			$userNames = explode( ';', $userNames );
			
			if ( count( $userNames ) > 0 ) {
				$options = '(';
				$operator = '';
				
				$this->db = GetDb();
				
				foreach ( $userNames as $userName ) {
					list( $userName ) = $this->db->PrepareParams( $userName );
					$options .= $operator .'name="'. $userName .'"';
					if ( $operator === '' ) {
						$operator = ' OR ';
					}
				}
				
				$options .= ') AND ';
			}
		}
		
		if ( !( $channelId == '' || $channelId == 'all' ) ) {
			$channelId = (int)$channelId;
			$options .= 'channelId = "'. $channelId .'" AND ';
		}
		
		$queryString = '
			SELECT id, chat_message.uid, name, message, date, channelId, (
				SELECT IFNULL((
					SELECT min(rid)
					FROM users_roles
					WHERE users_roles.uid = chat_message.uid), 2
				)
			) as rid
			FROM chat_message
			INNER JOIN users using(uid)
			WHERE '. $options .'
			date BETWEEN STR_TO_DATE("'. $startDate .'", "%Y-%m-%d %H:%i:%s")
			AND STR_TO_DATE("'. $endDate .'", "%Y-%m-%d %H:%i:%s")
			AND deletedBy is NULL';
		//echo '<!--' . $queryString . '-->';exit;
		
		$this->db = GetDb();
		$queryResult = $this->db->Query( $queryString );
		
		if ( $queryResult === false ) {
			SaveForDebug( $queryResult );
			$result = array(
				'messages' => '',
				'error' => CHAT_RUNTIME_ERROR . ' history 1'
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
		
		$historyCache = CHAT_HISTORY_MEMFS_DIR . $historyCache;
		file_put_contents( $historyCache, $dataJson );
		touch( $historyCache );
		
		$result = array(
			'messages' => $messages,
			'error' => ''
		);
		
		return $result;
	}
	
	
	private function PrepareUserNames( $userNames ){
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