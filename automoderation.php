<?php
/**
 * код для автомодерации пользователями
 * @author shr
 *
 */

class AutoModeration {
	private $db, $memcache, $user;
  
	function __construct( $memcacheObject, $user ) {
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
		if ( isset( $this->user[ 'isCitizen' ] ) ) {
			return $this->user[ 'isCitizen' ];
		}
		
		$chatAuthMemcacheKey = 'ChatUserInfo_' . $_COOKIE[ DRUPAL_SESSION ];
		
		// время после регистрации
		$timeOnSiteAfterReg = CITIZEN_DAYS_ON_SITE_AFTER_REG * 86400;
		
		// время, за которое не должно быть нарушений в чате и форуме
		$timeToLookForInfractions = CITIZEN_DAYS_BEFORE_WITHOUT_INFRACTIONS * 86400;
		
		// если с момента регистрации прошло недостаточно времени
		if ( ( CURRENT_TIME - $this->user[ 'created' ] < $timeOnSiteAfterReg ) ||
			// или есть бан(ы) в чате, но он(и) недопустимы для получения статуса
			// гражданина
			$this->DoesUserHaveNotAllowedBans($this->user,$timeToLookForInfractions)){
			
			$this->user[ 'isCitizen' ] = FALSE;
			$this->user[ 'noCitizenReason' ] = CHAT_AM_NOT_CITIZEN;
			$this->memcache->Set( $chatAuthMemcacheKey, $this->user,
				CHAT_USER_AUTHORIZATION_TTL );
			
			return FALSE;
		}
		
		// на форуме
		$queryString = '
			SELECT SUM(points) as infractionTotalCount
			FROM forum_infraction
			WHERE userid = "' . $this->user[ 'uid' ] . '"
			AND action = 0
			AND points >= 1
			AND dateline > ' .( CURRENT_TIME - $timeToLookForInfractions );
		
		$this->SetDatabase();
		$queryResult = $this->db->Query( $queryString );
		
		if ( $queryResult === FALSE ) {
			SaveForDebug( $queryString );
			exit;
		}
		
		if( $queryResult->num_rows > 0 ) {
			$userData = $queryResult->fetch_assoc();
			$infractionTotalCount = $userData[ 'infractionTotalCount' ];
				
			if( $infractionTotalCount > 1 ) {
				$this->user[ 'isCitizen' ] = FALSE;
				$this->user[ 'noCitizenReason' ] =
					'количество нарушений на форуме за последние '
					. CITIZEN_DAYS_BEFORE_WITHOUT_INFRACTIONS
					.' дней с числом баллов больше одного (' . $infractionTotalCount
					.') превышает допустимое (1)';
				$this->memcache->Set( $chatAuthMemcacheKey, $this->user,
					CHAT_USER_AUTHORIZATION_TTL );
				
				return FALSE;
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
				AutoModerationMemcacheSet( $memcacheObj, $isCitizenMemcachekey, FALSE,
					CITIZEN_STATUS_TTL );
				return FALSE;	
			}
		}
		//*/
		// число сообщений в чате
		
		// делаем через memcache, чтобы лишний раз не делать COUNT из базы
		$messagesCountMemcacheKey = 'AM_uid_' . $this->user['uid'] .'_chatMsgCount';
		$chatMessagesCount = $this->memcache->Get( $messagesCountMemcacheKey );
		
		// кол-во сообщений неизвестно, делаем запрос в базу
		if ( $chatMessagesCount === FALSE ) {
			$queryString = '
				SELECT COUNT(id) as chatMessagesCount
				FROM chat_message
				WHERE uid = "' . $this->user[ 'uid' ] . '"';
			
			$queryResult = $this->db->Query( $queryString );
			
			if ( $queryResult === FALSE ) {
				SaveForDebug( $queryString );
				exit;
			}
			
			$userData = $queryResult->fetch_assoc();
			
			$chatMessagesCount = $userData[ 'chatMessagesCount' ];
			
			$this->memcache->Set( $messagesCountMemcacheKey, $chatMessagesCount,
				CITIZEN_CHAT_MSG_COUNT );
		}
		
		if( $chatMessagesCount < CITIZEN_CHAT_POSTS_COUNT ) {
			$this->user[ 'isCitizen' ] = FALSE;
			$this->user[ 'noCitizenReason' ] =
				'количество сообщений в чате (' . $chatMessagesCount
				.') меньше необходимого (' . CITIZEN_CHAT_POSTS_COUNT . ')';
			$this->memcache->Set( $chatAuthMemcacheKey, $this->user,
				CHAT_USER_AUTHORIZATION_TTL );
			
			return FALSE;
		}
		
		$this->user[ 'isCitizen' ] = TRUE;
		$this->memcache->Set( $chatAuthMemcacheKey, $this->user,
			CHAT_USER_AUTHORIZATION_TTL );
		return TRUE;
	}
	
	
	/**
	 * проверка на наличие у пользователя банов в чате, недопустимых для статуса
	 * гражданина
	 * @param array $user - информация по пользователю
	 * @param int $timeToLookForInfractions - величина просматриваемого на баны
	 * периода времени в секундах
	 * @return bool
	 */
	private function DoesUserHaveNotAllowedBans($user, $timeToLookForInfractions){
		// банов не было
		if ( empty( $user[ 'wasBanned' ] ) ) {
			return FALSE;
		}
		
		// с момента последнего бана прошло достаточно времени
		if ( CURRENT_TIME - $user[ 'banTime' ] >= $timeToLookForInfractions ) {
			return FALSE;
		}
		// длительность последнего бана больше допустимой для граждан
		elseif( 
			$user['banExpirationTime'] - $user['banTime'] >	CITIZEN_ALLOWED_BAN_TIME
			) {
			return TRUE;
		}
		else {
			// проверка на кол-во банов, для граждан допустим только 1
			$queryString = '
				SELECT COUNT(DISTINCT banTime) as bansCount
				FROM chat_ban
				WHERE uid = "' . $user[ 'uid' ] . '"
				AND status = 1
				AND banTime > ' .( CURRENT_TIME - $timeToLookForInfractions );
			
			$this->SetDatabase();
			$queryResult = $this->db->Query( $queryString );
			
			if ( $queryResult === FALSE ) {
				SaveForDebug( $queryString );
				exit;
			}
			
			$userData = $queryResult->fetch_assoc();
			$bansCount = $userData[ 'bansCount' ];
			
			if( $bansCount > 1 ) {
				return TRUE;
			}
			
			return FALSE;
		}
	}
	
	
	/**
	 * Проголосовать гражданину за выдачу бана
	 * @param int uid - id пользователя, которого хотят забанить
	 * @param string userName - его имя, нужно для выдачи сообщений
	 * на стороне клиента оно уже есть
	 * @param int messageId - id сообщения, за которое хотят бан
	 * @param int reasonId - id причины, нарушения
	 * @return array возвращает массив с ключами code и result
	 * code равен 0 для ошибки, 1 для успеха
	 * result - сообщение пользователю
	 */
	public function VoteForUserBan( $uid, $userName, $messageId, $reasonId ) {
		$citizenId = $this->user[ 'uid' ];
		
		if ( $this->IsCitizen() === FALSE ) {
			$result = array(
				'code' => 0,
				'result' => 'Вы не гражданин и не можете голосовать.<br/>Причина: '
					. $this->user[ 'noCitizenReason' ]
			);
			return $result;
		}
		
		$uid = (int)$uid;
		
		// да, я не доверяю чатику
		// в запросах оно не используется, поэтому фильтровать можно, как угодно
		$userName = preg_replace( '/[^_a-zа-я0-9 ]+/ui', '', $userName );
		
		$messageId = (int)$messageId;
		$reasonId = (int)$reasonId;
		
		if ( $uid === 0 || $userName === '' || $messageId === 0 || $citizenId == 0
			|| $reasonId <= 0 || $reasonId > CITIZEN_REASONS_COUNT ) {
			$result = array(
				'code' => 0,
				'result' => CHAT_HACK_ATTEMPT
			);
			return $result;
		}
		
		$banInfoMemcacheKey = 'ChatAmVotesForUid_' . $uid;
		$banInfo = $this->memcache->Get( $banInfoMemcacheKey );
		
		$vote = array(
			'citizenId' => $citizenId,
			'messageId' => $messageId,
			'time' => CURRENT_TIME
		);
		
		// первый голос за бан
		if ( $banInfo === FALSE ) {
			$banInfo = array(
				$reasonId => array(
					'votesCount' => 1,
					'votes' => array( $vote )
				)
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
				$banInfo[ $reasonId ][ 'votesCount' ] =
					$banInfo[ $reasonId ][ 'votesCount' ] + 1;
				$banInfo[ $reasonId ][ 'votes' ][] = $vote;
			}
			else {
				$banInfo[ $reasonId ] = array(
					'votesCount' => 1,
					'votes' => array( $vote )
				);
			}
		}
		
		$isMoreVotesNeeded = TRUE;
		
		foreach( $banInfo as $reason => $votesForReason ) {
			if ( $votesForReason[ 'votesCount' ] >= CITIZEN_VOTES_NEEDED ) {
				$isMoreVotesNeeded = FALSE;
				$realReasonId = $reason;
				break;
			}
		}
		
		if ( $isMoreVotesNeeded ) {
			$setResult = $this->memcache->Set( $banInfoMemcacheKey, $banInfo,
				CITIZEN_VOTE_TTL );
			if ( $setResult === FALSE ) {
				$result = array(
					'code' => 0,
					'result' => 'Ошибка, повторите попытку.'
				);
				return $result;
			}
		}
		else {
			return $this->BanUserByCitizens( $uid, $userName, $realReasonId,
				$banInfo[ $realReasonId ][ 'votes' ] );
		}
		
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
	 * @param array votes массив с голосами за бан, каждый сохраняется в базу
	 * выглядит так, см. VoteForUserBan()
	 * $vote = array(
			'citizenId' => $citizenId,
			'messageId' => $messageId,
			'time' => CURRENT_TIME
		);
	 * @return array возвращает массив с ключами code и result
	 * code равен 0 для ошибки, 1 для успеха
	 */
	private function BanUserByCitizens( $uid, $userName, $reasonId, $votes ) {
		// Adolf не хочет, чтобы его банили
		if ( $uid === 231 ) {
			global $chat;
			$chat->SetDatabase( $this->db );
			
			$chat->WriteSystemMessage( CHAT_NO_BANS_FOR_ADOLF_TO_MAIN );
			
			$result = array(
				'code' => 0,
				'result' => CHAT_NO_BANS_FOR_ADOLF_TO_USER
			);
			return $result;
		}
		
		// проверяем флаг в memcache на случай бана от модератора или граждан
		$banInfoMemcacheKey = 'Chat_uid_' . $uid . '_BanInfo';
		//SaveForDebug( 'BanUserByCitizens ' . $banInfoMemcacheKey );
		
		// делаем через Add, чтобы одновременно проверить отсутствие флага и
		// установить его; здесь ставится бан на 10 мин, чтобы застолбить бан,
		// значения правильные выставляются дальше по коду
		$isUserBanned = $this->memcache->Add(
			$banInfoMemcacheKey,
			array(
				'banTime' => CURRENT_TIME,
				'banExpirationTime' => CURRENT_TIME + 600
			),
			600
		);
		
		if ( $isUserBanned === FALSE ) {
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
		if ( $banDuration === 0 ) {
			$result = array(
				'code' => 0,
				'result' => CHAT_HACK_ATTEMPT
			);
			return $result;
		}
		
		$banSerialNumber = $banDurationInfo[ 'banSerialNumber' ];  
		
		$banDurationInSeconds = $banDuration * 60;
		$banExpirationTime = CURRENT_TIME + $banDurationInSeconds;
		
		$resultQuery = '
			INSERT INTO chat_ban (
				uid, banExpirationTime, moderatorId, banMessageId, banReasonId, banTime,
				banDuration
			)
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
		
		$noError = TRUE;
		$queryResult = $this->db->Query( $resultQuery );
		
		if ( $queryResult === FALSE ) {
			SaveForDebug( $resultQuery );
			$noError = FALSE;
		}
		elseif ( CHAT_DELETE_BANNED_USERS_MESSAGE ) {
			$updateQueryArray = array_unique( $updateQueryArray );
			
			foreach( $updateQueryArray as $queryString ) {
				$queryResult = $this->db->Query( $queryString );
				
				if ( $queryResult === FALSE ) {
					SaveForDebug( $queryString );
					$noError = FALSE;
				}
			}
		}
		
		if ( $noError === TRUE ) {
			/*	помечаем в memcache, что пользователь не гражданин,
			 *	чтобы он не голосовал после своего бана,
			 *	хотя у него не будет менюшки, но поснифать ссылку теоретически может
			 */ 
			$this->SaveIsUserCitizenInMemcache( $uid, FALSE );
			
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
			
			$chat->WriteSystemMessage(
				'Граждане SC2TV.RU забанили '. $userName . ' на '	. $banDuration
				. ' минут, ' . $banSerialNumber . ' нарушение.'
			);
			
			$result = array(
				'code' => 1,
				'result' => 'Вы успешно забанили пользователя.'
			);
			return $result;
		}
		else {
			// в случае ошибки нужно удалить флаг о бане, чтобы юзера могли забанить
			// при следующей попытке
			$this->memcache->Delete( $banInfoMemcacheKey );
			
			$result = array(
				'code' => 0,
				'result' => 'Ошибка бана автомодерации #1. Сообщите разработчикам.'
			);
			return $result;
		}
	}
	
	
	/**
	 * сохранение в memcache, является ли пользователь гражданином
	 * @param int $uid - id пользователя
	 * @param boolean $value - да - TRUE, нет - FALSE
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
	 * @param string banModerator забанить модератора/граждан - TRUE; нет - FALSE
	 * @param int moderatorBanTime время бана в минутах
	 * @return array возвращает массив с ключами code и result;
	 * code равен 0 для ошибки, 1 для успеха
	 * result - сообщение для пользователя
	 */
	public function CancelBan($banKey, $reason, $banModerator, $moderatorBanTime){
		$judgeId = $this->user[ 'uid' ];
		
		if ( $this->CheckNoRightToModifyBan() ) {
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
		
		$reason = date( 'm-d H:i:s', CURRENT_TIME ) . ' Отмена. Причина: ' .$reason;
		
		$queryString = '
			UPDATE chat_ban
			SET status = 0,
			banModificationReason = \''. $reason .'\',
			banModificationUserId = '. $judgeId .'
			WHERE uid = "'. $uid .'"
			AND banExpirationTime = "'. $banExpirationTime .'"';
		
		$queryResult = $this->db->Query( $queryString );
		
		if ( $queryResult === FALSE ) {
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
			
			if( $queryResult == FALSE || $queryResult->num_rows == 0 ) {
				SaveForDebug( $queryString );
				$result = array(
					'code' => 0,
					'result' => CHAT_RUNTIME_ERROR . 'am 2.'
				);
				return $result;
			}
		 
			$banDurationInSeconds = $moderatorBanTime * 60;
			$moderatorbanExpirationTime = CURRENT_TIME + $banDurationInSeconds;
			
			$noError = TRUE;
			
			while ( $banData = $queryResult->fetch_assoc() ) {
				$moderatorId = (int)$banData[ 'moderatorId' ];
				
				// TODO пометить в xwiki, что banReasonId = 99 - обратный бан граждан
				$queryString =
					'INSERT INTO chat_ban	(
						uid, banExpirationTime, moderatorId, banReasonId, banTime,
						banDuration
					)
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
				
				if ( $newResult == FALSE ) {
					$noError = FALSE;
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
					$banDurationInSeconds
				);
			}
			
			if ( $noError == TRUE ) {
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
	 * @return array возвращает массив с ключами code и result;
	 * code равен 0 для ошибки, 1 для успеха
	 * result - сообщение пользователю
	 */
	public function EditBan( $banKey, $reason, $newBanTime ) {
		$judgeId = $this->user[ 'uid' ];
		
		if ( $this->CheckNoRightToModifyBan() ) {
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
		
		$reason = date( 'm-d H:i:s', CURRENT_TIME ) . ' Длительность изменена на '
			. $newBanTime .' мин. Причина: ' . $reason;
		
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
		
		if ( $queryResult === FALSE ) {
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
			. $banInfoMemcacheKey . var_export( $banInfo, TRUE ) );
		/*/
		if ( $banInfo == FALSE || !isset( $banInfo[ 'banTime' ] ) ) {
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
			$banInfo[ 'banExpirationTime' ] = $banInfo[ 'banTime' ] +
				$banDurationInSeconds;
			$banInfo[ 'needUpdate' ] = 1;
			
			$banInfoTtl = $banInfo[ 'banExpirationTime' ] - CURRENT_TIME;
			
			if ( $banInfoTtl <= 0 ) {
				$banInfoTtl = 259200;
			}
			/* SaveForDebug(
				'EditBan new banInfo' . var_export( $banInfo, TRUE ) . ' ttl = ' .
				$banInfoTtl
			);*/
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
		
		$reason = preg_replace(
			'/[^\x20-\x7E\x{400}-\x{45F}\x{490}\x{491}\x{207}\x{239}]+/us',
			'',
			$reason
		);
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
		if ( $this->IsCitizen() == TRUE ) {
			$complainIncrement = 2;
		}
		else {
			$complainIncrement = 1;
		}
		
		$complainsList = $this->memcache->Get( COMPLAINS_LIST_MEMCACHE_KEY );
		
		// предполагаем, что это самая 1я жалоба вообще
		$complainInfo = FALSE;
		
		if ( isset( $complainsList[ $banKey ] ) ) {
			$complainInfo = $complainsList[ $banKey ];
		}
		
		// 1я жалоба на этого пользователя
		if ( $complainInfo === FALSE ) {
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
		
		// запоминаем время, когда последний раз жаловались на бан, чтобы почистить
		// массив
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
			$this->memcache->Set( COMPLAINS_LIST_MEMCACHE_KEY, $complainsList,
				COMPLAINS_TTL );
			
			// и перезаписать файл в memfs
			$dataJs = 'var complainsList = ' . json_encode( $complainsListPublic );
		
			fwrite( $complainsCacheFile, $dataJs );
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
	 * Проверка на отсутствие прав совершать операции с банами
	 * @return boolean
	 */
	private function CheckNoRightToModifyBan() {
		switch ( $this->user[ 'rid' ] ) {
			case 3:
			case 4:
			case 5:
				return FALSE;
			break;
			
			default:
				return TRUE;
			break;
		}
	}
	
	
	/**
	 * Определение длительности нового бана с учетом количества предыдущих и
	 * причины бана
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
		
		if ( $queryResult === FALSE ) {
			SaveForDebug( $queryString );
			exit;
		}
		
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
			// Угрозы жизни и здоровью
			case 3:
			// Серьезные оскорбления
			case 5:
			// Национализм, нацизм
			case 6:
			// Вредные ссылки => Порно, шок-контент, вирусы
			case 12:
			// Спойлер
			case 14:
				$banDuration = 1440;
			break;
			
			// Завуалированный мат
			case 2:
			// Легкие оскорбления
			case 4:
			// Реклама
			case 7:
			// Спам
			case 8:
			// Клевета
			case 9:
			// Негативный троллинг
			// replace if you need to add new reason
			// case 10:
			// Транслит, удаффщина, капсы
			case 11:
			// Вредные флэшмобы
			case 13:
				$banDuration = 10;
			break;
			
			default:
				$banDuration = 0;
		}
		
		return $banDuration;
	}
}
?>