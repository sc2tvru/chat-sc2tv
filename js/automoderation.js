var SC2TV_URL = 'http://shr.dev.sc2tv.ru';
var CHAT_URL =  'http://chat.shr.dev.sc2tv.ru/';
var CHAT_IMG_DIR = '/img/';
var CHAT_MEMFS = CHAT_URL + 'memfs';
var CHAT_HISTORY_URL = CHAT_MEMFS + '/automoderation_history/';
var CHAT_MODERATORS_DETAILS_URL = CHAT_MEMFS + '/moderatorsDetails.json';
var CHAT_COMPLAINS_FOR_BANS_URL = CHAT_MEMFS + '/complainsForBans.json';
var CHAT_HISTORY_NOT_FOUND = 'Сообщений по вашему запросу не найдено';
var CHAT_HISTORY_MAX_TIME_DIFFERENCE = 86400000;
var CHAT_HISTORY_CHECK_PARAMS = 'Пожалуйста, проверьте правильность данных запроса. Максимальный временной интервал для запроса истории - 24 часа.';
var CHAT_HISTORY_FOR_USERS_ONLY = 'История доступна только для авторизованных в чате пользователей.';
var CHAT_MODERATORS_DETAILS_ERROR = 'Ошибка при получении данных по модераторам. Сообщите разработчикам.';
var CHAT_COMPLAINS_FOR_BANS_ERROR = 'Ошибка при получении данных по жалобам на баны. Сообщите разработчикам.';
var userInfo = [];
var moderatorsDetails = [];
var complainsList = [];
var smilesCount = smiles.length;

function GetModeratorsData() {
	Login();
	$.post( CHAT_URL + 'gate.php', { task: 'GetModeratorsDetails', token: userInfo.token }, function( data ) {
		data = $.parseJSON( data );
		if( data.error == '' ) {
			moderatorsDetails = data.moderatorsDetails;
		}
		else {
			show_error( data.error );
		}
	});
}

function GetModeratorsDetails() {
	if ( moderatorsDetails.length == 0 ) {
		$.ajaxSetup( {
			ifModified: true,
			statusCode: {
				404: function() {
					GetModeratorsData();
				}
			}
		});
		
		$.getJSON( CHAT_MODERATORS_DETAILS_URL, function( jsonData ){
			moderatorsDetails = jsonData.moderatorsDetails;
			if ( moderatorsDetails.length == 0 ) {
				show_error( CHAT_MODERATORS_DETAILS_ERROR );
			}
		});
	}
}

function GetComplainsData() {
	Login();
	$.post( CHAT_URL + 'gate.php', { task: 'GetComplainsList', token: userInfo.token }, function( data ) {
		data = $.parseJSON( data );
		if( data.error == '' ) {
			complainsList = data.complainsList;
		}
		else {
			show_error( data.error );
		}
	});
}

function GetComplainsList() {
	if ( complainsList.length == 0 ) {
		$.ajaxSetup( {
			ifModified: true,
			statusCode: {
				404: function() {
					GetComplainsData();
				}
			}
		});
		
		$.getJSON( CHAT_COMPLAINS_FOR_BANS_URL, function( jsonData ){
			complainsList = jsonData.complainsList;
			if ( complainsList.length == 0 ) {
				show_error( CHAT_COMPLAINS_FOR_BANS_ERROR );
			}
		});
	}
}

function GetReasonById( reasonId ) {
	reasonId = parseInt( reasonId );
	switch( reasonId ) {
		case 1:
			reason = 'Мат';
		break;
		
		case 5:
			reason = 'Серьезные оскорбления';
		break;
		
		case 6:
			reason = 'Национализм, нацизм';
		break;
		
		case 12:
			reason = 'Вредные ссылки';
		break;
			
		case 2:
			reason = 'Завуалированный мат';
		break;
		
		case 3:
			reason = 'Спам грубыми словами';
		break;
		
		case 4:
			reason = 'Легкие оскорбления';
		break;
		
		case 7:
			reason = 'Реклама';
		break;
		
		case 8:
			reason = 'Спам';
		break;
		
		case 9:
			reason = 'Клевета';
		break;
		
		case 10:
			reason = 'Негативный троллинг';
		break;
		
		case 11:
			reason = 'Транслит, удаффщина, капсы';
		break;
		
		case 13:
			reason = 'Вредные флэшмобы';
		break;
		
		case 99:
			reason = 'Бан модератором за неверно выданный гражданский бан.';
		break;
		
		default:
			reason = 'Ошибка. Сообщите, пожалуйста, разработчикам.';
	}
	
	return reason;
}

function GetHistoryData( channelId, startDate, endDate, nick ) {
	nick = nick.replace( /[^\u0020-\u007E\u0400-\u045F\u0490\u0491\u0207\u0239]+/g, '' );
	nick = nick.replace( /[\s]+/g, ' ' );
	
	Login();
	
	$.post( CHAT_URL + 'gate.php', { task: 'GetAutoModerationHistory', channelId: channelId, startDate: startDate, endDate: endDate, nick: nick, token: userInfo.token }, function( data ) {
		data = $.parseJSON( data );
		if( data.error == '' ) {
			historyData = BuildHtml( data.messages );
			ShowHistory( historyData );
		}
		else {
			show_error( data.error );
		}
	});
}

function ShowHistory( historyData ) {
	$( '#history').html( historyData );
	InstallHooksOnButtons();
}

function IsModerator(){
	uid = $.cookie( 'drupal_uid' );
	var moderatorsCount = moderatorsDetails.length;
	
	if ( uid == undefined || moderatorsCount == 0 || moderatorsDetails[ uid ] == undefined ) {
		return false;
	}
	
	return moderatorsDetails[ uid ].name === $.cookie( 'drupal_user' );
}

function GetModeratorNameById( uid ){
	if ( moderatorsDetails[ uid ] == undefined ) {
		return 'autoban?';
	}
	else {
		return moderatorsDetails[ uid ].name;
	}
}

function GetComplainsText( banKey ){
	if ( complainsList[ banKey ] == '' || complainsList[ banKey ] == undefined ) {
		return '';
	}
	
	complainsCount = complainsList[ banKey ][ 'complains' ].length;
	
	result = '<div class="complainMessage">';
	var j = 0;
	for( j=0; j < complainsCount; j++ ) {
		result += '<br/>' + complainsList[ banKey ][ 'complains' ][ j ][ 'userName' ] + ': ' + complainsList[ banKey ][ 'complains' ][ j ][ 'reason' ];
	}
	result += '</div>';
	
	return result;
}

function BuildHtml( messageList ) {
	messageList.reverse();
	var data = '';
	var color = '';
	var colorClass = '';
	var colorStyle = '';
	
	var messageCount = messageList.length;
	
	if ( messageCount == 0 ) {
		return CHAT_HISTORY_NOT_FOUND;
	}
	
	var previousBanKey = '';
	for( i=0; i < messageCount; i++ ) {
		var dateJsObj = new Date( messageList[ i ].banExpirationTime * 1000 );
		unBanDate = dateJsObj.toUTCString();
		
		moderatorName = messageList[ i ].moderatorName;
		
		var dateJsObj = new Date( messageList[ i ].banTime * 1000 );
		banDate = dateJsObj.toUTCString();
		var banDuration = messageList[ i ].banDuration / 60;
		var banReasonId = messageList[ i ].banReasonId;
		
		var banReason = '';
		
		if ( parseInt( messageList[ i ].banMessageId ) > 0 ){
			banReason = messageList[ i ].bannedForMessage;
		}
		
		if ( banReasonId > 0 ) {
			banReason += ' ' + GetReasonById( banReasonId );
		}
		
		// ключем, определяющим бан, является пара bannedUserId и время истечения бана
		// если ключ совпал с предыдущим, это бан от граждан и вывод надо сгруппировать в один блок
		var currentBanKey = messageList[ i ].bannedUserId + '_' + messageList[ i ].banExpirationTime;
		
		// новый бан
		if ( currentBanKey != previousBanKey ) {
			actionButton = '';
			
			// модеры и выше могут отменять баны
			if ( IsModerator() ) {
				actionButton = '<span title="Отменить" class="cancelBanButton" id="cancel-ban-' + currentBanKey +'">[ Отм ]</span> <span title="Изменить" class="editBanButton" id="edit-ban-' + currentBanKey + '">[ Изм ]</span>';
			}
			
			// можно жаловаться только на активные баны
			if ( messageList[ i ].chatBanStatus == 1 ) {
				actionButton += '<span title="Пожаловаться" class="complainBanButton" id="complain-ban-' + currentBanKey + '">[ Пож ]</span>';
			}
			
			// выполнится для всех банов, кроме первого, закрывая блоки с инфой
			if ( previousBanKey != '' ) {
				data += '</div>';
			}
			
			// если бан изменялся, надо вывести инфу по изменениям
			if ( messageList[ i ].banModificationUserId > 0 ) {
				banModificationInfo = '<div class="banModoficationHeader">Бан изменен ' + GetModeratorNameById( messageList[ i ].banModificationUserId ) + ':</div><div class="banModoficationReason">' + messageList[ i ].banModificationReason + '</div>';
			}
			else {
				banModificationInfo = '';
			}
			
			// если есть жалобы, выделяем бан
			banWithComplainsCssClass = '';
			if ( IsModerator() ) {
				banWithComplainsMessage = GetComplainsText( currentBanKey );
				if ( banWithComplainsMessage != '' ) {
					banWithComplainsCssClass = ' banWithComplain';
				}
			}
			else {
				banWithComplainsMessage = '';
			}
		
			data += '<div class="mess' + banWithComplainsCssClass + '"><span class="nick user-2" title="' + banDate + ' - ' + unBanDate + '">' +  messageList[ i ].userName + ' (' + banDuration + ', ' + moderatorName + ') </span>' + actionButton + banModificationInfo + banWithComplainsMessage + '<br/><p class="text">' + banReason + '</p>';
			
			previousBanKey = currentBanKey;
		}
		else {
			// бан гражданина из группы граждан надо дописать к открытому еще блоку
			data += '<br/>' + moderatorName + ': <p class="text">' + banReason + '</p>';
		}
	}
	
	data += '</div>';
	
	data = ProcessReplaces( data );
	return data;
}

// всевозможные замены
function ProcessReplaces( str ) {
	// смайлы
	for( i = 0; i < smilesCount; i++) {
		smileHtml = '<img src="' + CHAT_IMG_DIR + smiles[ i ].img +'" width="' + smiles[ i ].width + '" height="' + smiles[ i ].height+ '" class="chat-smile"/>';
		var smilePattern = new RegExp( RegExp.escape( ':s' + smiles[ i ].code ), 'gi' );
		str = str.replace( smilePattern, smileHtml );
	}
	return str;
}

RegExp.escape = function(text) {
    return text.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&");
}

function RequestHistory() {
	startDate = $( '#startDate' ).val();
	endDate = $( '#endDate' ).val();
	channelId = $( '#channelId' ).val();
	nick = $( '#nick' ).val();
	nick = encodeURIComponent( nick.replace( /[\s]+/g, '_' ) );
	
	$.ajaxSetup( {ifModified: true} );
	
	historyCache = CHAT_HISTORY_URL;
	
	// если интервал не определен, показываем последние баны
	if ( startDate == '' || endDate == '' || startDate == undefined || endDate == undefined ) {
		historyCache += 'last';
	}
	// иначе пробуем запросить файл с кэшем для этого запроса, если его нет - уже делать запрос к бэкэнду
	else {
		endDateForCmp = Date.parse( endDate.replace( /[\s]/g, 'T' ) + ':00' );
		startDateForCmp = Date.parse( startDate.replace( /[\s]/g, 'T' ) + ':00' );
		
		dateInterval = endDateForCmp - startDateForCmp;
		
		if ( dateInterval > CHAT_HISTORY_MAX_TIME_DIFFERENCE || dateInterval <= 0 ) {
			show_error( CHAT_HISTORY_CHECK_PARAMS );
			return;
		}
		
		historyCache += channelId + '_' + startDate + ':00_' + endDate + ':00_' + nick;
		historyCache = historyCache.replace( /[\s]+/g, '_' );
		
		$.ajaxSetup( {
			ifModified: true,
			statusCode: {
				404: function() {
					GetHistoryData( channelId, startDate, endDate, nick );
				}
			}
		});
	}
	
	historyCache += '.json';
	
	$.getJSON( historyCache, function( jsonData ){
		var messageList = jsonData.messages;
		if ( messageList.length > 0 ) {
			historyData = BuildHtml( messageList );
			ShowHistory( historyData );
		}
	});
}

function show_error( error ) {
	$( '#history' ).html( error );
}

function AddChannels(){
	$.getJSON( CHAT_URL + 'memfs/channels.json', function( data ) {
		channelList = data.channel;
		channelCount = channelList.length;
		channelsHtml = '';
		
		for( i=0; i < channelCount; i++) {
			channelsHtml +=	'<option value="' + channelList[ i ].channelId + '">' + channelList[ i ].channelTitle + '</option>';
		}
		
		if ( channelsHtml != '' ) {
			channelsHtml = '<option value="all" selected="selected">все</option>' + channelsHtml;
			$( '#channelId' ).html( channelsHtml );
		}
	});
}

function IsAnon(){
	return $.cookie( 'drupal_user' ) === null;
}

function Login() {
	console.log( 'Login' );
	console.log( userInfo );
	if ( userInfo == '' || userInfo == undefined ) {
		$.ajaxSetup( { async: false, cache: false } );
			
		$.getJSON( CHAT_URL + 'gate.php?task=GetUserInfo', function( data ) {
			userInfo = data;
		});
		
		$.ajaxSetup({ async: true, cache: true });
		
		switch( userInfo.type ){
			case 'anon':
			case 'newbie':
			case 'bannedInChat':
			case 'bannedOnSite':
				show_error( CHAT_HISTORY_FOR_USERS_ONLY );
			break;
		}
	}
}

$( document ).ready( function() {
	banKey = -1;
	statsData = '';
	
	if ( IsAnon() === true ) {
		$( '#history-form' ).remove();
		$( '#history' ).html( CHAT_HISTORY_FOR_USERS_ONLY );
	}
	else {
		$( "input.dateField" ).dynDateTime({
			showsTime: true,
			ifFormat: "%Y-%m-%d %H:%M",
			daFormat: "%Y-%m-%d %H:%M"
		});
		
		AddChannels();
		
		GetModeratorsDetails();
		
		// чтобы после запроса данных по модераторам GetModeratorsData не вызывалась после 404 ошибки
		// TODO надо это по феншую пофиксить, а не пустым колбэком
		$.ajaxSetup( {
			ifModified: true,
			statusCode: {
				404: function() {}
			}
		});
		
		GetComplainsList();
		
		// чтобы после запроса данных по модераторам GetComplainsData не вызывалась после 404 ошибки
		// TODO надо это по феншую пофиксить, а не пустым колбэком
		$.ajaxSetup( {
			ifModified: true,
			statusCode: {
				404: function() {}
			}
		});
		
		RequestHistory();
		
		$( '#history-form' ).submit(function() {
			RequestHistory();
			return false;
		});
	}
});

function CancelBan(){
	unBanReason =  $( '#reason' ).attr( 'value' );
	banModerator = $( '#banModerator' ).attr('checked');
	
	if ( banModerator == 'checked' ) {
		banModerator = 1;
	}
	else {
		banModerator = 0;
	}
	
	moderatorBanTime = $( '#moderatorBanTime').attr( 'value' );
	banKey = banKey.replace( 'cancel-ban-', '' );
	
	Login();
	
	$.post( CHAT_URL + 'gate.php', {task:"CancelBan",banKey:banKey,reason:unBanReason,banModerator:banModerator,moderatorBanTime:moderatorBanTime, token: userInfo.token}, function(data) {
		data = $.parseJSON(data);
		interactiveElement.append( ' <span class="cancelBanResult">' + data.result + '</span>' );
		
		if ( data.code == "1" ) {
			$( 'div.mess form#actionForm' ).remove();
			cancelBanButton.remove();
		}
		else {
			alert( data.result );
		}
	});
	
	// чтобы отправка формы не перезагрузила страницу
	return false;
}

function EditBan(){
	editBanReason =  $( '#reason' ).attr( 'value' );
	newBanTime = $( '#newBanTime').attr( 'value' );
	banKey = banKey.replace( 'edit-ban-', '' );
	Login();
	
	$.post( CHAT_URL + 'gate.php', {task:"EditBan",banKey:banKey,reason:editBanReason,newBanTime:newBanTime, token: userInfo.token}, function(data) {
		data = $.parseJSON(data);
		interactiveElement.append( ' <span class="editBanResult">' + data.result + '</span>' );
		
		if ( data.code == "1" ) {
			$( 'div.mess form#actionForm' ).remove();
			editBanButton.remove();
		}
	});
	
	// чтобы отправка формы не перезагрузила страницу
	return false;
}

function ComplainBan(){
	complainBanReason =  $( '#reason' ).attr( 'value' );
	banKey = banKey.replace( 'complain-ban-', '' );
	Login();
	
	$.post( CHAT_URL + 'gate.php', { task:'ComplainBan', banKey:banKey, reason:complainBanReason, token: userInfo.token }, function( data ) {
		data = $.parseJSON( data );
		interactiveElement.append( ' <span class="complainBanResult">' + data.result + '</span>' );
		
		if ( data.code == "1" ) {
			$( 'div.mess form#actionForm' ).remove();
			complainBanButton.remove();
		}
	});
	
	// чтобы отправка формы не перезагрузила страницу
	return false;
}
	
function InstallHooksOnButtons() {
	$( '.cancelBanButton' ).click( function (){
		$( 'div.mess form#actionForm' ).remove();
		
		interactiveElement = $(this).parents( 'div.mess' );
		cancelBanButton = $(this);
		
		interactiveElement.append( '<form id="actionForm"><br/><textarea id="reason" rows="3" cols="60" autofocus></textarea><br/><input type="submit" value="Отправить"><input type="reset" value="Отмена" id="resetFormButton"><input type="checkbox" id="banModerator"> Забанить граждан / модератора на <input type="text" id="moderatorBanTime" size="2"> минут?</form>' );
		
		$( '#resetFormButton' ).bind('click', function(){
			$( 'div.mess form#actionForm' ).remove();
		} );
		
		banKey = $(this).attr( 'id' );
		$( '#actionForm' ).submit( CancelBan );
		
		$( '#reason' ).focus();
	} );

	$( '.editBanButton' ).click( function (){
		$( 'div.mess form#actionForm' ).remove();
		
		interactiveElement = $(this).parents( 'div.mess' );
		editBanButton = $(this);
		
		interactiveElement.append( '<form id="actionForm"><br/><textarea id="reason" rows="3" cols="60" autofocus></textarea><br/><input type="submit" value="Отправить"><input type="reset" value="Отмена" id="resetFormButton"> Изменить длительность бана на <input type="text" id="newBanTime" size="2"> минут</form>' );
		
		$( '#resetFormButton' ).bind('click', function(){
			$( 'div.mess form#actionForm' ).remove();
		} );
		
		banKey = $(this).attr( 'id' );
		$( '#actionForm' ).submit( EditBan );
		
		$( '#reason' ).focus();
	} );
	
	$( '.complainBanButton' ).click( function (){
		$( 'div.mess form#actionForm' ).remove();
		
		interactiveElement = $(this).parents( 'div.mess' );
		complainBanButton = $(this);
		
		interactiveElement.append( '<form id="actionForm"><br/><textarea id="reason" rows="3" cols="60" autofocus></textarea><br/><input type="submit" value="Отправить"><input type="reset" value="Отмена" id="resetFormButton"></form>' );
		
		$( '#resetFormButton' ).bind('click', function(){
			$( 'div.mess form#actionForm' ).remove();
		} );
		
		banKey = $(this).attr( 'id' );
		$( '#actionForm' ).submit( ComplainBan );
		
		$( '#reason' ).focus();
	} );

	$( '#statsButton' ).click( function (){
		if ( statsData == '') {
			$.post( 'http://sc2tv.ru/chat/automoderation_ban_history.php', {task:"GetStats"}, function(data) {
				data = $.parseJSON(data);
				if ( data.code == "1" ) {
					statsData = data.statsData;
					$( '#statsInfo' ).html( statsData );
				}
			});
		}
		
		if ( $( '#statsInfo' ).css( 'display') == 'none' ) {
			$( '#statsInfo' ).css( 'display', 'block' );
		}
		else {
			$( '#statsInfo' ).css( 'display', 'none' );
		}
		
	} );
}