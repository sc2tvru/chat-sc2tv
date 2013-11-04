var SC2TV_URL = 'http://sc2tv.ru';
var CHAT_URL =  'http://chat.sc2tv.ru/';
var CHAT_IMG_DIR = '/img/';
var CHAT_HISTORY_URL = CHAT_URL + 'memfs/history/';
var CHAT_MEMFS = CHAT_URL + 'memfs';
var CHAT_MODERATORS_DETAILS_URL = CHAT_MEMFS + '/moderatorsDetails.json';
var CHAT_HISTORY_NOT_FOUND = 'Сообщений по вашему запросу не найдено';
var CHAT_HISTORY_MAX_TIME_DIFFERENCE = 86400000;
var CHAT_HISTORY_CHECK_PARAMS = 'Пожалуйста, проверьте правильность данных запроса. Максимальный временной интервал для запроса истории - 24 часа.';
var CHAT_HISTORY_FOR_USERS_ONLY = 'История доступна только для авторизованных в чате пользователей.';
var smilesCount = smiles.length;
var userInfo = [];

var urlPattern = '((?:(?:ftp)|(?:https?))(?:://))' + // протокол (1)
	// URL без протокола (2)
	'(((?:(?:[a-z\u0430-\u0451\\d](?:[a-z\u0430-\u0451\\d-]*[a-z\u0430-\u0451\\d])*)\\.)+(?:[a-z]{2,}|\u0440\u0444)' + // хост (3)
	'|(?:(?:\\d{1,3}\\.){3}\\d{1,3}))' + // хост в формате IPv4 (3)
	'(:\\d+)?' + // порт (4)
	'(/[-a-z\u0430-\u0451\\d%_~\\+\\(\\):]*(?:[\\.,][-a-z\u0430-\u0451\\d%_~\\+\\(\\):]+)*)*' + // путь (5)
	'(\\?(?:&|[.:;a-z\u0430-\u0451\\d%_~\\+=-])*)?' + // параметры (6)
	'(#(?:&|[.:;a-z\u0430-\u0451\\d%_~\\+=-])*)?)'; // якорь (7)
	
var bbToUrlPattern = new RegExp('\\[url\\]' + urlPattern + '\\[\/url\\]()', 'gi');//пусто(8)
var bbToUrlPatternWithText = new RegExp('\\[url=' + urlPattern + '\\]([\u0020-\u007E\u0400-\u045F\u0490\u0491\u0207\u0239\u2012\u2013\u2014]+?)\\[\/url\\]', 'gi');//текст для ссылки(8)

function GetHistoryData( channelId, startDate, endDate, nick ) {
	nick = nick.replace( /[^\u0020-\u007E\u0400-\u045F\u0490\u0491\u0207\u0239]+/g, '' );
	nick = nick.replace( /[\s]+/g, ' ' );
	
	Login();
	
	$.ajaxSetup( {
		ifModified: true,
		cache: true,
	});
	
	$.post( CHAT_URL + 'gate.php', { task: 'GetHistory', channelId: channelId, startDate: startDate, endDate: endDate, nick: nick, token: userInfo.token }, function( data ) {
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

function BanUser( uid, user_name, duration, mid, channelId ){
	Login();
	
	$.post( CHAT_URL + 'gate.php', { task: 'BanUser', banUserId: uid, userName: user_name, duration: duration, messageId: mid, channelId: channelId, token: userInfo.token }, function( data ) {
		data = $.parseJSON( data );
		if( data.code == 0 ) {
			interactiveElement.append( data.error );
		}
		else if ( data.code == 1 ) {
			$( 'div.mess form#actionForm' ).remove();
			interactiveElement.append( user_name + ' забанен на ' + duration + ' мин' );
			banButton.remove();
		}
	});
}

function PrepareToBanUser( uid, user_name, mid, channelId ){
	duration = $( '#newBanTime').val();
	var r = confirm( 'Вы уверены, что хотите забанить ' + user_name + ' на ' + duration + ' минут?' );
	if ( r == true ) {
		BanUser( uid, user_name, duration, mid, channelId );
	}
}

function InstallHooksOnButtons() {
	$( '.banButton' ).click( function (){
		$( 'div.mess form#actionForm' ).remove();
		
		interactiveElement = $(this).parents( 'div.mess' );
		banButton = $(this);
		
		interactiveElement.append( '<form id="actionForm"><br/>Забанить на <input type="text" id="newBanTime" size="2"> минут <input type="submit" value="Отправить" id="submitBan"><input type="reset" value="Отмена" id="resetFormButton"> </form>' );
		
		$( '#resetFormButton' ).bind('click', function(){
			$( 'div.mess form#actionForm' ).remove();
		} );
		
		divClass = interactiveElement.attr( 'class' );
		
		var regExpr = /message_(\d+)/gi;
		res = regExpr.exec( divClass );
		mid = res[1];
		
		var regExpr = /channel-(\d+)/gi;
		res = regExpr.exec( divClass );
		channelId = res[1];
		
		var regExpr = /uid_(\d+)/gi;
		res = regExpr.exec( divClass );
		uid = res[1];
		
		user_name = interactiveElement.find( 'span.nick' ).html();
		
		$( '#submitBan' ).click(function() {
			PrepareToBanUser( uid, user_name, mid, channelId );
			return false;
		});
		
		$( '#newBanTime' ).focus();
	} );
}

function ShowHistory( historyData ) {
	$( '#history').html( historyData );
	InstallHooksOnButtons();
}

function ShowActionButton( mid ){
	$( 'div.message_' + mid + ' > span.banButton' ).show();
}

function HideActionButton( mid ){
	$( 'div.message_' + mid + ' > span.banButton' ).hide();
}

function BuildHtml( messageList ) {
	var data = '';
	var color = '';
	var colorClass = '';
	var colorStyle = '';
	
	var messageCount = messageList.length;
	
	if ( messageCount == 0 ) {
		return CHAT_HISTORY_NOT_FOUND;
	}
	
	for( i = messageCount -1; i >= 0 ; i-- ) {
		color = GetSpecColor( messageList[ i ].uid );
		// если не блат, то цвет по классу группы
		if ( color == '' ) {
			colorClass = ' user-' + messageList[ i ].rid;
		}
		else {
			colorStyle = ' style="color:' + color + ';"';
			colorClass = '';
		}
		
		actionButton = '';
		
		// модеры и выше могут отменять баны
		if ( IsModerator() ) {
			actionButton = ' <span title="Забанить" class="banButton">[ Бан ]</span>';
		}
		
		// TODO убрать лишнее
		data = '<div class="channel-' + messageList[ i ].channelId + ' mess message_' + messageList[ i ].id + ' uid_' + messageList[ i ].uid + '" onmouseover="ShowActionButton(' + messageList[ i ].id +');" onmouseout="HideActionButton('+ messageList[ i ].id +');"><span' + colorStyle + ' class="nick' + colorClass + '" title="' + messageList[ i ].date + '">' + messageList[ i ].name + '</span><p class="text">' + messageList[ i ].message + '</p>' + actionButton + '</div>' + data;
	}
	
	data = ProcessReplaces( data );
	return data;
}

//преобразуем бб код в хтмл
function bbCodeUrlToHtml(str, proto, url, host, port, path, query, fragment, text){
		url = url.replace(/:s:/gi, ':%73:');	//удаляем смайлы из ссылок
		if (!text) text = url;
	    if ( text.length > 60 ) {
			length = text.length;
			return '<a rel="nofollow" href="' + proto + url + '" target="_blank" title="' + proto + url + '">' + text.substring( 0, 30 ) + '...' + text.substring( length - 20) + '</a>';
		}
		return '<a rel="nofollow" href="' + proto + url + '" title="' + text + '" target="_blank">' + text + '</a>';

}

// всевозможные замены
function ProcessReplaces( str ) {
	// URL
	str = str.replace( bbToUrlPatternWithText, bbCodeUrlToHtml );
	str = str.replace( bbToUrlPattern, bbCodeUrlToHtml );

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

function GetSpecColor( uid ) {
	var color = '';
	switch( uid ) {
		// Laylah
		case '20546':
		// Kitsune
		case '11378':
			color = '#FFC0CB';										
		break;
		// Reeves
		case '21514':
			color = '#DAD871';
		break;
		// Kas
		case '62395':
			color = '#5DA130';
		break;
		default:
			color = '';
	}
	return color;
}

function PrepareNick( nick ) {
	nick = nick.replace( /[\/]+/g, '' );
	nick = encodeURIComponent( nick.replace( /[\s]+/g, ' ' ) );
	return nick;
}

function RequestHistory() {
	startDate = $( '#startDate' ).val();
	endDate = $( '#endDate' ).val();
	channelId = $( '#channelId' ).val();
	nick = $( '#nick' ).val();
	nick = PrepareNick( nick );
	
	$.ajaxSetup( {ifModified: true, cache:true} );
	
	historyCache = CHAT_HISTORY_URL;
	
	// если интервал не определен, показываем последние баны
	if ( startDate == '' || endDate == '' || startDate == undefined || endDate == undefined ) {
		historyCache += 'last';
	}
	// иначе пробуем запросить файл с кэшем для этого запроса, если его нет - уже делать запрос к бэкэнду
	else {
		endDateForCmp = Date.parse( endDate.replace( /[\s]/g, 'T' ) + ':00' );
		startDateForCmp = Date.parse( startDate.replace( /[\s]/g, 'T' ) + ':00' );
		
		if ( !IsModerator() ) {
			dateInterval = endDateForCmp - startDateForCmp;
			
			if ( dateInterval > CHAT_HISTORY_MAX_TIME_DIFFERENCE || dateInterval <= 0 ) {
				show_error( CHAT_HISTORY_CHECK_PARAMS );
				return;
			}
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
	$.getJSON( CHAT_URL + 'memfs/channels_history.json', function( data ) {
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

function IsModerator(){
	uid = $.cookie( 'drupal_uid' );
	var moderatorsCount = moderatorsDetails.length;
	
	if ( uid == undefined || moderatorsCount == 0 || moderatorsDetails[ uid ] == undefined ) {
		return false;
	}
	
	return moderatorsDetails[ uid ].name !== '';
}

function IsAnon(){
	return $.cookie( 'drupal_user' ) === null;
}

function Login() {
	if ( userInfo == '' || userInfo == undefined ) {
		$.ajaxSetup( { async: false, cache: false } );
			
		$.getJSON( CHAT_URL + 'gate.php?task=GetUserInfo', function( data ) {
			userInfo = data;
		});
		
		$.ajaxSetup({ async: true, cache: true });
		
		switch( userInfo.type ){
			case 'anon':
			case 'newbie':
			/*mod by rentgen
			case 'bannedInChat':
			*/
			case 'bannedOnSite':
				show_error( CHAT_HISTORY_FOR_USERS_ONLY );
			break;
		}
	}
}

$( document ).ready( function(){
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
		
		//RequestHistory();
		
		$( '#history-form' ).submit(function() {
			RequestHistory();
			return false;
		});
	}
});
