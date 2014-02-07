var SC2TV_URL = 'http://sc2tv.ru';
var CHAT_URL =  'http://chat.sc2tv.ru/';
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
var uid = 0;
var topModeratorsCount = 10;
var moderatorsDetailsHtml = '';
var SC2TV_TIME_DIFF = 14400;
var processReplacesMessageInfo = [];

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
			reason = 'Порно, шок-контент, вирусы';
		break;
			
		case 2:
			reason = 'Завуалированный мат';
		break;
		
		case 3:
			reason = 'Угрозы жизни и здоровью';
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
			//TODO: replace if you need to add new reason
			reason = 'Негативный троллинг';
		break;
		
		case 11:
			reason = 'Транслит, удаффщина, капсы';
		break;
		
		case 13:
			reason = 'Вредные флэшмобы';
		break;

		case 14:
			reason = 'Спойлер';
		break;
		
		case 99:
			reason = 'Бан модератором за неверно выданный гражданский бан.';
		break;
		
		default:
			reason = 'Ошибка. Сообщите, пожалуйста, разработчикам.';
	}
	
	return reason;
}

function GetHistoryData( channelId, startDate, endDate, nick, bannedNick ) {
	nick = nick.replace( /[^\u0020-\u007E\u0400-\u045F\u0490\u0491\u0207\u0239]+/g, '' );
	nick = nick.replace( /[\s]+/g, ' ' );
	
	bannedNick = bannedNick.replace( /[^\u0020-\u007E\u0400-\u045F\u0490\u0491\u0207\u0239]+/g, '' );
	bannedNick = bannedNick.replace( /[\s]+/g, ' ' );
	
	Login();
	
	$.post( CHAT_URL + 'gate.php', { task: 'GetAutoModerationHistory', channelId: channelId, startDate: startDate, endDate: endDate, nick: nick, bannedNick: bannedNick, token: userInfo.token }, function( data ) {
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
	if ( uid == undefined || moderatorsDetails[ uid ] == undefined ) {
		return false;
	}
	
	return moderatorsDetails[ uid ].name !== '';
}

function GetModeratorNameById( uid ){
	if ( moderatorsDetails[ uid ] == undefined ) {
		return 'Неизвестный ID модератора, возможно, поможет обновление страницы';
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
		result += '<br/>' + complainsList[ banKey ][ 'complains' ][ j ][ 'userName' ] + ' @ ' + complainsList[ banKey ][ 'complains' ][ j ][ 'date' ] + ': ' + complainsList[ banKey ][ 'complains' ][ j ][ 'reason' ];
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
		var dateJsObj = new Date( ( parseInt( messageList[ i ].banExpirationTime ) + SC2TV_TIME_DIFF )* 1000 );
		unBanDate = dateJsObj.toUTCString();
		
		moderatorName = messageList[ i ].moderatorName;
		
		var dateJsObj = new Date( ( parseInt( messageList[ i ].banTime ) + SC2TV_TIME_DIFF )* 1000 );
		banDate = dateJsObj.toUTCString();
		var banDuration = messageList[ i ].banDuration / 60;
		var banReasonId = messageList[ i ].banReasonId;
		
		var banReason = '';
		
		if ( parseInt( messageList[ i ].banMessageId ) > 0 ){
			banReason = ProcessReplaces( messageList[ i ] );
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
	return data;
}

// всевозможные замены
function ProcessReplaces( messageInfo ) {
	processReplacesMessageInfo = messageInfo;
	var message = messageInfo.bannedForMessage;
	// bb codes
	message = bbCodeToHtml( message );

	// смайлы
	message = message.replace( /:s(:[-a-z0-9]{2,}:)/gi, function( match, code ) {
		var indexOfSmileWithThatCode = -1;
		for ( var i = 0; i < smilesCount; i++ ) {
			if ( smiles[ i ].code == code ) {
				indexOfSmileWithThatCode = i;
				break;
			}
		};
		
		var replace = '';
		if ( indexOfSmileWithThatCode == -1 ) {
			replace = match;
		} else {
			replace = smileHtmlReplacement[ indexOfSmileWithThatCode ];
		}
		
		return replace;
	});
	
	return message;
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
	bannedNick = $( '#bannedNick' ).val();
	nick = PrepareNick( nick );
	bannedNick = PrepareNick( bannedNick );
	
	$.ajaxSetup({
		ifModified: true,
		cache: true
	});
	
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
		
		historyCache += channelId + '_' + startDate + ':00_' + endDate + ':00_' + nick + '_' + bannedNick;
		historyCache = historyCache.replace( /[\s]+/g, '_' );
		
		$.ajaxSetup( {
			statusCode: {
				404: function() {
					GetHistoryData( channelId, startDate, endDate, nick, bannedNick );
				}
			}
		});
	}
	
	historyCache += '.json';
	
	$.getJSON( historyCache, function( jsonData ){
		if ( jsonData != undefined ) {
			var messageList = jsonData.messages;
			if ( messageList.length > 0 ) {
				historyData = BuildHtml( messageList );
				ShowHistory( historyData );
			}
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
			/* mod by rentgen
			case 'bannedInChat': */
			case 'bannedOnSite':
				show_error( CHAT_HISTORY_FOR_USERS_ONLY );
			break;
		}
	}
}

$( document ).ready( function() {
	banKey = -1;
	
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
		uid = $.cookie( 'drupal_uid' );
		
		RequestHistory();
		
		$( '#history-form' ).submit(function() {
			RequestHistory();
			return false;
		});
	}
});

function CancelBan(){
	unBanReason =  $( '#reason' ).val();
	banModerator = $( '#banModerator' ).is(':checked');
	
	if ( banModerator === true ) {
		banModerator = 1;
	}
	else {
		banModerator = 0;
	}
	
	moderatorBanTime = $( '#moderatorBanTime').val();
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
			console.log( data.result );
		}
	});
	
	// чтобы отправка формы не перезагрузила страницу
	return false;
}

function EditBan(){
	editBanReason =  $( '#reason' ).val();
	newBanTime = $( '#newBanTime').val();
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
	complainBanReason =  $( '#reason' ).val();
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
		if ( moderatorsDetailsHtml == '' || moderatorsDetailsHtml == undefined ) {
			
			moderatorsDetailsHtml = '<div id="moderatorStatsBlock"><span id="moderatorStatsHeader">Топ ' + topModeratorsCount + ' модераторов (баны за месяц)</span><ol id="moderatorStats">';
			
			$.each( moderatorsDetails, function( modId, info ) {
				if ( moderatorsDetails[ modId ].bansCount != undefined && moderatorsDetails[ modId ].bansCount > 0 ) {
					moderatorsDetailsHtml += '<li><a href="' + SC2TV_URL + '/user/' + modId + '" rel="nofollow" target="_blank">' + moderatorsDetails[ modId ].name + '</a><span class="moderatorBansCount">' + moderatorsDetails[ modId ].bansCount + '</span></li>';
				}
			});
			
			moderatorsDetailsHtml += '</ol></div>';
			$( '#statsInfo' ).html( moderatorsDetailsHtml );
			
			$( '#moderatorStatsBlock > ol#moderatorStats > li' ).sortElements( function( a, b ){
				res = parseInt( $( b ).children( 'span.moderatorBansCount' ).text() ) >
					parseInt( $( a ).children( 'span.moderatorBansCount' ).text());
				return res ? 1 : -1;
			});
			
			$( '#moderatorStatsBlock > ol#moderatorStats > li' ).slice( topModeratorsCount ).css( 'display', 'none' );
		}
		
		if ( $( '#statsInfo' ).css( 'display') == 'none' ) {
			$( '#statsInfo' ).css( 'display', 'block' );
		}
		else {
			$( '#statsInfo' ).css( 'display', 'none' );
		}
	} );
}
