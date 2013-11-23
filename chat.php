<?
class Chat {
	public $user;
	private $channelId, $memcache, $db;
	
	function __construct( $memcacheObject ) {
		
		// по умолчанию считаем всех анонимами без прав
		$this->user = array(
			'uid' => 0,
			'type' => 'anon',
			'rid' => 1,
			'rights' => -1,
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
			$this->user[ 'error' ] = CHAT_COOKIE_NOT_FOUND;
			return;
		}
		
		$drupalSession = $_COOKIE[ DRUPAL_SESSION ];
		
		$chatAuthMemcacheKey = 'ChatUserInfo_' . $drupalSession;
		
		$isAuthInfoInMemcache = $this->GetAuthInfoFromMemcache(
			$chatAuthMemcacheKey
		);

		if ( $isAuthInfoInMemcache ) {
			return;
		}
		
		$this->SetDatabase();
		// регулярки выше должно хватить, но на всякий случай
		// лучше подготовить. TODO: Убрать?
		list( $drupalSession ) = $this->db->PrepareParams( $drupalSession );
		
    // roles priority Moderator > Root > Admin > Streamer > others
    $queryString = 'SELECT users.uid as uid, name, created, rid, banExpirationTime, banTime,
      chat_ban.status as ban, rid in (5) as isModerator, rid in (4) as isAdmin, rid in (3) as isRoot,
      (SELECT GROUP_CONCAT(rid SEPARATOR ",") FROM users_roles WHERE users_roles.uid = users.uid) as roleIds
      FROM users INNER JOIN sessions using(uid)
      LEFT JOIN chat_ban ON users.uid = chat_ban.uid
      LEFT JOIN users_roles ON users_roles.uid = users.uid
      WHERE sid = "'. $drupalSession .'"
      ORDER BY isModerator DESC, isRoot DESC, isAdmin DESC, ban DESC, banExpirationTime DESC, rid ASC LIMIT 1';

		$queryResult = $this->db->Query( $queryString );

		if ( $queryResult === FALSE ) {
			SaveForDebug( 'Login fail ' . $queryString );
			$this->user[ 'error' ] = 'Ошибка авторизации';
			return;
		}

		$userInfo = $queryResult->fetch_assoc();

		// Drupal обнуляет uid в сессии, если пользователю в профиле
		// поставить статус blocked
		if ( $userInfo === NULL || $userInfo[ 'uid' ] == 0 ) {
			$this->user[ 'error' ] = CHAT_UID_FOR_SESSION_NOT_FOUND;
			return;
		}
		
		$this->user = $userInfo;
		
		if ( $this->user[ 'roleIds' ] === NULL ) {
			$this->user[ 'roleIds' ] = array(2);
		} else {
			$this->user[ 'roleIds' ] = array_merge(
				array(2),
				array_map( 'intval', explode( ',', $userInfo[ 'roleIds' ] ) )
			);
		}
		
		$newbieStatusTTL = $userInfo[ 'created' ] +
			CHAT_TIME_ON_SITE_AFTER_REG_NEEDED - CURRENT_TIME;
		
		if( $newbieStatusTTL > 0 ) {
			$this->user[ 'ban' ] = 0;
			$this->user[ 'rights' ] = -1;
			$this->user[ 'type' ] = 'newbie';
			$this->user[ 'error' ] = CHAT_NEWBIE_USER;
			$this->memcache->Set(
				$chatAuthMemcacheKey,
				$this->user,
				$newbieStatusTTL
			);
			return;
		}

		if( $userInfo[ 'ban' ] == 1 ) {
			// для проверки на гражданство в будущем
			$this->user[ 'wasBanned' ] = 1;

			$banStatusTTL = $userInfo[ 'banExpirationTime' ] - CURRENT_TIME;
			if ( $banStatusTTL > 0 ) {
				$this->user[ 'ban' ] = 1;
				$this->user[ 'rights' ] = -1;
				$this->user[ 'type' ] = 'bannedInChat';
				$this->user[ 'error' ] = CHAT_USER_BANNED_IN_CHAT;
				$this->memcache->Set( $chatAuthMemcacheKey, $this->user, $banStatusTTL);
				return;
			}
			else {
				$this->user[ 'ban' ] = 0;
			}
		}

		$this->user[ 'error' ] = '';
		
		// 3 - root, 4 - admin, 5 - moder, 6 - journalist, 7 - editor, 8 - banned
		// 9 - streamer, 10 - userstreamer
		// TODO: rid is useless since we add roleIds list?
		if ( $this->user[ 'rid' ] === NULL ) {
			$this->user[ 'rid' ] = 2;
		}
		
		if ( count(
			array_intersect( array( 3, 4, 5 ), $this->user[ 'roleIds' ] )
			) > 0 ) {
			$this->user[ 'type' ] = 'chatAdmin';
			$this->user[ 'rights' ] = 1;
		} elseif ( in_array( 8, $this->user[ 'roleIds' ] ) ) {
			$this->user[ 'ban' ] = 1;
			$this->user[ 'rights' ] = -1;
			$this->user[ 'type' ] = 'bannedOnSite';
			$this->user[ 'error' ] = CHAT_USER_BANNED_ON_SITE;
		} else {
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
		
		$this->memcache->Set(
			$chatAuthMemcacheKey,
			$this->user,
			CHAT_USER_AUTHORIZATION_TTL
		);
	}
	
	
	/**
	 *	Получение данных для авторизации из memcache
	 *	@param string key ключ memcache
	 *	return boolean TRUE - успех, FALSE - нет данных
	 */
	private function GetAuthInfoFromMemcache( $key ) {
		$userInfo = $this->memcache->Get( $key );
		
		if ( $userInfo === FALSE ) {
			return FALSE;
		}
		
		$this->user = $userInfo;
		
		// проверяем флаг в memcache на случай бана от модератора или граждан,
		// либо изменения длительности бана
		$banInfoMemcacheKey = 'Chat_uid_' . $this->user[ 'uid' ] . '_BanInfo'; 
		$banInfo = $this->memcache->Get( $banInfoMemcacheKey );
		/*
		SaveForDebug( 'GetAuthInfoFromMemcache banInfoMemcacheKey = '
			. $banInfoMemcacheKey . ' banInfo ' .var_export( $banInfo, TRUE ) );
		//*/
		if ( $banInfo !== FALSE ) {
			// при форсе релогина удаляем информацию о бане и возвращаем FALSE
			// для авторизации через базу
			if ( isset( $banInfo[ 'needRelogin' ] )
				&& ( $banInfo[ 'needRelogin' ] == 1 ) ) {
				$this->memcache->Delete( $banInfoMemcacheKey );
				return FALSE;
			}
			
			$this->user[ 'error' ] = CHAT_USER_BANNED_IN_CHAT;
			
			/** если есть информация о бане, нужно обновить данные по пользователю,
			 *  но только если это еще не сделано (тип пользователя не bannedInChat)
			 *	либо установлен флаг needUpdate
			 */
			if ( $this->user[ 'type' ] != 'bannedInChat'
				|| isset( $banInfo[ 'needUpdate' ] ) && ( $banInfo[ 'needUpdate' ] == 1)
				) {
				
				$banInfoTTL = $banInfo[ 'banExpirationTime' ] - CURRENT_TIME;
				// бан уже прошел
				if ( $banInfoTTL <= 0 ) {
					$this->memcache->Delete( $banInfoMemcacheKey );
					return FALSE;
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
						. var_export( $banInfo, TRUE ) . "\n\nnew userInfo "
						. var_export( $this->user, TRUE ) );
					//*/
					$this->memcache->Set( $banInfoMemcacheKey, $banInfo, $banInfoTTL );
					$this->memcache->Set( $key, $this->user, $banInfoTTL );
				}
			}
		}
		
		return TRUE;
	}
	
	
	/**
	 *  проверка строки на CAPS / abuse
	 *  @param string str строка для проверки
	 *  @return bool TRUE | FALSE
	 */
	private function IsStringCapsOrAbuse( $str ) {
		// удаляем обращения вроде [b]MEGAKILLER[/b], bb-код [b][/b]
		$tempStr = preg_replace(
			'/^\[b\][-\.\w\x{400}-\x{45F}\x{490}\x{491}\x{207}\x{239}\[\]]+\[\/b\]|\[b\]|\[\/b\]/uis',
			'', 
			$str
		);
		
		// если остались только пробельные символы, это абуз
		if ( !preg_match( '/[^\s]+/uis', $tempStr ) ) {
			return TRUE;
		}
		
		// URL
		$tempStr = preg_replace('/(?:ht|f)tp[s]{0,1}:\/\/[^\s]+/uis', '', $tempStr);
		
		// коды смайлов
		$tempStr = preg_replace( '/:s:[^:]+:/uis', '',  $tempStr );
		
		// общее кол-во букв независимо от регистра
		preg_match_all(
			'/[a-z\x{400}-\x{45F}\x{490}\x{491}\x{207}\x{239}]/ui',
			$tempStr,
			$matches
		);
		
		$len = count( $matches[ 0 ] );
		
		if ( $len === 0 ) {
			return FALSE;
		}
		
		// кол-во букв в верхнем регистре
		preg_match_all( '/[A-ZА-Я]/u', $tempStr, $matches );
		$capsCount = count( $matches[ 0 ] );
		
		if( $capsCount >= 5 && $capsCount >= ( $len / 2 ) ) {
			return TRUE;
		}
		else {
			return FALSE;
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
			//$isCacheActualMemcacheKey = 'ChatChActual-' . $channelId;
			$channelFileName = CHAT_MEMFS_DIR . '/channel-' . $channelId . '.json';
		}
		else {
			//$isCacheActualMemcacheKey = 'ChatModChActual';
			$channelFileName = CHAT_MEMFS_DIR . '/channel-moderator.json';
		}
		/*
		$isCacheActual = $this->memcache->Get( $isCacheActualMemcacheKey );
		
		// пока кэш актуален, перезаписывать его не нужно
		if ( $isCacheActual == '1' ) {
			return;
		}
		*/
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
			
			/*/ помечаем, что кэш актуален
			$this->memcache->Set(
				$isCacheActualMemcacheKey,
				TRUE,
				CHANNEL_CACHE_ACTUAL_TTL
			);
			*/
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
		// если канал не указан, выбираются сообщения для модераторов
		// по всем каналам
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
		
		$queryString = '
			SELECT id, chat_message.uid, IFNULL( name, "system" ) as name, message, date, channelId,
				(SELECT GROUP_CONCAT(rid SEPARATOR ",") FROM users_roles WHERE users_roles.uid = users.uid) as roleIds
				FROM chat_message '. $index_condition .'
				LEFT JOIN users on users.uid = chat_message.uid
				WHERE '. $channelCondition .'
				date > "' . date( 'Y-m-d H:i:s', CURRENT_TIME - 259200 ) . '" AND
				deletedBy is NULL
				ORDER BY id DESC LIMIT '. $messagesCount;
		
		$queryResult = $this->db->Query( $queryString );
		
		if ( $queryResult === FALSE ) {
			SaveForDebug( 'GetMessagesByChannelId fail ' . $queryString );
			return FALSE;
		}
		
		$messages = array();
		
		while( $msg = $queryResult->fetch_assoc() ) {
			if ( $msg[ 'roleIds' ] === NULL ) {
				$msg[ 'roleIds' ] = array(2);
			} else {
				$msg[ 'roleIds' ] = array_merge(
					array(2),
					array_map( 'intval', explode ( ',', $msg[ 'roleIds' ] ) )
				);
			}
			
			if ( in_array( 3, $msg[ 'roleIds' ] ) ) {
				$msg[ 'role' ] = 'root';
			} elseif ( in_array( 4, $msg[ 'roleIds' ] ) ) {
				$msg[ 'role' ] = 'admin';
			} elseif ( in_array( 5, $msg[ 'roleIds' ] ) ) {
				$msg[ 'role' ] = 'moderator';
			} elseif ( in_array( 9, $msg[ 'roleIds' ] ) ) {
				$msg[ 'role' ] = 'streamer';
			} elseif (
					count( array_intersect( array( 6, 7 ), $msg[ 'roleIds' ] ) ) > 0
				) {
				$msg[ 'role' ] = 'editor';
			} else {
				$msg[ 'role' ] = 'user';
			}
			if ( $msg[ 'uid' ] == -2 ) {
				$msg [ 'name' ] == 'PRIME-TIME';
			}
			$messages[] = $msg;
		}

		return $messages;
	}
	
	
	/**
	 *  проверка сообщения на попадание под признаки автобана
	 *  @param string message сообщение
	 *  @return bool TRUE | FALSE
	 */
	private function CheckForAutoBan( $message ) {
		// 3 или 4 смайла смайла
		if ( in_array( 20, $this->user[ 'roleIds' ] ) ) {
			$pattern = '/(?::s:[^:]+:.*){4,}/usi';
		} else {
			$pattern = '/(?::s:[^:]+:.*){3,}/usi';
		}
		
		if( preg_match( $pattern, $message ) ) {
			$this->BanUser( $this->user[ 'uid' ], $this->user[ 'name' ], 4320, 0, 0,
				CHAT_AUTOBAN_REASON_1, TRUE );
			return TRUE;
		}
		
		return FALSE;
	}
	
	
	/*
	 * удаление недопустимых для данной роли смайлов
	 * @param string message сообщение
	 * @return string отфильтрованное сообщение
	 */
	private function FilterSmiles ( $message ) {
		$queryResult = $this->db->Query(
			'SELECT smiles FROM role_smiles WHERE rid in ('
				. implode( ',', $this->user[ 'roleIds' ] )
				. ')'
		);
		
		if ( $queryResult === FALSE ) {
			return $message;
		}

		$allowed_smiles = array();
		while( $result = $queryResult->fetch_assoc() ) {
			$allowed_smiles = array_merge(
				$allowed_smiles,
				explode( ',', $result['smiles'] )
			);
		}

		preg_match_all( '/:s(:[a-z0-9-]+:)/usi', $message, $matches );
		foreach ( $matches[1] as $match ) {
			if ( !in_array( strtolower($match), $allowed_smiles) ) {
				$message = str_ireplace( ':s' . $match, ' ',  $message );
			}
		}

		return $message;
	}
	
	
	/**
	 *  пост сообщения в чат
	 *  @param string message текст сообщения
	 *  @return bool TRUE в случае успеха, FALSE неудачи
	 */
	public function WriteMessage( $message ) {

		/* удаляем явно не разрешенные символы
		разрешены
		U+0020 - U+003F - знаки препинания и арабские цифры
		U+0040 - U+007E http://ru.wikipedia.org/wiki/Латинский_алфавит_в_Юникоде
		U+0400 - U+045F, U+0490, U+0491, U+0207, U+0239 http://ru.wikipedia.org/wiki/Кириллица_в_Юникоде
		U+2012, U+2013, U+2014 - тире
		*/
		$message = preg_replace(
			'/[^\x20-\x7E\x{400}-\x{45F}\x{490}\x{491}\x{207}\x{239}\x{2012}\x{2013}\x{2014}]+/us',
			'',
			$message
		);
		
		// whitespaces
		$message = preg_replace( '#[\s]+#uis', ' ',  $message );
		
		if( $message === '' ) {
			return FALSE;
		}
		// TODO php 5.4.0 добавить ENT_SUBSTITUTE ?
		$message = htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' );
		
		$channelId = $this->GetChannelId();
		
		if( $this->IsStringCapsOrAbuse( $message ) ) {
			// предотвращаем перевод кодов смайлов в картинки, чтобы не бился html
			$message = preg_replace( '/(?::s)+(:[^:]+:)/uis', '\\1', $message );
			// URL тоже
			if ( mb_stripos( $message, '[url' ) !== FALSE ) {
				// [url=link]text[/url]
				$message = preg_replace(
					'#\[url=((?:ht|f)tps?:\/\/[^\]]+)\](.+?)\[/url\]#uis',
					'\\1 \\2',
					$message
				);
				// [url]link[/url]
				$message = preg_replace(
					'#\[url\]((?:ht|f)tps?:\/\/.+?)\[/url\]#uis',
					'\\1',
					$message
				);
				echo $message;
			}
			
			// length of DB field - length of errorMessage = 1024 - 81
			$maxLength = 943;
			if ( mb_strlen( $message ) > $maxLength ) {
				$message = mb_substr( $message, 0, $maxLength );
			}
			$message = '<span class="red" title="' . $message
				. '">Предупреждение за CAPS / Abuse!</span>';
		}
		else {
			$message = preg_replace(
				'#\[b\](.+?)\[/b\]#uis',
				'<b>\\1</b>',
				$message
			);
		}
		
		if( $this->CheckForAutoBan( $message ) ) {
			return FALSE;
		}
		
		$message = $this->FilterSmiles($message);
				
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
			return TRUE;
		}
		else {
			return FALSE;
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
			SaveForDebug( var_export( $_REQUEST, TRUE ) );
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
		
		if( $queryResult === FALSE ) {
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
	 *  @param bool isAutoBan если автобан, TRUE, иначе FALSE
	 *  @return array возвращает массив вида
	 *  array(
			'code' => 0,// код результата: 0 для ошибки | 1 для успеха
			'error' => 'hack';// текст ошибки
		)
	 */
	public function BanUser( $banUid, $banUserName, $banDurationInMin,
		$banMessageId, $channelId,	$banReasonId = 0, $isAutoBan = FALSE
		) {
		
		$banUid = (int)$banUid;
		$banMessageId = (int)$banMessageId;
		$banReasonId = (int)$banReasonId;
		$banDurationInMin = (int)$banDurationInMin;
		$channelId = (int)$channelId;
		
		// выдаем ошибку, если есть права, но неправильный id сообщения
		if( ( $this->user[ 'rights' ] === 1 && $banMessageId < 0 ) ||
			// либо нет прав, но это не автобан
			( $this->user[ 'rights' ] != 1 && $isAutoBan === FALSE ) ||
			// либо непонятно, кого баним и насколько
			$banUid === 0 ||	$banUserName === '' || $banDurationInMin === 0 ||
			// или неправильная причина бана
			$banReasonId < 0 ) {
			SaveForDebug( var_export( $_REQUEST, TRUE ) );
			
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
		
		// делаем через Add, чтобы одновременно проверить отсутствие флага
		// и установить его
		$isUserBanned = $this->memcache->Add(
			$banInfoMemcacheKey,
			array(
				'banTime' => CURRENT_TIME, 
				'banExpirationTime' => $banExpirationTime
			),
			$banDuration
		);
		
		if ( $isUserBanned === FALSE ) {
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
		
		if( $queryResult === FALSE ) {
			// в случае ошибки с запросом удаляем флаг в мемкеше,
			// чтобы юзера можно было забанить в следующий раз
			$this->memcache->Delete( $banInfoMemcacheKey );
			$result = array(
				'code' => 0,
				'error' => CHAT_RUNTIME_ERROR . '3'
			);
			return $result;
		}
		
		if( CHAT_DELETE_BANNED_USERS_MESSAGE && $isAutoBan === FALSE) {
			$queryString = '
				UPDATE chat_message
				SET deletedBy = "' . $moderatorId . '"
				WHERE id = "'. $banMessageId .'"';
			
			$queryResult = $this->db->Query( $queryString );
			
			if( $queryResult === FALSE ) {
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
			$moderatorsDetails = $this->memcache->Get(
				MODERATORS_DETAILS_MEMCACHE_KEY );
			
			// попытка считать статистику из файла, если ее нет в memcache
			if ( $moderatorsDetails === FALSE ) {
				$moderatorsDetails = GetModeratorDetailsFromFile();
			}
			
			if ( $moderatorsDetails !== FALSE ) {
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
				
				$this->memcache->Set(
					MODERATORS_DETAILS_MEMCACHE_KEY,
					$moderatorsDetails,
					CHAT_MODERATORS_DETAILS_TTL
				);
			}
		}
		
		if ( !$isAutoBan ) {
			// кэш текущего канала
			$this->WriteChannelCache( $channelId );
			// кэш модераторов
			$this->WriteChannelCache( -1 );
		}
		
		$message = $moderatorName . ' забанил ' . $banUserName . ' на '
			. $banDurationInMin.' минут.';
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
	 *  @return bool TRUE успех, FALSE ошибка
	 */
	public function WriteSystemMessage( $message ) {
		list( $message ) = $this->db->PrepareParams( $message );
		
		if ( $message === '' ) {
			return FALSE;
		}
		
		$queryString = '
			INSERT INTO chat_message ( uid, message, date )
			VALUES ( "-1", "'. $message .'", "'. CURRENT_DATE .'")';
		
		$queryResult = $this->db->Query( $queryString );
		
		if( $queryResult ) {
			$this->WriteChannelCache( 0 );
			return TRUE;
		}
		else {
			return FALSE;
		}
	}
}
?>