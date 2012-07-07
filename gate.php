<?php
// TODO перенести на nginx?
// без задачи работать не стоит
if ( isset(  $_REQUEST[ 'task' ] ) ) {
	$task = $_REQUEST[ 'task' ];
}

if ( $task == '' ) {
	exit;
}

require_once 'core.php';
require_once 'utils.php';
require_once 'chat.php';

global $memcache;
$chat = new Chat( $memcache );

$authInfo = $chat->GetAuthInfo();
$error = $authInfo[ 'error' ];
$userInfo = $authInfo[ 'userInfo' ];

// если есть ошибка авторизации, лучше сразу отдать ее и прекратить выполнение
if ( $error != '' && $task != 'GetHistory' ) {
	SendDefaultResponse( $userInfo, $error );
}

if ( $task == 'GetUserInfo' ) {
	SendDefaultResponse( $userInfo, $error );
}

// для всех действий, кроме авторизации, проверяем установленный токен
if ( !isset( $_REQUEST[ 'token' ] ) || $userInfo[ 'token' ] != $_REQUEST[ 'token' ] ) {
	SendDefaultResponse( $userInfo, CHAT_TOKEN_VERIFICATION_FAILED );
}

// выполняем действия для задачи
switch ( $task ) {
	case 'WriteMessage':
		if ( isset( $_POST[ 'message' ] ) && $_POST[ 'message' ] != '' ) {
			$message = $_POST[ 'message' ];
		}
		else {
			SendDefaultResponse( $userInfo, CHAT_USER_MESSAGE_EMPTY );
		}
		
		$chat->SetDatabase();
		$res = $chat->WriteMessage( $message );
		
		if ( $res === true ) {
			// для автомодерации, чтобы держать в памяти актуальное значение кол-ва сообщений
			// в чате и лишний раз не обращаться за ним в базу
			$chatMessagesCountMemcacheKey = 'AM_uid_'. $userInfo[ 'uid' ] .
				'_chatMsgCount';
			
			$currentChatMessagesCount = $memcache->Get( $chatMessagesCountMemcacheKey );
			
			/* увеличивается только в случае, если была попытка проголосовать за бан
			 * иначе если добавить сюда извлечение кол-ва и его установку,
			 * будет много "холостых" запросов, хотя по факту нужны только для автомодерации
			*/ 
			if ( $currentChatMessagesCount > 0 ) {
				$memcache->Inc( $chatMessagesCountMemcacheKey, 1 );
			}
		}
		else {
			$error = CHAT_USER_MESSAGE_ERROR;
		}
		
		SendDefaultResponse( $userInfo, $error );
	break;
	
	case 'DeleteMessage':
		$chat->SetDatabase();
		$result = $chat->DeleteMessage( $_GET[ 'messageId' ], $_GET[ 'channelId' ] );
		$result = array_merge( $userInfo, $result );
		echo json_encode( $result );
	break;

	case 'BanUser':
		$chat->SetDatabase();
		
		if ( isset( $_POST[ 'channelId' ] ) ) {
			$channelId = $_POST[ 'channelId' ];
		}
		else {
			$channelId = 0;
		}
		
		$result = $chat->BanUser(
			$_POST[ 'banUserId' ],
			$_POST[ 'userName' ],
			$_POST[ 'duration' ],
			$_POST[ 'messageId' ],
			$channelId
		);
		$result = array_merge( $userInfo, $result );
		echo json_encode( $result );
	break;
	
	case 'GetHistory':
		if($error != '' && $error != CHAT_USER_BANNED_IN_CHAT) SendDefaultResponse( $userInfo, $error );
		include 'history.php';
		
		$history = new ChatHistory();
		
		list( $channelId, $startDate, $endDate, $nick ) = GetHistoryParamsFromPost();
		
		$result = $history->Get(
			$channelId,
			$startDate,
			$endDate,
			$nick,
			IsModeratorRequest( $userInfo )
		);
		
		echo json_encode( $result );
	break;
	
	case 'GetModeratorsDetails':
		include 'automoderation_history.php';
		
		$history = new ChatAutomoderationHistory( $memcache );
		$result = $history->GetModeratorsDetails();
		
		echo json_encode( $result );
	break;
	
	case 'GetComplainsList':
		include 'automoderation_history.php';
		
		$history = new ChatAutomoderationHistory( $memcache );
		$result = $history->GetComplainsList();
		
		echo json_encode( $result );
	break;
	
	case 'GetAutoModerationHistory':
		include 'automoderation_history.php';
		
		$history = new ChatAutomoderationHistory( $memcache );
		
		list( $channelId, $startDate, $endDate, $nick ) = GetHistoryParamsFromPost();
		
		if ( isset( $_POST[ 'bannedNick' ] ) ) {
			$bannedNick = $_POST[ 'bannedNick' ];
		}
		else {
			$bannedNick = '';
		}
		
		$result = $history->Get(
			$channelId,
			$startDate,
			$endDate,
			$nick,
			$bannedNick,
			IsModeratorRequest( $userInfo )
		);
		
		echo json_encode( $result );
	break;
	
	case 'CitizenVoteForUserBan':
		include 'automoderation.php';
		$citizenModerator = new AutoModeration( $memcache, $userInfo );
		
		$result = $citizenModerator->VoteForUserBan(
			$_POST[ 'banUserId' ],
			$_POST[ 'userName' ],
			$_POST[ 'messageId'],
			$_POST[ 'reasonId' ]
		);
		$result = array_merge( $userInfo, $result );
		echo json_encode( $result );
	break;
		
	case 'CancelBan':
		include 'automoderation.php';
		$citizenModerator = new AutoModeration( $memcache, $userInfo );
		$citizenModerator->SetDatabase();
		
		$result = $citizenModerator->CancelBan(
			$_POST[ 'banKey' ],
			$_POST[ 'reason' ],
			$_POST[ 'banModerator' ],
			$_POST[ 'moderatorBanTime' ]
		);
		
		echo json_encode( $result );
	break;
		
	case 'EditBan':
		include 'automoderation.php';
		$citizenModerator = new AutoModeration( $memcache, $userInfo );
		$citizenModerator->SetDatabase();
		
		$result = $citizenModerator->EditBan(
			$_POST[ 'banKey' ],
			$_POST[ 'reason' ],
			$_POST[ 'newBanTime' ]
		);
		
		echo json_encode( $result );
	break;
	
	case 'ComplainBan':
		if ( $userInfo[ 'type' ] == 'user' || $userInfo[ 'type' ] == 'chatAdmin' ) {
			include 'automoderation.php';
			$citizenModerator = new AutoModeration( $memcache, $userInfo );
			$citizenModerator->SetDatabase();
			
			$result = $citizenModerator->ComplainBan(
				$_POST[ 'banKey' ],
				$_POST[ 'reason' ]
			);
			
			echo json_encode( $result );
		}
		else {
			echo '{"code":"0","result": "Вы не можете жаловаться на баны."}';
		}
	break;
	
	default:
		exit;
}

function SendDefaultResponse( $userInfo, $error ) {
	$userInfo[ 'error' ] = $error;
	echo json_encode( $userInfo );
	exit;
}

/**
 *	проверка, модератор ли делает этот запрос
 *  @param array $userInfo
 *	return bool true | false
 */
function IsModeratorRequest( $userInfo ) {
	if ( $userInfo[ 'rid' ] == 3 || $userInfo[ 'rid' ] == 4 || $userInfo[ 'rid' ] == 5 ) {
		$isModeratorRequest = true;
	}
	else {
		$isModeratorRequest = false;
	}
	
	return $isModeratorRequest;
}

/**
 *	Получение переменных для выборки истории из POST
 *	return array список $channelId, $startDate, $endDate, $nick
 */
function GetHistoryParamsFromPost() {
	if ( isset( $_POST[ 'channelId' ] ) ) {
		$channelId = $_POST[ 'channelId' ];
	}
	else {
		$channelId = '';
	}
	
	if ( isset( $_POST[ 'startDate' ] ) ) {
		$startDate = $_POST[ 'startDate' ];
	}
	else {
		$startDate = 0;
	}
	
	if ( isset( $_POST[ 'endDate' ] ) ) {
		$endDate = $_POST[ 'endDate' ];
	}
	else {
		$endDate = 0;
	}
	
	if ( isset( $_POST[ 'nick' ] ) ) {
		$nick = $_POST[ 'nick' ];
	}
	else {
		$nick = '';
	}
	
	return array( $channelId, $startDate, $endDate, $nick );
}
?>