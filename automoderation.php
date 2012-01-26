<?php
/**
 * код для автомодерации пользователями
 * @author shr, forshr@gmail.com
 *
 */

class AutoModeration {
	private $db, $memcache, $user;
    
	function __construct ( $memcacheObject, $user ) {
		$this->memcache = $memcacheObject;
		$this->user = $user;
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
	 * Проверяет, является ли текущий пользователь гражданином
	 * @return boolean
	 */
	public function IsCitizen() {
		$userInfo = $this->user;
		$uid = $userInfo[ 'uid' ];
		$isCitizenMemcachekey = 'AM_isCitizen_' . $uid;
		$isCitizen = $this->memcache->Get( $isCitizenMemcachekey );
		
		if ( $isCitizen === '1' ) {
			//echo 'гражданин по memcache ;)';
			return true;
		}
		
		//echo 'определение гражданин или нет';
		
		// время после регистрации
		$timeOnSiteAfterReg = CITIZEN_DAYS_ON_SITE_AFTER_REG * 86400;
		
		// время, за которое не должно быть нарушений в чате и форуме
		$timeBeforeNowWithoutInfractions = CITIZEN_DAYS_BEFORE_WITHOUT_INFRACTIONS * 86400;
		
		// если с момента регистрации прошло недостаточно времени
		if ( ( CURRENT_TIME - $userInfo[ 'created' ] < $timeOnSiteAfterReg ) ||
			// либо был бан и с его момента прошло недостаточно времени
			isset( $userInfo[ 'wasBanned' ] ) && $userInfo[ 'wasBanned' ] == '1'
			&& ( CURRENT_TIME - $userInfo[ 'banTime' ] < $timeBeforeNowWithoutInfractions ) ) {
			$this->memcache->Set( $isCitizenMemcachekey, false, CITIZEN_STATUS_TTL );
			return false;
		}
		
		// на форуме
		$queryString = '
			SELECT dateline
			FROM forum_infraction
			WHERE userid = "' . $uid . '"
			ORDER BY dateline DESC LIMIT 1';
		
		$this->SetDatabase();
		$queryResult = $this->db->Query( $queryString );
		
		if( $queryResult->num_rows == 1 ) {
			$userData = $queryResult->fetch_assoc();
			$lastInfractionTime = $userData[ 'dateline' ];

			if ( CURRENT_TIME - $lastInfractionTime < $timeBeforeNowWithoutInfractions ) {
				$this->memcache->Set( $isCitizenMemcachekey, false, CITIZEN_STATUS_TTL );
				return false;
			}
		}
		
		/* / общее число сообщений на форуме и комментариев в новостях
		$query = '
			SELECT posts
			FROM forum_user
			WHERE userid = '. $userId;
		
		$result = mysql_query( $query );
		$userData = mysql_fetch_assoc( $result );
		
		$newsCommentsNeededCount = CITIZEN_POSTS_COUNT - $userData[ 'posts' ];
		
		if( $numberNewsCommentsNeeded > 0 ) {
			$query = '
				SELECT COUNT(*) as commentsCount
				FROM comments
				WHERE uid = '. $userId;
			
			$result = mysql_query( $query );
			$userData = mysql_fetch_assoc( $result );
			
			if ( $userData[ 'commentsCount' ] <  $newsCommentsNeededCount ) {
				AutoModerationMemcacheSet( $memcacheObj, $isCitizenMemcachekey, false,
					CITIZEN_STATUS_TTL );
				return false;	
			}
		}
		//*/
		// число сообщений в чате
		
		// делаем через memcache, чтобы лишний раз не делать COUNT из базы
		$chatMessagesCountMemcacheKey = 'AM_uid_'. $uid .'_chatMsgCount';
		$chatMessagesCount = $this->memcache->Get( $chatMessagesCountMemcacheKey );
		
		// кол-во сообщений неизвестно, делаем запрос в базу
		if ( $chatMessagesCount === false ) {
			$queryString = '
				SELECT COUNT(id) as chatMessagesCount
				FROM chat_message
				WHERE uid = "' . $uid . '"';
			
			$queryResult = $this->db->Query( $queryString );
			$userData = $queryResult->fetch_assoc();
			
			$chatMessagesCount = $userData[ 'chatMessagesCount' ];
			
			$this->memcache->Set( $chatMessagesCountMemcacheKey, $chatMessagesCount,
				CITIZEN_CHAT_MSG_COUNT );
		}
		
		if( $chatMessagesCount < CITIZEN_CHAT_POSTS_COUNT ) {
			$this->memcache->Set( $isCitizenMemcachekey, false, CITIZEN_STATUS_TTL );
			return false;
		}
		
		$this->memcache->Set( $isCitizenMemcachekey, true, CITIZEN_STATUS_TTL );
		return true;
	}
	
	
	/**
	 * Проголосовать гражданину за выдачу бана
	 * @param int uid - id пользователя, которого хотят забанить
	 * @param string userName - его имя, нужно для выдачи сообщений
	 * на стороне клиента оно уже есть
	 * @param int messageId - id сообщения, за которое хотят бан
	 * @param int reasonId - id причины, нарушения
	 * @return array возвращает массив с ключами code и message
	 * code равен 0 для ошибки, 1 для успеха
	 * result - сообщение пользователю
	 */
	public function VoteForUserBan( $uid, $userName, $messageId, $reasonId = 0 ) {
		$citizenId = $this->user[ 'uid' ];
		
		if ( $this->IsCitizen() == false ) {
			$result = array(
				'code' => 0,
				'result' => 'Вы не гражданин, поэтому не можете голосовать.'
			);
			return $result;
		}
		
		$uid = (int)$uid;
		
		// да, я не доверяю чатику
		// в запросах оно не используется, поэтому фильтровать можно, как угодно
		$userName = preg_replace( '/[^_a-zа-я0-9]*/ui', '', $userName );
		
		$messageId = (int)$messageId;
		$reasonId = (int)$reasonId;
		
		if ( $reasonId > CITIZEN_REASONS_COUNT || $uid == 0 || $userName == ''
			|| $citizenId == 0 || $messageId == 0 ) {
			$result = array(
				'code' => 0,
				'result' => 'Неверные параметры. Хак? За тобой уже выехал Каби xD'
			);
			return $result;
		}
		
		$banInfoMemcacheKey = 'ChatAmVotesForUid_'. $uid;
		$banInfo = $this->memcache->Get( $banInfoMemcacheKey );
		
		$vote = array(
			'citizenId' => $citizenId,
			'messageId' => $messageId,
			'time' => CURRENT_TIME
		);
		
		// первый голос за бан
		if ( $banInfo === FALSE ) {
			$banInfo[ $reasonId ] = array(
				'votesCount' => 1,
				'votes' => array( $vote )
			);
		}
		// последующие голоса
		else {
			// не учитываем повторные голоса за бан того же пользователя
			foreach( $banInfo as $reason => $votesForReason ) {
				foreach( $votesForReason[ 'votes' ] as $existVote ) {
					if ( $existVote[ 'citizenId' ] == $citizenId ) {
						$result = array(
							'code' => 0,
							'result' => 'Вы уже голосовали за бан '. $userName
						);
						return $result;
					}
				}
			}
			
			if ( isset( $banInfo[ $reasonId ] ) ) {
				// TODO для читаемости заменить на += 1 ?
				// Но ++ почему-то не работает, так что надо будет проверить
				$banInfo[ $reasonId ][ 'votesCount' ] = $banInfo[ $reasonId ][ 'votesCount' ] + 1;
				$banInfo[ $reasonId ][ 'votes' ][] = $vote;
			}
			else {
				$banInfo[ $reasonId ] = array(
					'votesCount' => 1,
					'votes' => array( $vote )
				);
			}
		}
		
		$isMoreVotesNeeded = true;
		
		foreach( $banInfo as $reason => $votesForReason ) {
			if ( $votesForReason[ 'votesCount' ] >= CITIZEN_VOTES_NEEDED ) {
				$isMoreVotesNeeded = false;
				$realReasonId = $reason;
				break;
			}
		}
		
		if ( $isMoreVotesNeeded ) {
			$this->memcache->Set( $banInfoMemcacheKey, $banInfo, CITIZEN_VOTE_TTL );
		}
		else {
			return $this->BanUserByCitizens( $uid, $userName, $realReasonId,
				$banInfo[ $realReasonId ][ 'votes' ] );
		}
		
		//print_r( $banInfo );
		
		$result = array(
			'code' => 1,
			'result' => 'Ваш голос учтен. Спасибо.'
		);
		
		return $result;
	}
	
	
	/**
	 * Бан пользователя гражданами
	 * @param int uid - id пользователя, которого надо забанить
	 * @param string userName - его ник, для отображения сообщения о бане в чат,
	 * чтобы не делать запрос, можно взять его - т.к. уже есть на клиенте в чате
	 * @param int reasonId - id причины для выдачи бана или нарушения
	 * @param array votes массив с голосами за бан, каждый голос сохраняется в базу
	 * выглядит так, см. VoteForUserBan()
	 * $vote = array(
			'citizenId' => $citizenId,
			'messageId' => $messageId,
			'time' => CURRENT_TIME
		);
	 */
	private function BanUserByCitizens( $uid, $userName, $reasonId, $votes ) {
		// проверяем флаг в memcache на случай бана от модератора или граждан
		$banInfoMemcacheKey = 'Chat_uid_' . $uid . '_BanInfo';
		//SaveForDebug( 'BanUserByCitizens ' . $banInfoMemcacheKey );
		$banInfo = $this->memcache->Get( $banInfoMemcacheKey );
		
		if ( $banInfo != false ) {
			$result = array(
				'code' => 0,
				'result' => 'Уже забанен'
			);
			return $result;
		}
		
		$this->SetDatabase();
		
		// длительность бана расчитывается специальной функцией
		$banDurationInfo = $this->GetBanDuration( $uid, $reasonId );
		
		$banDuration = $banDurationInfo[ 'banDuration' ];
		$banSerialNumber = $banDurationInfo[ 'banSerialNumber' ];  
		 
		$banDurationInSeconds = $banDuration * 60;
		$banExpirationTime = CURRENT_TIME + $banDurationInSeconds;
		
		$resultQuery = '
			INSERT INTO chat_ban
			( uid, banExpirationTime, moderatorId, banMessageId, banReasonId, banTime, banDuration )
			VALUES ';
		
		/*	поскольку несколько пользователей могут пожаловаться на одно сообщение,
		 *	то скрывать его, ставя флаг в базе несколько раз, не стоит,
		 *	лучше предварительно отфильтровать дубли
		 */	
		$updateQueryArray = array();
		$votesValueForQuery = array();
		
		foreach( $votes as $vote ) {
			$votesValueForQuery[] = '('.
				$uid .', '.
				$banExpirationTime .', '.
				$vote[ 'citizenId' ] .', '.
				$vote[ 'messageId' ] .', "'.
				$reasonId .'", '.
				CURRENT_TIME .', '.
				$banDurationInSeconds .'
			)';
			
			if( CHAT_DELETE_BANNED_USERS_MESSAGE ) {
				$updateQueryArray[] = '
					UPDATE chat_message
					SET deletedBy = "0"
					WHERE id="'. $vote[ 'messageId' ] .'";';
			}
		}
		
		$resultQuery .= implode( ',', $votesValueForQuery ) . ';';
		
		$noError = true;
		$queryResult = $this->db->Query( $resultQuery );
		
		if ( $queryResult == false ) {
			SaveForDebug( $resultQuery );
			$noError = false;
		}
		
		if ( CHAT_DELETE_BANNED_USERS_MESSAGE ) {
			$updateQueryArray = array_unique( $updateQueryArray );
			
			foreach( $updateQueryArray as $queryString ) {
				$queryResult = $this->db->Query( $queryString );
				
				if ( $queryResult == false ) {
					SaveForDebug( $queryString );
					$noError = false;
				}
			}
		}
		
		if ( $noError == true ) {
			/*	помечаем в memcache, что пользователь не гражданин,
			 *	чтобы он не голосовал после своего бана,
			 *	хотя у него не будет менюшки, но поснифать ссылку теоретически может
			 */ 
			$this->SaveIsUserCitizenInMemcache( $uid, false );
			
			// сохраняем данные по бану в memcache
			$this->memcache->Set(
				$banInfoMemcacheKey,
				array(
					'banTime' => CURRENT_TIME,
					'banExpirationTime' => $banExpirationTime
				),
				$banDurationInSeconds
			);
			
			global $chat;
			$chat->SetDatabase( $this->db );
			
			$chat->WriteSystemMessage( 'Граждане SC2TV.RU забанили '. $userName .' на '
				. $banDuration .' минут за '. $banSerialNumber . ' бан.' );
			
			$result = array(
				'code' => 1,
				'result' => 'Вы успешно забанили пользователя.'
			);
			return $result;
		}
	}
	
	
	/**
	 * сохранение в memcache, является ли пользователь гражданином
	 * @param int $uid - id пользователя
	 * @param boolean $value - да - true, нет - false
	 */
	private function SaveIsUserCitizenInMemcache( $uid, $value ) {
		$isCitizenMemcachekey = 'AM_isCitizen_'. $uid;
		$this->memcache->Set( $isCitizenMemcachekey, $value, CITIZEN_STATUS_TTL );
	}
	
	
	/**
	 * сохранение в memcache, является ли пользователь гражданином
	 * @param int $uid - id пользователя
	 */
	private function DeleteIsUserCitizenInMemcache( $uid ) {
		$isCitizenMemcachekey = 'AM_isCitizen_'. $uid;
		$this->memcache->Delete( $isCitizenMemcachekey );
	}
	
	
	/**
	 * Отмена бана
	 * @param string banKey - ключ, определяющий бан, состоит из
	 * uid забаненного и времени истечения бана, разделенных подчеркиванием _
	 * оба значения состоят из цифр, поэтому никаких конфликтов _ не дает
	 * @param string reason причина разбана
	 * @param string banModerator - забанить модератора или граждан - true; нет - false
	 * @param int moderatorBanTime время бана в минутах
	 * @return array возвращает массив с ключами code и message;
	 * code равен 0 для ошибки, 1 для успеха
	 * result - сообщение для пользователя
	 */
	public function CancelBan( $banKey, $reason, $banModerator, $moderatorBanTime ) {
		$judgeId = $this->user[ 'uid' ];
		// проверка на право отменять бан
		if ( $this->CheckRightToModifyBan() == false ) {
			$result = array(
				'code' => 0,
				'result' => 'Нет прав отменять бан.'
			);
			return $result;
		}
		
		list( $uid, $banExpirationTime ) = explode( '_', $banKey );
		$uid = (int)$uid;
		$banExpirationTime = (int)$banExpirationTime;
		$moderatorBanTime=(int)$moderatorBanTime;
		$banModerator = (int)$banModerator;
		
		list( $reason ) = $this->db->PrepareParams( $reason );
		
		if ( $reason == '' ) {
			$result = array(
				'code' => 0,
				'result' => 'Введите причину.'
			);
			return $result;
		}
		
		$reason = date( 'm-d H:i:s', CURRENT_TIME ) . ' Отмена. Причина: ' . $reason;
		
		$queryString = '
			UPDATE chat_ban
			SET status = 0,
			banModificationReason = \''. $reason .'\',
			banModificationUserId = '. $judgeId .'
			WHERE uid = "'. $uid .'"
			AND banExpirationTime = "'. $banExpirationTime .'"';
		
		$queryResult = $this->db->Query( $queryString );
		
		if ( $queryResult === false ) {
			SaveForDebug( $queryString );
			$result = array(
				'code' => 0,
				'result' => CHAT_RUNTIME_ERROR . 'am 1.'
			);
			return $result;
		}
		
		$resultMessage = 'Бан успешно отменен.';
		
		// удаляем информацию в memcache о гражданинстве
		$this->DeleteIsUserCitizenInMemcache( $uid );
		
		// форсим релогин
		$banInfoMemcacheKey = 'Chat_uid_' . $uid . '_BanInfo';
		
		$this->memcache->Set(
			$banInfoMemcacheKey,
			array(
				'needUpdate' => 1,
				'needRelogin' => 1
			),
			259200
		);
		
		// теперь обработка варианта с баном граждан, которые в терминах системы,
		// если абстрагироваться, составляют модератора ;)
		// на случай флешмобов 
		if ( $banModerator == 1 ) {
			
			if ( $moderatorBanTime == 0 ) {
				$result = array(
					'code' => 0,
					'result' => 'Введите время бана.'
				);
				return $result;
			}
			
			$queryString = '
				SELECT moderatorId
				FROM chat_ban
				WHERE uid = "' . $uid . '"
				AND banExpirationTime = "' . $banExpirationTime . '"';
			
			$queryResult = $this->db->Query( $queryString );
			
			if( $queryResult->num_rows == 0 ) {
				SaveForDebug( $queryString );
				$result = array(
					'code' => 0,
					'result' => CHAT_RUNTIME_ERROR . 'am 2.'
				);
				return $result;
			}
		 
			$banDurationInSeconds = $moderatorBanTime * 60;
			$moderatorbanExpirationTime = CURRENT_TIME + $banDurationInSeconds;
			
			$noError = true;
			
			while ( $banData = $queryResult->fetch_assoc() ) {
				$moderatorId = (int)$banData[ 'moderatorId' ];
				
				// TODO пометить в xwiki, что banReasonId = 99 - обратный бан граждан
				$queryString =
					'INSERT INTO chat_ban
					( uid, banExpirationTime, moderatorId, banReasonId, banTime, banDuration )
					VALUES(	'.
						$moderatorId .', '.
						$moderatorbanExpirationTime .', '.
						$judgeId .',
						99, '.
						CURRENT_TIME .', '.
						$banDurationInSeconds .'
					);
				';
				
				$newResult = $this->db->Query( $queryString );
				
				if ( $newResult == false ) {
					$noError = false;
					SaveForDebug( $queryString );					
				}
				
				$banInfoMemcacheKey = 'Chat_uid_' . $moderatorId . '_BanInfo';
		
				$this->memcache->Set(
					$banInfoMemcacheKey,
					array(
						'needUpdate' => 1,
						'banTime' => CURRENT_TIME, 
						'banExpirationTime' => $moderatorbanExpirationTime
					),
					$moderatorbanExpirationTime - CURRENT_TIME
				);
			}
			
			if ( $noError == true ) {
				$resultMessage .= ' Граждане или модератор забанен.';
			}
			else {
				$result = array(
					'code' => 0,
					'result' => CHAT_RUNTIME_ERROR . 'Ошибка базы 3.'
				);
				return $result;
			}
		}
		
		$result = array(
			'code' => 1,
			'result' => $resultMessage
		);
		return $result;
	}
	
	
	/**
	 * Изменение бана
	 * @param string banKey - ключ, определяющий бан, состоит из
	 * uid забаненного и времени истечения бана, разделенных подчеркиванием _
	 * оба значения состоят из цифр, поэтому никаких конфликтов _ не дает
	 * @param string reason - причина разбана
	 * @param int newBanTime - новое время бана в минутах
	 * @return array возвращает массив с ключами code и message;
	 * code равен 0 для ошибки, 1 для успеха
	 * result - сообщение пользователю
	 */
	public function EditBan( $banKey, $reason, $newBanTime ) {
		$judgeId = $this->user[ 'uid' ];
		
		// проверка на право редактировать бан
		if ( $this->CheckRightToModifyBan() == false ) {
			$result = array(
				'code' => 0,
				'result' => 'Нет прав редактировать бан.'
			);
			return $result;
		}
		
		list( $uid, $banExpirationTime ) = explode( '_', $banKey );
		$uid = (int)$uid;
		$banExpirationTime = (int)$banExpirationTime;
		$newBanTime=(int)$newBanTime;
		
		list( $reason ) = $this->db->PrepareParams( $reason );
		
		if ( $reason == '' ) {
			$result = array(
				'code' => 0,
				'result' => 'Введите причину.'
			);
			return $result;
		}
		
		$reason = date( 'm-d H:i:s', CURRENT_TIME ) . ' Длительность изменена на '. $newBanTime .' мин. Причина: ' . $reason;
		
		$banDurationInSeconds = $newBanTime * 60;
		
		$queryString = '
			UPDATE chat_ban
			SET banExpirationTime = banTime + '. $banDurationInSeconds .',
			banDuration = '. $banDurationInSeconds .',
			banModificationReason = "'. $reason .'",
			banModificationUserId = "'. $judgeId .'",
			status = "1"
			WHERE uid = "'. $uid .'"
			AND banExpirationTime = "'. $banExpirationTime .'"';
		
		$queryResult = $this->db->Query( $queryString );
		
		if ( $queryResult == false ) {
			SaveForDebug( $queryString );
			$result = array(
				'code' => 0,
				'result' => CHAT_RUNTIME_ERROR . 'Ошибка базы 4.'
			);
			return $result;
		}
		
		// изменяем данные по бану в memcache
		$banInfoMemcacheKey = 'Chat_uid_' . $uid . '_BanInfo';
		$banInfo = $this->memcache->Get( $banInfoMemcacheKey );
		/*SaveForDebug( 'EditBan banInfo banInfoMemcacheKey ='
			. $banInfoMemcacheKey . var_export( $banInfo, true ) );
		/*/
		if ( $banInfo == false ) {
			// форсим релогин
			$this->memcache->Set(
				$banInfoMemcacheKey,
				array(
					'needUpdate' => 1,
					'needRelogin' => 1
				),
				259200
			);
		}
		else {
			$banInfo[ 'banExpirationTime' ] = $banInfo[ 'banTime' ] + $banDurationInSeconds;
			$banInfo[ 'needUpdate' ] = 1;
			
			$banInfoTtl = $banInfo[ 'banExpirationTime' ] - CURRENT_TIME;
			
			if ( $banInfoTtl <= 0 ) {
				$banInfoTtl = 259200;
			}
			/*
			SaveForDebug( 'EditBan new banInfo' . var_export( $banInfo, true ) . ' ttl = ' .
			$banInfoTtl );
			*/
			$this->memcache->Set(
				$banInfoMemcacheKey,
				$banInfo,
				$banInfoTtl
			);
		}
		
		// удаляем информацию в memcache о гражданинстве
		$this->DeleteIsUserCitizenInMemcache( $uid );
		
		$result = array(
			'code' => 1,
			'result' => 'Длительность бана успешно изменена.'
		);
		return $result;
	}
	
	
	/**
	 * Сохранение жалобы на бан в memcache
	 * @param string banKey - ключ, определяющий бан, состоит из
	 * uid забаненного и времени истечения бана, разделенных подчеркиванием _
	 * оба значения состоят из цифр, поэтому никаких конфликтов _ не дает
	 * @param string reason причина разбана
	 * code равен 0 для ошибки, 1 для успеха
	 * result - сообщение пользователю
	 */
	public function ComplainBan( $banKey, $reason ) {
		$uid = $this->user[ 'uid' ];
		$userName = $this->user[ 'name' ];
		
		$reason = preg_replace( '/[^\x20-\x7E\x{400}-\x{45F}\x{490}\x{491}\x{207}\x{239}]+/us', '',  $reason );
		$reason = preg_replace( '#[\s]+#uis', ' ', $reason );
		$reason = htmlspecialchars( $reason, ENT_QUOTES, 'UTF-8' );
		
		if ( $reason == '' ) {
			$result = array(
				'code' => 0,
				'result' => CHAT_RUNTIME_ERROR . 'Введите причину.'
			);
			return $result;
		}
		
		$banKey = preg_replace( '/[^_\d]*/ui', '', $banKey );
		
		if ( $banKey == '' ) {
			$result = array(
				'code' => 0,
				'result' => CHAT_RUNTIME_ERROR . 'Ошибка memcache 1.'
			);
			return $result;			
		}
		
		$complain = array(
			'userName' => $userName,
			'reason' => $reason,
			'date' => date( 'm-d H:i:s', CURRENT_TIME )
		);
		
		// жалоба граждан идет за 2 от обычных пользователей
		if ( $this->IsCitizen() == true ) {
			$complainIncrement = 2;
		}
		else {
			$complainIncrement = 1;
		}
		
		$complainsList = $this->memcache->Get( COMPLAINS_LIST_MEMCACHE_KEY );
		
		// предполагаем, что это самая 1я жалоба вообще
		$complainInfo = false;
		
		if ( isset( $complainsList[ $banKey ] ) ) {
			$complainInfo = $complainsList[ $banKey ];
		}
		
		// 1я жалоба на этого пользователя
		if ( $complainInfo === false ) {
			$complainInfo = array(
				'count' => $complainIncrement,
				'complains' => array( $complain )
			);
		}
		// последующие жалобы
		else {
			// пропускаем повторные жалобы
			foreach( $complainInfo[ 'complains' ] as $oldComplain ) {
				if ( $oldComplain[ 'userName' ] == $userName ) {
					$result = array(
						'code' => 0,
						'result' => 'Вы уже жаловались на этот бан.'
					);
					return $result;
				}
			}
			
			// TODO для читаемости заменить на += ?
			// Но ++ почему-то не работает, так что надо будет проверить
			$complainInfo[ 'count' ] = $complainInfo[ 'count' ] + $complainIncrement;
			$complainInfo[ 'complains' ][] = $complain;
		}
		
		// запоминаем время, когда последний раз жаловались на бан, чтобы почистить массив
		$complainInfo[ 'lastTime' ] = CURRENT_TIME;
		$complainsList[ $banKey ] = $complainInfo;
		
		// делаем копию массива для сброса в файл
		$complainsListPublic = $complainsList;
		
		foreach( $complainsList as $key => $complainsForBan ) {
			if ( $complainsForBan[ 'lastTime' ] < CURRENT_TIME - COMPLAINS_TTL ) {
				unset( $complainsList[ $key ], $complainsListPublic[ $key ] );
			}
			
			// жалобы, не набравшие в сумме нужное кол-во, выводить не надо
			if ( $complainsForBan[ 'count' ] < COMPLAINS_NEEDED ) {
				unset( $complainsListPublic[ $key ] );
			}
		}
		
		// пробуем получить файл для записи
		$complainsCacheFile = fopen( CHAT_COMPLAINS_FOR_BANS, 'w' );
	
		if ( flock( $complainsCacheFile, LOCK_EX | LOCK_NB ) ) {
			// жалоб не так много, поэтому можно пока перезаписывать весь массив
			$this->memcache->Set( COMPLAINS_LIST_MEMCACHE_KEY, $complainsList, COMPLAINS_TTL );
			
			// и перезаписать файл в memfs
			$dataJson = json_encode( array( 'complainsList' => $complainsListPublic ) );
		
			fwrite( $complainsCacheFile, $dataJson );
			fflush( $complainsCacheFile );
			
			flock( $complainsCacheFile, LOCK_UN );
			
			$result = array(
				'code' => 1,
				'result' => 'Ваша жалоба на бан принята.'
			);
		}
		else {
			$result = array(
				'code' => 0,
				'result' => 'Ошибка. Повторите попытку.'
			);
		}
		
		fclose( $complainsCacheFile );
		
		return $result;
	}
	
	
	/**
	 * Проверка прав на возможность совершать операции с банами
	 * @return boolean
	 */
	private function CheckRightToModifyBan() {
		switch ( $this->user[ 'rid' ] ) {
			case 3:
			case 4:
			case 5:
				return true;
			break;
			
			default:
				return false;
			break;
		}
	}
	
	
	/**
	 * Определение длительности нового бана с учетом количества предыдущих и причины бана
	 * @param int uid - id пользователя, которого банят
	 * @param int reasonId - id причины
	 * @return array массив с ключами banDuration - длительность нового бана и
	 * banSerialNumber - порядковый номер этого бана за просматриваемый период 
	 */
	private function GetBanDuration( $uid, $reasonId ) {
		$banSerialNumber = 1;
		
		$timePenaltyForBan = CURRENT_TIME - CITIZEN_REPEATED_BAN_TTL * 86400;
		
		$queryString = '
			SELECT uid
			FROM chat_ban
			WHERE uid = "'. $uid .'"
			AND status = 1
			AND banReasonId = "'. $reasonId .'"
			AND banTime > ' . $timePenaltyForBan .'
			GROUP BY banExpirationTime';
		
		$queryResult = $this->db->Query( $queryString );
		
		$banSerialNumber += $queryResult->num_rows;
		
		// геометрическая прогрессия 2^( n-1 ) * base,
		// где n - порядковый номер бана, base - длительность 1го бана
		// попросту - удвоение
		$banDuration = pow( 2, $banSerialNumber - 1 ) *
			$this->GetBanDurationByReasonId( $reasonId ); 
		
		$result = array(
			'banDuration' => $banDuration,
			'banSerialNumber' => $banSerialNumber
		);
		
		return $result;
	}
	
	
	/**
	 * Определение длительности бана по нарушению, в минутах
	 * @param int $reasonId - id нарушения
	 * @return int
	 */
	private function GetBanDurationByReasonId( $reasonId ) {
		switch( $reasonId ) {
			// Мат
			case 1:
			// Серьезные оскорбления
			case 5:
			// Национализм, нацизм
			case 6:
			// Вредные ссылки
			case 12:
				$banDuration = 1440;
			break;
			
			// Завуалированный мат
			case 2:
			// Спам грубыми словами
			case 3:
			// Легкие оскорбления
			case 4:
			// Реклама
			case 7:
			// Спам
			case 8:
			// Клевета
			case 9:
			// Негативный троллинг
			case 10:
			// Транслит, удаффщина, капсы
			case 11:
			// Вредные флэшмобы
			case 13:
				$banDuration = 10;
			break;
			
			default:
				$banDuration = 10;
		}
		
		return $banDuration;
	}
}
?>