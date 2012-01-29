var SC2TV_URL = 'http://sc2tv.ru';
var CHAT_URL =  'http://chat.sc2tv.ru/';
var CHAT_IMG_DIR = '/img/';
var CHAT_HISTORY_URL = CHAT_URL + 'memfs/history/';
var CHAT_HISTORY_NOT_FOUND = 'Сообщений по вашему запросу не найдено';
var CHAT_HISTORY_MAX_TIME_DIFFERENCE = 86400000;
var CHAT_HISTORY_CHECK_PARAMS = 'Пожалуйста, проверьте правильность данных запроса. Максимальный временной интервал для запроса истории - 24 часа.';
var CHAT_HISTORY_FOR_USERS_ONLY = 'История доступна только для авторизованных в чате пользователей.';
var smilesCount = smiles.length;

function GetHistoryData( channelId, startDate, endDate, nick ) {
	nick = nick.replace( /[^\u0020-\u007E\u0400-\u045F\u0490\u0491\u0207\u0239]+/g, '' );
	nick = nick.replace( /[\s]+/g, ' ' );
	
	// логин
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
			return CHAT_HISTORY_FOR_USERS_ONLY;
		break;
	}
	
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

function ShowHistory( historyData ) {
	$( '#history').html( historyData );
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
	
	for( i=0; i < messageCount; i++ ) {
		color = GetSpecColor( messageList[ i ].uid );
		// если не блат, то цвет по классу группы
		if ( color == '' ) {
			colorClass = ' user-' + messageList[ i ].rid;
		}
		else {
			colorStyle = ' style="color:' + color + ';"';
			colorClass = '';
		}
		
		if ( messageList[ i ].uid == -1 ) {
			systemClass = 'system_';
		}
		else {
			systemClass = '';
		}
		
		// TODO убрать лишнее
		data = '<div class="channel-' + messageList[ i ].channelId + ' mess message_' + messageList[ i ].id + '"><span' + colorStyle + ' class="nick' + colorClass + '" title="' + messageList[ i ].date + '">' + messageList[ i ].name + '</span><p class="' + systemClass + 'text">' + messageList[ i ].message + '</p></div>' + data;
	}
	
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