<?php
/**
 * код для автомодерации пользователями
 * редакция от 2011-07-30
 * @author shr, forshr@gmail.com
 *
 */

if ( $_POST[ 'task' ] == 'GetStats' ) {
	include_once 'db.php';
	// подключаем для получения констант и функций для работы с memcache
	// код класса автомодерации не работает, пока не создадим экземпляр и не вызовем функции *кэп*
	include_once 'automoderation.php';
	
	exit( GetModeratorStats( 10, 600 ) );
}

/**
 * Получение статистики по модераторам
 * @param int $moderatosCount количество выводимых позиций
 * @param int $updateInterval период обновления статистики
 * @return array возвращает массив в формате JSON с ключами
 * statusCode = 1 (ошибок нет) и statsData с HTML
 */
function GetModeratorStats( $moderatosCount, $updateInterval ) {
	$memcacheObj = new Memcache;
	
	$statsMemcacheKey = 'AutoModeration_stats';
	$complainInfo = AutoModerationMemcacheGet( $memcacheObj, $statsMemcacheKey );
	
	// статистики нет, надо получить и записать в memcache
	if ( $complainInfo === false ) {
		$moderatosCount = (int)$moderatosCount;
		
		// модераторы
		$query = '
			SELECT moderatorId modid, (Select count(*) from chat_ban where moderatorId = modid) as bans_count, name
			FROM users_roles, chat_ban Inner join users on users.uid = chat_ban.moderatorId
			WHERE moderatorId > 0 AND moderatorId = users_roles.uid
			AND rid in (3,4,5)
			GROUP BY moderatorId ORDER BY bans_count DESC LIMIT '. $moderatosCount;
		
		$result = mysql_query( $query );
		$resultData = '<div id="moderatorStatsBlock"><span id="moderatorStatsHeader">Топ - '. $moderatosCount .' модераторов по количеству банов</span><ol id="moderatorStats">';
		
		while ( $moderatorData = mysql_fetch_assoc( $result ) ) {
			$resultData .= '<li><a href="http://sc2tv.ru/user/'. $moderatorData[ 'modid' ] . '">'. $moderatorData[ 'name' ].'</a><span class="moderatorBansCount">'. $moderatorData[ 'bans_count' ] .'</span></li>';
		}
		
		$resultData .= '</ol></div>';
		
		// граждане
		// вообще код этот можно было бы заменить на метод, принимающий параметром роли
		// но я решил так не делать на случай, если критерии выборки в будущем изменятся
		$query = '
			SELECT moderatorId modid, (Select count(*) from chat_ban where moderatorId = modid) as bans_count, name
			FROM users_roles, chat_ban Inner join users on users.uid = chat_ban.moderatorId
			WHERE moderatorId > 0 AND moderatorId = users_roles.uid
			AND rid in (2,6,7,9,10)
			GROUP BY moderatorId ORDER BY bans_count DESC LIMIT '. $moderatosCount;
		
		$result = mysql_query( $query );
		$resultData .= '<div id="citizensStatsBlock"><span id="moderatorStatsHeader">Топ - '. $moderatosCount .' граждан по количеству банов</span><ol id="citizenStats">';
		
		while ( $moderatorData = mysql_fetch_assoc( $result ) ) {
			$resultData .= '<li><a href="http://sc2tv.ru/user/'. $moderatorData[ 'modid' ] . '">'. $moderatorData[ 'name' ].'</a><span class="moderatorBansCount">'. $moderatorData[ 'bans_count' ] .'</span></li>';
		}
		
		$resultData .= '</ol></div>';
		
		$result = json_encode(
			array(
				'statusCode' => 1,
				'statsData' => $resultData
			)
		);
		
		// сохраняем вывод в memcache, чтобы не дергать базу
		AutoModerationMemcacheSet( $memcacheObj, $statsMemcacheKey, $result,
			$updateInterval );
	}
	else {
		$result = $complainInfo;
	}
	
	return $result;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ru">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>История банов чата SC2TV.RU с системой автомодерации Project Misaka by shr</title>
	<link type="text/css" rel="stylesheet" media="all" href="history.css"/>
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
	<script type="text/javascript" src="js/jquery.dynDateTime.min.js"></script>
	<script type="text/javascript" src="js/calendar-ru.js"></script>
	<script type="text/javascript" src="js/automoderation_interactive.js"></script>
	<link rel="stylesheet" type="text/css" media="all" href="css/history/calendar-win2k-cold-1.css"/> 
</head>
<?php
require_once 'db.php';
// подключаем для получения констант и функций для работы с memcache
// код класса автомодерации не работает, пока не создадим экземпляр и не вызовем функции *кэп*
require_once 'automoderation.php';
	
// текущее смещение времени сервера (Казахстан) от времени по Москве
define( 'SERVER_OFFSET', 7200 );
// месяц назад с учетом смещения
define( 'VISIBLE_OFFSET', 604800 + SERVER_OFFSET );

if ( !$_GET[ 'startDate' ] ) {
	$startDate = date( 'Y-m-d H:i:s', time() - VISIBLE_OFFSET );
}
else {
	$startDate = $_GET['startDate'];
}

if ( !$_GET[ 'endDate' ] ) {
	$endDate = date( 'Y-m-d H:i:s', time() - SERVER_OFFSET );
}
else {
	$endDate = $_GET['endDate'];
}
?>
<body>
	<div id="history-form">
		<a href="http://sc2tv.ru">SC2TV.RU</a> | <span id="statsButton">Статистика</span> | <a href="http://forum.sc2tv.ru/showthread.php/17429">Пожелания и предложения</a> | v.2011-07-30 @ shr<br/><br/>
		<form action="?" method="get">
			Дата начала *: <input type="text" name="startDate" class="dateField" value="<?php echo $startDate;?>"/>
			Дата конца *: <input type="text" name="endDate" class="dateField" value="<?php echo $endDate;?>"/>
			<div class="history-hint">
				по Москве (GMT+3)
			</div><br/>
			Модератор(ы): <input type="text" name="nick" size="30"/>
			<input type="submit" value="Найти"/>
			<input type="reset" value="Сброс"/>
			<div class="history-hint">
				Опционально. Разделитель ";". Пример: Weedle;Laylah;Ispec
			</div>
		</form>
	</div>
	<div id="statsInfo">Проблемы с получением статистики. Сообщите разработчикам.</div>
<?php
require_once 'chat.php';

$chat = new chat();
$user = $chat->auth();

if ( $user ) {
	//print_r( $user );
	$userType = 'user';
	$userRights = $user[ 'rights' ];
	$userId = $user[ 'uid' ];
	
	// из бана надо исключить админов, модеров и рута, чтобы могли саморазбаниться
	if( isset( $user[ 'ban' ] ) && $userRights != 1 ) {
		$userType = 'banned';
		$userRights = -1;
	}
	
	if( time() - $user[ 'created' ] < 259200 ) {
		// зареген менее трех дней назад
		$userType = 'newbie';
		$userRights = -1;
	}
}
else {
	$userType = 'anon';
	$userRights = -1;
	$userId = 0;
}

$startDate = FormatDate( mysql_real_escape_string( $startDate ) );
$endDate = FormatDate( mysql_real_escape_string( $endDate ) );

if ( $_GET[ 'nick' ] !== '' ) {
	$nicknames = mysql_real_escape_string( $_GET[ 'nick' ] );
}

$options = '';

if ( $nicknames !== '' ) {
	$nick = explode( ';', $nicknames );
	if ( count( $nick ) > 0 ) {
		$options = '(';
		$operator = '';
		foreach ( $nick as $currentNick ) {
			$options .= $operator . "banned_by='$currentNick'";
			if ( $operator === '' ) {
				$operator = ' OR ';
			}
		}
		$options .= ') AND';
	}
}

// TODO упростить ^_^
$query = "SELECT id, users.uid as bannedUserId, name as userName, (SELECT name FROM users WHERE uid=moderatorId) as moderatorName, timeban, banned_by, ban_reason, ban_date, banned_for, chat_ban.status as sc2tvChatBanStatus, banModificationReason, (SELECT name FROM users WHERE uid=banModificationUserId) as modificationModeratorName, (SELECT message FROM chat_message WHERE id=chat_ban.banned_for) as bannedForMessage, ban_duration
FROM chat_ban, users
WHERE
$options
users.uid = chat_ban.uid
AND ban_date BETWEEN '". $startDate ."' AND '". $endDate ."'
ORDER BY ban_date DESC";
//ORDER BY timeban - ban_duration DESC";
//mail( 'forshr@gmail.com', 'dev'.time(), $query );

if ( $res = mysql_query( $query ) ) {
	$previousBanKey = '';
	
	while ( $banData = mysql_fetch_array( $res ) ) {
		$bannedUserId = $banData[ 'bannedUserId' ];
		$userName = $banData[ 'userName' ];
		$unBanDate = date( 'd F H:i:s', $banData[ 'timeban' ] - SERVER_OFFSET );
		$moderatorName = $banData[ 'moderatorName' ];
		
		$banDate = date( 'd F H:i:s', $banData[ 'ban_date' ] - SERVER_OFFSET );
		$banDuration = round( intval( $banData[ 'timeban' ] - $banData[ 'ban_date' ] ) / 60 );
		
		$banStatus = $banData[ 'sc2tvChatBanStatus' ];
		$banModificationReason = $banData[ 'banModificationReason' ];
		$modificationModeratorName = $banData[ 'modificationModeratorName' ];
		
		$bannedForMessage = $banData[ 'bannedForMessage' ];
		$banReason = $banData[ 'ban_reason' ];
		
		// TODO: на данный момент причина для модеров - это тупо копия сообщения,
		// им тоже надо будет прикрутить меню с нарушениями и писать в базу только ID нарушений
		// поэтому это условие выполняется только для граждан,
		// в банах которых в поле ban_reason сейчас ID нарушений
		// надо будет соответственно тип поля поменять c varchar на int
		if ( intval( $banData[ 'banned_for' ] ) > 0 && intval( $banReason ) > 0 ) {
			$banReason = $bannedForMessage . ' ' . GetReasonById( $banReason );
		}
		
		// ключем, определяющим бан, является пара bannedUserId и время истечения бана
		// если ключ совпал с предыдущим, это бан от граждан и вывод надо сгруппировать в один блок
		$currentBanKey = $bannedUserId .'_'. $banData[ 'timeban' ];
		$out = '';
		
		// новый бан
		if ( $currentBanKey != $previousBanKey ) {
			$actionButton = '';
			
			// root, admin, moder могут отменять баны
			if ( $userRights == 1 ) {
				$actionButton = '<span title="Отменить" class="cancelBanButton" id="cancel-ban-'. $currentBanKey .'">[ Отм ]</span> <span title="Изменить" class="editBanButton" id="edit-ban-'. $currentBanKey .'">[ Изм ]</span>';
			}
			// граждане и обычные пользователи могут жаловаться только на активные баны
			// тут не elseif, чтобы проверябщие тоже могли убедиться, что жалобы работают ;)
			if ( $userRights == 0 || $banStatus == 1 ) {
				$actionButton .= '<span title="Пожаловаться" class="complainBanButton" id="complain-ban-'. $currentBanKey .'">[ Пож ]</span>';
			}
			
			// забаненные жаловаться не могут
			if ( $userRights == -1 ) {
				$actionButton = '';
			}
			
			// выполнился для всех банов, кроме первого, закрывая блоки с инфой
			if ( $previousBanKey != '' ) {
				$out = '</div>';
			}
			
			// если бан изменялся, надо вывести инфу по изменениям
			if ( $modificationModeratorName != ''  ) {
				$banModificationInfo = str_replace( '{modificationModeratorName}', $modificationModeratorName, $banModificationReason );
			}
			else {
				$banModificationInfo = '';
			}
			
			// если есть жалобы, выделяем бан
			$banWithComplainsMessage = GetComplainsText( $currentBanKey );
			if ( $banWithComplainsMessage == '' ) {
				$banWithComplainsCssClass = '';
			}
			else {
				$banWithComplainsCssClass = ' banWithComplain';
			}
			
			$out .= '<div class="mess'. $banWithComplainsCssClass .'"><span class="nick user0" title="'. $banDate .' - '. $unBanDate .'">'. $userName .' ('. $banDuration .', '.$moderatorName .') </span>'. $actionButton . $banModificationInfo . $banWithComplainsMessage.'<br/><p class="text">'. $banReason .'</p>';
			
			$previousBanKey = $currentBanKey;
		}
		else {
			// бан гражданина из группы граждан надо дописать к открытому еще блоку
			$out = '<br/>'. $moderatorName .': <p class="text">'. $banReason . '</p>';
		}
		
		echo $out;
	}
	echo '</div>';
}


/**
 * Получение текста жалоб пользователей на баны
 * @param int $moderatosCount количество выводимых позиций
 * @param int $updateInterval период обновления статистики
 * @return array возвращает массив в формате JSON с ключами
 * statusCode = 1 (ошибок нет) и statsData с HTML
 */
function GetComplainsText( $banKey ) {
	$memcacheObj = new Memcache;
	
	$complainInfoMemcacheKey = 'AutoModeration_complain_' . $banKey;
	$complainInfo = AutoModerationMemcacheGet( $memcacheObj, $complainInfoMemcacheKey );
	
	if ( $complainInfo === false || $complainInfo[ 'complainsCount' ] < COMPLAINS_NEEDED ) {
		$result = '';
	}
	else {
		$result = '<div class="complainMessage">';
		foreach( $complainInfo[ 'complains'] as $complain ) {
			$result .= '<br/>'.$complain[ 'userName' ] . ': ' . $complain[ 'reason' ];
		}
		$result .= '</div>';
	}
	
	return $result;
}


/**
 * Определение текста нарушения
 * @param int $reasonId - id нарушения
 * @return int
 */
function GetReasonById( $reasonId ) {
	switch( $reasonId ) {
		case 1:
			$reason = 'Мат';
		break;
		
		case 5:
			$reason = 'Серьезные оскорбления';
		break;
		
		case 6:
			$reason = 'Национализм, нацизм';
		break;
		
		case 12:
			$reason = 'Вредные ссылки';
		break;
			
		case 2:
			$reason = 'Завуалированный мат';
		break;
		
		case 3:
			$reason = 'Спам грубыми словами';
		break;
		
		case 4:
			$reason = 'Легкие оскорбления';
		break;
		
		case 7:
			$reason = 'Реклама';
		break;
		
		case 8:
			$reason = 'Спам';
		break;
		
		case 9:
			$reason = 'Клевета';
		break;
		
		case 10:
			$reason = 'Негативный троллинг';
		break;
		
		case 11:
			$reason = 'Транслит, удаффщина, капсы';
		break;
		
		case 13:
			$reason = 'Вредные флэшмобы';
		break;
		
		default:
			$reason = 'Ошибка. Сообщите, пожалуйста, разработчикам.';
	}
	
	return $reason;
}

// приведение даты к нужному формату
function FormatDate( $date ) {
	$date = strtotime( $date ) + SERVER_OFFSET;
	return $date;
}
?>
</body>
</html>