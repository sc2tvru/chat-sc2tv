<?
class Chat {
	private $user, $channelId, $memcache, $db;
    
	function __construct( $memcacheObject ) {
		
		// по умолчанию считаем всех анонимами без прав
		$this->user = array(
			'uid' => 0,
			'type' => 'anon',
			'rid' => 1,
			'rights' => -1
		);
		
		$this->channelId = '';
		$this->memcache = $memcacheObject;
	}
	
	
	/**
	 *	настройка на использование базы
	 */
	public function SetDatabase( $db = '' ) {
		// если указана база, используем ее
		if ( is_object( $db ) ) {
			$this->db = $db;
		}
		// если не указана
		else {
			// и база еще не была настроена, настраиваем ее
			if ( !isset( $this->db ) ) {
				$this->db = GetDb();
			}
		}
	}
	
	
	/**
	 * авторизация пользователя
	 * @return array возвращает массив с ключами error, userInfo
	 * error - сообщение пользователю
	 * userInfo - данные по пользователю
	 * Пример:
	 * Array
		(
            [uid] => 241
            [name] => Okarin
            [ban] => 0
            [created] => 1274051744
            [banExpirationTime] => 1317410423
            [banTime] => 1317409823
            [type] => chatAdmin
            [rights] => 1
            [rid] => 4
        )
	 */
	public function GetAuthInfo() {
		if( empty( $_COOKIE[ DRUPAL_SESSION ] ) || 
			preg_match( '/[^a-z\d]+/i', $_COOKIE[ DRUPAL_SESSION ] ) ) {
			/*/ запись для отладки кэширования по просьбе Данила
			$this->SetDatabase();
			
			$ipAddress = $_SERVER[ 'REMOTE_ADDR' ];
			if ( $ipAddress == '' ) {
				$ipAddress = '0';
			}
			
			$ipAddress = sprintf( "%u", ip2long( $ipAddress ) );
			
			$uri = $_SERVER[ 'REQUEST_URI' ];
			list( $uri ) = $this->db->PrepareParams( $uri );
			
			if ( isset( $_GET[ 'ref' ] ) ) {
				$referer = $_GET[ 'ref' ];
				list( $referer ) = $this->db->PrepareParams( $referer );
			}
			else {
				$referer = '';
			}
			
			if ( isset( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) {
				$userAgent = $_SERVER[ 'HTTP_USER_AGENT' ];
				list( $userAgent ) = $this->db->PrepareParams( $userAgent );
			}
			else {
				$userAgent = '';
			}
			
			$cookie = var_export( $_COOKIE, true );
			//list( $cookie ) = $this->db->PrepareParams( $cookie );
			
			$queryString = 'INSERT INTO chat_anon VALUES(
				"'. $ipAddress . '",
				"'. CURRENT_DATE .'",
				"'. $uri .'",
				"'. $referer .'",
				"'. $userAgent .'",
				"'. $cookie .'"
			)';
			
			$queryResult = $this->db->Query( $queryString );
			
			//*/
			
			$result = array(
				'userInfo' => $this->user,
				'error' => CHAT_COOKIE_NOT_FOUND
			);
			return $result;
		}
		
		$drupalSession = $_COOKIE[ DRUPAL_SESSION ];
		
		$chatAuthMemcacheKey = 'ChatUserInfo_' . $drupalSession;
		//* test for Aulust
		$memcacheAuthInfo = $this->GetAuthInfoFromMemcache( $chatAuthMemcacheKey );

		if ( $memcacheAuthInfo[ 'code' ] == 1 ) {
			$result = array(
				'userInfo' => $this->user,
				'error' => $memcacheAuthInfo[ 'error' ]
			);
			return $result;
		}
		//*/
		$this->SetDatabase();
		// TODO: регулярки выше должно хватить, но на всякий случай лучше подготовить
		// убрать?
		list( $drupalSession ) = $this->db->PrepareParams( $drupalSession );
		
    // roles priority Moderator > Root > Admin > Streamer > others
    $queryString = 'SELECT users.uid as uid, name, created, rid, banExpirationTime, banTime,
      chat_ban.status as ban, rid in (5) as isModerator, rid in (4) as isAdmin, rid in (3) as isRoot
      FROM users INNER JOIN sessions using(uid)
      LEFT JOIN chat_ban ON users.uid = chat_ban.uid
      LEFT JOIN users_roles ON users_roles.uid = users.uid
      WHERE sid = "'. $drupalSession .'"
      ORDER BY isModerator DESC, isRoot DESC, isAdmin DESC, ban DESC, banExpirationTime DESC, rid ASC LIMIT 1';
		
		$queryResult = $this->db->Query( $queryString );
		
		if ( $queryResult === FALSE ) {
			SaveForDebug( 'Login fail ' . $queryString );
			$result = array(
				'userInfo' => $this->user,
				'error' => 'Ошибка авторизации'
			);
			return $result;
		}
		
		$userInfo = $queryResult->fetch_assoc();
		
		$reasonWhyUserCantChat = $this->GetReasonWhyUserCantChat( $userInfo, $chatAuthMemcacheKey );
		
		if ( $reasonWhyUserCantChat != FALSE ) {
			$result = array(
				'userInfo' => $this->user,
				'error' => $reasonWhyUserCantChat 
			);
			return $result;
		}
		
		$result[ 'error' ] = '';
		
		// 3 - root, 4 - admin, 5 - moder, 6 - journalist, 7 - editor, 8 - banned, 9 - streamer, 10 - userstreamer
		if ( $userInfo[ 'rid' ] === NULL ) {
			$this->user[ 'rid' ] = 2;
		}
		else {
			$this->user[ 'rid' ] = $userInfo[ 'rid' ];
		}
		
		switch ( $this->user[ 'rid' ] ) {
			/*
			case 2:
			case 6:
			case 7:
				$this->user[ 'type' ] = 'user';
				$this->user[ 'rights' ] = 0;
			break;*/
			
			case 3:
			case 4:
			case 5:
				$this->user[ 'type' ] = 'chatAdmin';
				$this->user[ 'rights' ] = 1;
			break;
			
			case 8:
				$this->user[ 'ban' ] = 1;
				$this->user[ 'rights' ] = -1;
				$this->user[ 'type' ] = 'bannedOnSite';
				$result[ 'error' ] = CHAT_USER_BANNED_ON_SITE;
			break;
			
			default:
				$this->user[ 'type' ] = 'user';
				$this->user[ 'rights' ] = 0;
		}
		
		// генерируем токен на основе сессии и запоминаем
		if ( empty( $_COOKIE[ CHAT_COOKIE_TOKEN ] ) ) {
			$this->user[ 'token' ] = GenerateSecurityToken( $drupalSession );
			setcookie( CHAT_COOKIE_TOKEN, $this->user[ 'token' ] );
		}
		elseif(	!preg_match( '/[^a-z\d]+/i', $_COOKIE[ CHAT_COOKIE_TOKEN ] ) ) {
			$this->user[ 'token' ] = $_COOKIE[ CHAT_COOKIE_TOKEN ];
		}
		
		$this->memcache->Set( $chatAuthMemcacheKey, $this->user, CHAT_USER_AUTHORIZATION_TTL );
		
		$result[ 'userInfo' ] = $this->user;
		return $result;
	}
	
	
	/**
	 *	проверка на наличие причины, почему нельзя писать в чат
	 *	@param array $userInfo - массив с данными пользователя
	 *	@param string $chatAuthMemcacheKey ключ к данным пользователя в memcache
	 *	@return string - текст ошибки 
	 */
	private function GetReasonWhyUserCantChat( $userInfo, $chatAuthMemcacheKey ) {
		// Drupal обнуляет uid в сессии, если пользователю в профиле поставить статус blocked
		if ( $userInfo === NULL || $userInfo[ 'uid' ] == 0 ) {
			return CHAT_UID_FOR_SESSION_NOT_FOUND;
		}
		
		$this->user = $userInfo;
		
		//*
		$newbieStatusTTL = $userInfo[ 'created' ] + CHAT_TIME_ON_SITE_AFTER_REG_NEEDED
			- CURRENT_TIME;
		
		if( $newbieStatusTTL > 0 ) {
			$this->user[ 'ban' ] = 0;
			$this->user[ 'rights' ] = -1;
			$this->user[ 'type' ] = 'newbie';
			
			$this->memcache->Set( $chatAuthMemcacheKey, $this->user, $newbieStatusTTL );
			return CHAT_NEWBIE_USER;
		}
		//*/
		
		if( $userInfo[ 'ban' ] == 1 ) {
			// для проверки на гражданство в будущем
			$this->user[ 'wasBanned' ] = 1;
			
			$banStatusTTL = $userInfo[ 'banExpirationTime' ] - CURRENT_TIME;
			if ( $banStatusTTL > 0 ) {
				$this->user[ 'ban' ] = 1;
				$this->user[ 'rights' ] = -1;
				$this->user[ 'type' ] = 'bannedInChat';
				
				$this->memcache->Set( $chatAuthMemcacheKey, $this->user, $banStatusTTL );
				return CHAT_USER_BANNED_IN_CHAT;
			}
			else {
				$this->user[ 'ban' ] = 0;
			}
		}
		
		return false;
	}
	
	
	/**
	 *	Получение данных для авторизации из memcache
	 *	@param string key ключ memcache
	 *	return array массив с ключами code, error - текст ошибки
	 *	code - 1 для успешной атворизации, 0 для ошибки
	 */
	private function GetAuthInfoFromMemcache( $key ) {
		$result = array (
			'code' => 1,
			'error' => ''
		);
		
		$userInfo = $this->memcache->Get( $key );
		
		if ( $userInfo === false ) {
			$result[ 'code' ] = 0;
			return $result;
		}
		
		$this->user = $userInfo;
		// SaveForDebug( 'GetAuthInfoFromMemcache userInfo ' .var_export( $userInfo, true ) );
		// проверяем флаг в memcache на случай бана от модератора или граждан,
		// либо изменения длительности бана
		$banInfoMemcacheKey = 'Chat_uid_' . $this->user[ 'uid' ] . '_BanInfo'; 
		$banInfo = $this->memcache->Get( $banInfoMemcacheKey );
		/*
		SaveForDebug( 'GetAuthInfoFromMemcache banInfoMemcacheKey = '
			. $banInfoMemcacheKey . ' banInfo ' .var_export( $banInfo, true ) );
		//*/
		if ( $banInfo === false ) {
			switch ( $userInfo[ 'type' ] ) {
				case 'bannedInChat':
					$result[ 'error' ] = CHAT_USER_BANNED_IN_CHAT;
				break;
				
				case 'bannedOnSite':
					$result[ 'error' ] = CHAT_USER_BANNED_ON_SITE;
				break;
				
				case 'newbie':
					$result[ 'error' ] = CHAT_NEWBIE_USER;
				break;
			}
		}
		else {
			// при форсе релогина удаляем информацию о бане и возвращаем код 0 для авторизации чере базу
			if ( isset( $banInfo[ 'needRelogin' ] ) && ( $banInfo[ 'needRelogin' ] == 1 ) ) {
				$this->memcache->Delete( $banInfoMemcacheKey );
				$result[ 'code' ] = 0;
				return $result;
			}
			
			$result[ 'error' ] = CHAT_USER_BANNED_IN_CHAT;
			
			/** если есть информация о бане, нужно обновить данные по пользователю,
			 *  но только если это еще не сделано (тип пользователя отличен от bannedInChat)
			 *	либо установлен флаг needUpdate
			 */
			if ( $this->user[ 'type' ] != 'bannedInChat' || isset( $banInfo[ 'needUpdate' ] ) && ( $banInfo[ 'needUpdate' ] == 1 ) ) {
				
				$banInfoTTL = $banInfo[ 'banExpirationTime' ] - CURRENT_TIME;
				//SaveForDebug( var_export( $banInfo, true ) . "banInfoTTL = $banInfoTTL" );
				// бан уже прошел
				if ( $banInfoTTL <= 0 ) {
					$this->memcache->Delete( $banInfoMemcacheKey );
					$result[ 'code' ] = 0;
					return $result;
				}
				else {
					$this->user[ 'ban' ] = 1;
					$this->user[ 'rights' ] = -1;
					$this->user[ 'type' ] = 'bannedInChat';
					$this->user[ 'banExpirationTime' ] = $banInfo[ 'banExpirationTime' ];
					$this->user[ 'banTime' ] = $banInfo[ 'banTime' ];
					
					$banInfo[ 'needUpdate' ] = 0;
					/*
					SaveForDebug( 'GetAuthInfoFromMemcache new banInfo '
						. var_export( $banInfo, true ) . "\n\nnew userInfo "
						. var_export( $this->user, true ) );
					//*/
					$this->memcache->Set( $banInfoMemcacheKey, $banInfo, $banInfoTTL );
					$this->memcache->Set( $key, $this->user, $banInfoTTL );
				}
			}
		}
		
		return $result;
	}
	
	
	/**
	 *  проверка строки на CAPS / abuse
	 *  @param string str строка для проверки
	 *  @return bool true | false
	 */
	private function IsStringCapsOrAbuse( $str ) {
		// удаляем обращения вроде [b]MEGAKILLER[/b], bb-код [b][/b]
		$tempStr = preg_replace( '/^\[b\][-\.\w\x{400}-\x{45F}\x{490}\x{491}\x{207}\x{239}\[\]]+\[\/b\]|\[b\]|\[\/b\]/uis', '',  $str );
		
		// если остались только пробельные символы, это абуз
		if ( !preg_match( '/[^\s]+/uis', $tempStr ) ) {
			return true;
		}
		
		// URL
		$tempStr = preg_replace( '/(?:ht|f)tp[s]{0,1}:\/\/[^\s]+/uis', '',  $tempStr );
		
		// коды смайлов
		$tempStr = preg_replace( '/:s:[^:]+:/uis', '',  $tempStr );
		
		// общее кол-во букв независимо от регистра
		preg_match_all( '/[a-z\x{400}-\x{45F}\x{490}\x{491}\x{207}\x{239}]/ui', $tempStr, $matches );
		
		$len = count( $matches[ 0 ] );
		
		if ( $len === 0 ) {
			return false;
		}
		
		// кол-во букв в верхнем регистре
		preg_match_all( '/[A-ZА-Я]/u', $tempStr, $matches );
		$capsCount = count( $matches[ 0 ] );
		
		if( $capsCount >= 5 && $capsCount >= ( $len / 2 ) ) {
			return true;
		}
		else {
			return false;
		}
	}
	
	
	/**
	 *  получение id текущего канала из $_POST[ 'channel_id' ]
	 *  @return string
	 */
	private function GetChannelId() {
		if ( $this->channelId === '' ) {
			if ( isset( $_POST[ 'channel_id' ] ) ) {
				$id = (int)$_POST[ 'channel_id' ];
				
				if ( $id < 0 ) {
					$id = 0;
				}
			}
			else {
				$id = 0;
			}
			
			$this->channelId = $id;
		}
		
		return $this->channelId;
	}
	
	
	/**
	 *  запись сообщений из базы в файл в memfs
	 *  @param int $channelId id канала
	 */
	private function WriteChannelCache( $channelId = 0 ) {
		if ( $channelId >= 0 ) {
			$isCacheActualMemcacheKey = 'ChatChActual-' . $channelId;
			$channelFileName = CHAT_MEMFS_DIR . '/channel-' . $channelId . '.json';
		}
		else {
			$isCacheActualMemcacheKey = 'ChatModChActual';
			$channelFileName = CHAT_MEMFS_DIR . '/channel-moderator.json';
		}
		
		$isCacheActual = $this->memcache->Get( $isCacheActualMemcacheKey );
		
		// пока кэш актуален, перезаписывать его не нужно
		if ( $isCacheActual == '1' ) {
			return;
		}
		
		// пишем в файл, если он не заблокирован
		$channelCacheFile = fopen( $channelFileName, 'w' );
	
		if ( flock( $channelCacheFile, LOCK_EX | LOCK_NB ) ) {
			$messages = $this->GetMessagesByChannelId( $channelId );
			$dataJson = json_encode( array( 'messages' => $messages ) );
			
			fwrite( $channelCacheFile, $dataJson );
			fflush( $channelCacheFile );
			
			$channelFileNameGz = $channelFileName . '.gz';
			$channelCacheGzFile = gzopen( $channelFileNameGz, 'w' );
			gzwrite( $channelCacheGzFile, $dataJson );
			gzclose( $channelCacheGzFile );
			
			// помечаем, что кэш актуален
			$this->memcache->Set( $isCacheActualMemcacheKey, true, CHANNEL_CACHE_ACTUAL_TTL );
			
			flock( $channelCacheFile, LOCK_UN );
		}
		
		fclose( $channelCacheFile );
	}
	
	
	/**
	 *  получение из базы списка сообщений для заданного канала
	 *  @param int $channelId id канала
	 *  @return array
	 */
	private function GetMessagesByChannelId( $channelId = -1 ) {
		// если канал не указан, выбираются сообщения для модераторов по всем каналам
		if (  $channelId === -1 ) {
			$channelCondition = '';
      $index_condition = '';
			$messagesCount = CHAT_MODERATORS_MSG_LIMIT;
		}
		else {
			$channelCondition = 'channelId = "' . $channelId . '" AND ';
      $index_condition = 'USE INDEX(channelId)';
			$messagesCount = CHAT_CHANNEL_MSG_LIMIT;
		}
		
		// ограничение по дате сделано, чтобы ускорить выборку при большом числе записей
		/*$queryString = '
			SELECT id, chat_message.uid, IFNULL( name, "system" ) as name, message,
			IFNULL( min( rid ), 2 ) as rid, date, channelId
			FROM chat_message
			LEFT JOIN users on users.uid = chat_message.uid
			LEFT JOIN users_roles ON users_roles.uid = chat_message.uid
			WHERE '. $channelCondition .'
			deletedBy is NULL
			AND date > "' . date( 'Y-m-d H:i:s', CURRENT_TIME - 259200 ) . '"
			GROUP BY id
			ORDER BY id	DESC LIMIT '. $messagesCount;*/

    // roles priority root > Admin > Moderator > Streamer > others.
    $queryString = 'SELECT *  FROM (
      SELECT * FROM (
        SELECT id, IFNULL(rid, 2 ) as rid, chat_message.uid, IFNULL( name, "system" ) as name, message, date, channelId
          FROM chat_message '. $index_condition .'
    			LEFT JOIN users on users.uid = chat_message.uid
    			LEFT JOIN users_roles ON users_roles.uid = chat_message.uid
          WHERE '. $channelCondition .'
    			date > "' . date( 'Y-m-d H:i:s', CURRENT_TIME - 259200 ) . '" AND
          deletedBy is NULL
          ORDER BY id DESC LIMIT '. $messagesCount * 3 . '
        ) as tmp_table_chat  ORDER BY FIELD(rid,3,4,5,9,6,7,10,14,2) ASC LIMIT '. $messagesCount * 3 . '
      ) as tmp_table_chat_limited GROUP BY id ORDER BY id DESC LIMIT '. $messagesCount;
		
		$queryResult = $this->db->Query( $queryString );
		
		if ( $queryResult === false ) {
			SaveForDebug( 'GetMessagesByChannelId fail ' . $queryString );
			return false;
		}
		
		$messages = array();

		while( $msg = $queryResult->fetch_assoc() ) {
			$messages[] = $msg;
		}
		
		return $messages;
	}
	
	
	/**
	 *  проверка сообщения на попадание под признаки автобана
	 *  @param string message сообщение
	 *  @return bool true | false
	 */
	private function CheckForAutoBan( $message ) {
		// 3 смайла
		if( preg_match( '/(?::s.*:.*){3,}/usi', $message ) ) {
			$this->BanUser( $this->user[ 'uid' ], $this->user[ 'name' ], 10, 0, 0,
				CHAT_AUTOBAN_REASON_1, true );
			return true;
		}
		
		return false;
	}
	
	
	/**
	 *  пост сообщения в чат
	 *  @param string message текст сообщения
	 *  @return bool true в случае успеха, false неудачи
	 */
	public function WriteMessage( $message ) {
		
		/* удаляем явно не разрешенные символы
		разрешены
		U+0020 - U+003F - знаки препинания и арабские цифры
		U+0040 - U+007E http://ru.wikipedia.org/wiki/Латинский_алфавит_в_Юникоде
		U+0400 - U+045F, U+0490, U+0491, U+0207, U+0239 http://ru.wikipedia.org/wiki/Кириллица_в_Юникоде
		U+2012, U+2013, U+2014 - тире
		*/
		$message = preg_replace( '/[^\x20-\x7E\x{400}-\x{45F}\x{490}\x{491}\x{207}\x{239}\x{2012}\x{2013}\x{2014}]+/us', '',  $message );
		
		// whitespaces
		$message = preg_replace( '#[\s]+#uis', ' ',  $message );
		
		if( $message === '' ) {
			return false;
		}
		// TODO php 5.4.0 добавить ENT_SUBSTITUTE ?
		$message = htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' );
		
		$channelId = $this->GetChannelId();
		
		if( $this->IsStringCapsOrAbuse( $message ) ) {
			// предотвращаем перевод кодов смайлов в картинки, чтобы не бился html
			$message = preg_replace( '/(?::s)+(:[^:]+:)/uis', '\\1', $message );
			// URL тоже
			$message = preg_replace( '/(?:ht|f)tp[s]{0,1}:\/\/([^\s]+)/uis', '\\1',  $message );
			$message = '<span class="red" title="' . $message . '">Предупреждение за CAPS / Abuse!</span>';
		}
		else {
			$message = preg_replace( '#\[b\](.+?)\[/b\]#uis', '<b>\\1</b>',  $message );
		}
		
		if( $this->CheckForAutoBan( $message ) ) {
			return false;
		}
		
		$message = $this->db->mysqli->real_escape_string( $message );
		
		$queryString = '
			INSERT INTO chat_message (uid, message, date, channelId)
			VALUES ("'.
				$this->user[ 'uid' ] .'", "'.
				$message .'", "'.
				CURRENT_DATE .'", "'.
				$channelId .'")';
		
		$queryResult = $this->db->Query( $queryString );
		
		if ( $queryResult ) {
			// кэш текущего канала
			$this->WriteChannelCache( $channelId );
			// кэш модераторов
			$this->WriteChannelCache( -1 );
			return true;
		}
		else {
			return false;
		}
	}
	
	
	/**
	 *	удаляет сообщение
	 *	@param int messageId id сообщения
	 *	@param int channelId id канала
	 *  @return array возвращает массив вида
	 *  array(
			'code' => 0,// код результата: 0 для ошибки | 1 для успеха
			'error' => 'hack';// текст ошибки
		)
	 */
	public function DeleteMessage( $messageId, $channelId ) {
		$messageId = (int)$messageId;
		$channelId = (int)$channelId;
		
		if( $this->user[ 'rights' ] != 1 || $messageId <= 0 || $channelId < 0 ) {
			SaveForDebug( var_export( $_REQUEST, true ) );
			$result = array(
				'code' => 0,
				'error' => CHAT_RUNTIME_ERROR
			);
			return $result;
		}
		
		$queryString = '
			UPDATE chat_message
			SET deletedBy = "' . $this->user[ 'uid' ] . '"
			WHERE id = "' . $messageId . '"';
		
		$queryResult = $this->db->Query( $queryString );
		
		if( $queryResult === false ) {
			$result = array(
				'code' => 0,
				'error' => CHAT_RUNTIME_ERROR . '1'
			);
			return $result; 
		}
		
		// кэш текущего канала
		$this->WriteChannelCache( $channelId );
		// кэш модераторов
		$this->WriteChannelCache( -1 );
		
		$result = array(
			'code' => 1,
			'error' => ''
		);
		
		return $result;
	}

	
	/**
	 *  бан пользователя
	 *  @param int banUid id пользователя, которого баним
	 *  @param string banUserName его имя
	 *  @param int banDurationInMin длительность бана в минутах
	 *  @param int banMessageId id сообщения, за которое выдается бан
	 *	@param int channelId id канала юзера
	 *  @param int banReasonId id причины бана, для модеров 0
	 *  @param bool isAutoBan если автобан, true, иначе false
	 *  @return array возвращает массив вида
	 *  array(
			'code' => 0,// код результата: 0 для ошибки | 1 для успеха
			'error' => 'hack';// текст ошибки
		)
	 */
	public function BanUser( $banUid, $banUserName, $banDurationInMin, $banMessageId,
		$channelId,	$banReasonId = 0, $isAutoBan = false ) {
		
		$banUid = (int)$banUid;
		$banMessageId = (int)$banMessageId;
		$banReasonId = (int)$banReasonId;
		$banDurationInMin = (int)$banDurationInMin;
		$channelId = (int)$channelId;
		
		// выдаем ошибку, если есть права, но неправильный id сообщения
		if( ( $this->user[ 'rights' ] === 1 && $banMessageId < 0 ) ||
			// либо нет прав, но это не автобан
			( $this->user[ 'rights' ] != 1 && $isAutoBan === false ) ||
			// либо непонятно, кого баним и насколько
			$banUid === 0 ||	$banUserName === '' || $banDurationInMin === 0 ||
			// или неправильная причина бана
			$banReasonId < 0 ) {
			SaveForDebug( var_export( $_REQUEST, true ) );
			
			$result = array(
				'code' => 0,
				'error' => CHAT_RUNTIME_ERROR . '2'
			);
			return $result;
		}
		
		$banDuration = $banDurationInMin * 60;
		$banExpirationTime = CURRENT_TIME + $banDuration;
		
		// проверяем флаг в memcache на случай бана от модератора или граждан
		$banInfoMemcacheKey = 'Chat_uid_' . $banUid . '_BanInfo';
		
		// делаем через Add, чтобы одновременно проверить отсутствие флага и установить его
		$isUserBanned = $this->memcache->Add(
			$banInfoMemcacheKey,
			array(
				'banTime' => CURRENT_TIME, 
				'banExpirationTime' => $banExpirationTime
			),
			$banDuration
		);
		
		if ( $isUserBanned === false ) {
			$result = array(
				'code' => 0,
				'error' => 'Уже забанен'
			);
			return $result;
		}
		
		list( $banUserName ) = $this->db->PrepareParams( $banUserName );
		
		$moderatorName = $this->user[ 'name' ];
		$moderatorId = $this->user[ 'uid' ];
		
		$queryString = '
			INSERT INTO chat_ban
			( uid, moderatorId, banExpirationTime, banMessageId, banReasonId, banTime,
			banDuration )
			VALUES( "' .
				$banUid . '", "' .
				$moderatorId . '", "' .
				$banExpirationTime . '", "' .
				$banMessageId . '", "' .
				$banReasonId . '", "' . 
				CURRENT_TIME . '", "' .
				$banDuration .
			'")';
		
		$queryResult = $this->db->Query( $queryString );
		
		if( $queryResult === false ) {
			// в случае ошибки с запросом удаляем флаг в мемкеше, чтобы юзера можно было забанить в следующий раз
			$this->memcache->Delete( $banInfoMemcacheKey );
			$result = array(
				'code' => 0,
				'error' => CHAT_RUNTIME_ERROR . '3'
			);
			return $result;
		}
		
		if( CHAT_DELETE_BANNED_USERS_MESSAGE && $isAutoBan === false) {
			$queryString = '
				UPDATE chat_message
				SET deletedBy = "' . $moderatorId . '"
				WHERE id = "'. $banMessageId .'"';
			
			$queryResult = $this->db->Query( $queryString );
			
			if( $queryResult === false ) {
				$this->memcache->Delete( $banInfoMemcacheKey );
				$result = array(
					'code' => 0,
					'error' => CHAT_RUNTIME_ERROR . '4'
				);
				return $result;
			}
		}
		
		// сохраняем для модераторов кол-во банов
		if ( $this->user[ 'type' ] === 'chatAdmin' ) {
			$moderatorsDetails = $this->memcache->Get( MODERATORS_DETAILS_MEMCACHE_KEY );
			
			if ( $moderatorsDetails != false ) {
				if ( isset( $moderatorsDetails[ $moderatorId ] ) ) {
					// TODO += ?
					if ( isset( $moderatorsDetails[ $moderatorId ][ 'bansCount' ] ) ) {
						$moderatorsDetails[ $moderatorId ][ 'bansCount' ] = 
							$moderatorsDetails[ $moderatorId ][ 'bansCount' ] + 1;
					}
					else {
						$moderatorsDetails[ $moderatorId ][ 'bansCount' ] = 1;
					}
				}
				else {
					$moderatorsDetails[ $moderatorId ] = array(
						'name' => $moderatorName,
						'bansCount' => 1
					);
				}
				
				$this->memcache->Set( MODERATORS_DETAILS_MEMCACHE_KEY, $moderatorsDetails,
					CHAT_MODERATORS_DETAILS_TTL );
			}
		}
		
		if ( !$isAutoBan ) {
			// кэш текущего канала
			$this->WriteChannelCache( $channelId );
			// кэш модераторов
			$this->WriteChannelCache( -1 );
		}
		
		$message = $moderatorName .' забанил '. $banUserName .' на ' .$banDurationInMin.' минут.';
		$this->WriteSystemMessage( $message );
		
		$result = array(
			'code' => 1,
			'error' => ''
		);
		
		return $result;
	}
	
	
	/**
	 *  пост системного сообщения
	 *  @param string message сообщение
	 *  @return bool true успех, false ошибка
	 */
	public function WriteSystemMessage( $message ) {
		list( $message ) = $this->db->PrepareParams( $message );
		
		if ( $message === '' ) {
			return false;
		}
		
		$queryString = '
			INSERT INTO chat_message ( uid, message, date )
			VALUES ( "-1", "'. $message .'", "'. CURRENT_DATE .'")';
		
		$queryResult = $this->db->Query( $queryString );
		
		if( $queryResult ) {
			$this->WriteChannelCache( 0 );
			return true;
		}
		else {
			return false;
		}
	}
}
?>