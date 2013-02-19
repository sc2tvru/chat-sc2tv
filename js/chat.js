var CHAT_USER_MESSAGE_EMPTY = 'Введите сообщение, прежде чем нажимать Enter.';
var CHAT_USER_MESSAGE_ERROR = 'Ошибка при отправке сообщения.';
var CHAT_USER_BANNED = 'Вы были забанены.';
var CHAT_USER_NO_CAPS = 'КАПСИТЬ нельзя. Проверьте caps lock.';
var CHAT_USER_NO_SPAM_SMILES = 'В одном сообщении нельзя использовать 3 и более смайла.';
var user_name="";

// chat reload interval in ms
var CHAT_RELOAD_INTERVAL = 5000;
var CHAT_CHANNEL_RELOAD_INTERVAL = 300000;
var SC2TV_URL = 'http://sc2tv.ru';
var CHAT_URL =  'http://chat.sc2tv.ru/';
var CHAT_IMG_DIR = '/img/';
var chatTimerId = 0;
var channelList = [];
var screen2 = 0;
var userInfo = [];
var moderatorData = '';
var moderatorMessageList = [];
var prevModeratorMessageList = [];

var smilesCount = smiles.length;
smileHtml = '<div id="smile-panel-tab-1">';
smilePanelTabsHtml = '<span id="smile-panel-pager-1">[ 1 ]</span>';
for( i=0,t=2; i < smilesCount; i++) {
	smileHtml += '<img src="' + CHAT_IMG_DIR + smiles[i].img +'" title="' + smiles[i].code +'" width="' + smiles[i].width + '" height="' + smiles[i].height+ '"class="chat-smile" alt="' + smiles[i].code + '"/>';
	if ( i > 0 && i % 37 == 0 ) {
		smileHtml += '</div><div id="smile-panel-tab-' + t + '">';
		smilePanelTabsHtml += '<span id="smile-panel-pager-' + t + '">[ ' + t + ' ]</span>';
		t++;
	}
}
smileHtml += '</div>' + smilePanelTabsHtml;

chat_rules_link = '<a title="Правила чата" href="' + SC2TV_URL + '/chat-rules" target="_blank">rules</a>';
chat_history_link = '<a title="История чата" href="/history.htm" target="_blank">history</a>';
chat_ban_history_link = '<a title="История банов чата" href="/automoderation_history.htm" target="_blank">bans</a>';
chat_vkl_btn = '<span id="chat-on" title="включить чат" style="display:none;">chat</span><span title="отключить чат" id="chat-off">chat</span>';
img_btn = '<span id="smile-text" title="текстовые смайлы" style="display:none;">img</span><span id="smile-img" title="включить смайлы" style="display:none;">img</span><span id="smile-off" title="выключить смайлы">img</span>';
color_btn = '<span id="clr_nick_on" title="включить цветные ники">col</span><span id="clr_nick_off" title="выключить цветные ники">col</span>';
smiles_btn = '<span id="smile-btn">smile</span>';
smile_panel = '<div id="chat-smile-panel">' + smileHtml + '<div id="chat-smile-panel-close">X</div></div>';
divForFullScreen = '<div id="full-screen-place">.</div>';

form_chat = '<div id="chat-form"><form id="chat-form-id" method="post" action=""><input maxlength="1024" type="text" name="chat-text" class="chat-text"/></form>' + chat_vkl_btn + ' ' + img_btn + ' ' + color_btn + ' ' + smiles_btn + ' ' + chat_rules_link + ' ' + chat_history_link + ' ' + chat_ban_history_link + smile_panel + '</div>';

form_anon = '<div id="chat-form">' + divForFullScreen + chat_vkl_btn + ' ' + img_btn + ' ' + color_btn + ' ' + chat_history_link + ' <span>В чате могут писать только зарегистрированные пользователи.</span></div>';
 
form_banned = '<div id="chat-form">' + divForFullScreen + chat_vkl_btn + ' ' + img_btn + ' ' + chat_history_link + ' ' + chat_rules_link +  ' <span>Вы были забанены. </span> <a href="/automoderation_history.htm" target="_blank">Причина</a></div>';

form_newbie = '<div id="chat-form">' + divForFullScreen + chat_vkl_btn + ' ' + img_btn + ' ' + color_btn + ' ' + chat_history_link + ' <span>Вы зарегистрированы менее трех дней назад.</span></div>';

tpl_chat_stopped = "<div id='chat_closed'><div>Чатик остановлен!</div><div>Остановил: [stopper].</div><div>Остановлено до: [min]</div><div>Причина: [reason].</div></div>";
var chat_channel_id = 0;
autoScroll = 1;

var urlPattern = new RegExp(
	'((?:(?:ftp)|(?:https?))(?:://))' + // протокол (1)
	// URL без протокола (2)
	'(((?:(?:[a-z\u0430-\u0451\\d](?:[a-z\u0430-\u0451\\d-]*[a-z\u0430-\u0451\\d])*)\\.)+(?:[a-z]{2,}|\u0440\u0444)' + // хост (3)
	'|(?:(?:\\d{1,3}\\.){3}\\d{1,3}))' + // хост в формате IPv4 (3)
	'(:\\d+)?' + // порт (4)
	'(/[-a-z\u0430-\u0451\\d%_~\\+\\(\\):]*(?:[\\.,][-a-z\u0430-\u0451\\d%_~\\+\\(\\):]+)*)*' + // путь (5)
	'(\\?(?:&amp;|[:;a-z\u0430-\u0451\\d%_~\\+=-])*)?' + // параметры (6)
	'(#(?:&amp;|[:;a-z\u0430-\u0451\\d%_~\\+=-])*)?)' // якорь (7)
	, 'gi'
);
	
$(document).ready(function(){
	chat_channel_id = getParameterByName( 'channelId' );
	
	whoStopChat = getParameterByName( 'stop' );
	
	BuildChat();
	
	if ( whoStopChat == '0' || whoStopChat == undefined || whoStopChat == '' ) {
		//CheckIsChannelAllowed( chat_channel_id );
		if ( $.cookie( 'chat-on' ) == null || $.cookie( 'chat-on' ) == '1' ) {
			StartChat();
		}
		else {
			StopChat( true , '' );
		}
	}
	else {
		StopChat( false, 'Чат отключен. Для включения нажмите кнопку chat.' );
	}
});

function getParameterByName( name ) {
	name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
	var regexS = "[\\?&]" + name + "=([^&#]*)";
	var regex = new RegExp(regexS);
	var results = regex.exec(window.location.href);
	
	if( results == null ) {
		return '';
	}
	else {
		return decodeURIComponent(results[1].replace(/\+/g, " "));
	}
}

function CheckIsChannelAllowed( channelId ) {
	$.ajaxSetup({ ifModified: false, cache: true });
	$.getJSON( CHAT_URL + 'memfs/channels.json', function( data ) {
		if ( !( data == undefined || data == '' ) ){
			channelList = data.channel;
			var channelMaxNum = channelList.length - 1;
			
			for( var i=0; i <= channelMaxNum; i++ ) {
				if ( channelList[ i ].channelId == channelId ) {
					return true;
				}
			}
			StopChat( true , 'Канал с таким ID не найден в списке разрешенных.' );
			$( '#chat-on').remove();
		}
	});
}

function StartChat(){
	$.cookie( 'chat-on', '1', { expires: 365, path: '/'} );
	
	$( '#chat-on').hide();
	$( '#chat-off' ).show();
	$( '#smile-btn' ).show();
	
	chatTimerId = setInterval( 'ReadChat()', CHAT_RELOAD_INTERVAL );
	if( $.cookie( 'is_moderator') ) {
		GetChannelsList();
		channelListTimerId = setInterval( 'GetChannelsList()', CHAT_CHANNEL_RELOAD_INTERVAL );
	}
	
	$( '#chat-form-id' ).show();
	ReadChat( true );
	AddStreamerNameBtn();
}

function StopChat( setStopCookie, message ){
	clearInterval( chatTimerId );
	
	if ( setStopCookie == true ) {
		$.cookie( 'chat-on', '0', { expires: 365, path: '/'} );
	}
	
	if ( message == '' ) {
		message = 'Вы отключили чат.';
	}
	
	$( '#chat' ).html( message );
	
	$( '#chat-on' ).show();
	$( '#chat-off' ).hide();
	$( '#smile-btn' ).hide();
	$( '#chat-smile-panel' ).hide();
	$( '#chat-form-id' ).hide();
}

function GetChannelsList(){
	$.ajaxSetup({ ifModified: true, cache: false });
	$.getJSON( CHAT_URL + 'memfs/channels.json', function( data ) {
		if ( !( data == undefined || data == '' ) ) {
			channelList = data.channel;
		}
	});
	$.ajaxSetup({ ifModified: true, cache: true });
}

function toogleImgBtn() {
	if( $.cookie( 'chat-img' ) == null ) {
		$.cookie( 'chat-img', '1', { expires: 365, path: '/'} );
	}
	
	// smile: text,2 > img,1 > off,0
	$( '#smile-img' ).toggle( $.cookie( 'chat-img' ) == '2' );
	$( '#smile-off' ).toggle( $.cookie( 'chat-img' ) == '1' );
	$( '#smile-text' ).toggle( $.cookie( 'chat-img' ) == '0' );
	
	$( '#smile-text' ).on( 'click', function() {
		$.cookie( 'chat-img', '2', { expires: 365, path: '/'} );
		$(this).hide();
    $( '#smile-off' ).hide();
		$( '#smile-img' ).show();
	});

  $( '#smile-img' ).on( 'click', function() {
    $.cookie( 'chat-img', '1', { expires: 365, path: '/'} );
    $(this).hide();
    $( '#smile-text' ).hide();
    $( '#smile-off'  ).show();
  });

	$( '#smile-off' ).on( 'click', function() {
		$.cookie( 'chat-img', '0', { expires: 365, path: '/'} );
		$(this).hide();
    $( '#smile-img' ).hide();
		$( '#smile-text' ).show();
	});
}

function GetChannelId( id ) {
	id = id.replace( /[^0-9]/ig, '' );
	if( id == '' ) {
		id = 0;
	}
	return id;
}

function AddChannelTitles(){
	var channelMaxNum = channelList.length - 1;
	for( i=0; i <= channelMaxNum; i++) {
		$( 'div.channel-' + channelList[ i ].channelId + ' > span' ).each(function(index) {
			$( this ).attr( 'title', $( this ).attr( 'title' ) + ' @ ' + channelList[ i ].channelTitle );
		});
	}
}

function JumpToUserChannel( mid ) {
	messageClass = $( 'div[class$="' + mid + '"]').attr( 'class' );
	
	var regExpr = new RegExp( 'channel-([^ ]+) mess' );
	res = regExpr.exec( messageClass );
	channelId = res[1];
	
	channelClassPath = 'div.channel-' + chat_channel_id;
	$( channelClassPath ).attr(	'style', '' );
	
	chat_channel_id = channelId;
	
	channelClassPath = 'div.channel-' + channelId;
	$( channelClassPath ).attr(
		'style', 'background-color:#333333 !important;'
	);
	
	$( '.menushka' ).remove();
}

function ReadChat( firstRead ){
	// проверка, чтобы после отключения чат не обновился
	if ( $.cookie( 'chat-on' ) == '0' ) {
		return;
	}
	
	if ( firstRead == true ){
		$.ajaxSetup({ ifModified: false, cache: false });
	}
	else {
		$.ajaxSetup({ ifModified: true, cache: true });
	}
	
	// модеры читают все каналы
	if( $.cookie( 'is_moderator' ) && ( $.cookie( 'moderatorReadAllChannels' ) === '1' || $.cookie( 'moderatorReadAllChannels' ) == undefined ) ) {
		channelCount = channelList.length;
		
		$.getJSON( CHAT_URL + 'memfs/channel-moderator.json', function( jsonData ){
			if ( jsonData != undefined ) {
				var messageList = [];
				messageList = FilterMessages(jsonData.messages);
				data = BuildHtml( messageList );
				PutDataToChat( data );
			}
		});
	}
	else {
		channelId = GetChannelId( chat_channel_id );
		
		$.getJSON( CHAT_URL + 'memfs/channel-' + channelId + '.json', function( jsonData ){
			if ( jsonData != undefined ) {
				var messageList = [];
				messageList = jsonData.messages;
				data = BuildHtml( messageList );
				PutDataToChat( data );
			}
		});
	}
}

// add streamer nickname to the top
function AddStreamerNameBtn(){
	channelId = GetChannelId( chat_channel_id );
	
	if ( channelId == 0 ) {
		return;
	}
	
	$( '#chat-streamer-msg' ).remove();
	
	$.getJSON( CHAT_URL + 'memfs/channels.json', function( jsonData ){
		if ( !( jsonData == undefined || jsonData == '' ) ) {
			channelList = jsonData.channel;
			var channelMaxNum = channelList.length - 1;
		
			for( var i=0; i <= channelMaxNum; i++ ) {
				if ( channelList[ i ].channelId == channelId ) {
					streamerName = channelList[ i ].streamerName;
					if ( streamerName != '' ) {
						AddStreamerNameBtnHtml( streamerName );
					}
				}
			}
		}
	});
}

function AddStreamerNameBtnHtml( streamerName ) {
	$( '#stream-room' ).after( '<div title="написать стримеру" id="chat-streamer-msg">streamer</div>' );
	
	$( '#chat-streamer-msg' ).attr({
		title: 'написать стримеру ' + streamerName,
		style: 'color: #BBB !important'
	});
	
	$( '#chat-streamer-msg' ).off();
	
	$( '#chat-streamer-msg' ).on('click', function() {
		$( '.chat-text' ).val( '[b]' + streamerName + '[/b], ' );
		$( '.chat-text' ).focus();
	});
}

function FilterMessages(messages) {
    var pattern = /\w{0,2}[х]([х\s\!@#\$%\^&*+-\|\/]{0,2})[у]([у\s\!@#\$%\^&*+-\|\/]{0,2})[ёлeеюийя]\w{0,5}|\w{0,2}[п]([п\s\!@#\$%\^&*+-\|\/]{0,2})([ие\s\!@#\$%\^&*+-\|\/]{0,2})[3зс]([3зс\s\!@#\$%\^&*+-\|\/]{0,2})[д]\w{0,5}|[с][у]([у\!@#\$%\^&*+-\|\/]{0,2})[чк]\w{1,3}|\w{0,2}[б][л]([л\s\!@#\$%\^&*+-\|\/]{0,2})[я]\w{0,5}|\w{0,2}[её][б][лске@ыиа]([наи@йвл]|[щ])\w{0,5}|\w{0,2}[е]([е\s\!@#\$%\^&*+-\|\/]{0,2})[б]([б\s\!@#\$%\^&*+-\|\/]{0,2})[у]([у\s\!@#\$%\^&*+-\|\/]{0,2})[н4ч]\w{0,4}|\w{0,2}[её]([её\s\!@#\$%\^&*+-\|\/]{0,2})[б]([б\s\!@#\$%\^&*+-\|\/]{0,2})[н]([н\s\!@#\$%\^&*+-\|\/]{0,2})[у]\w{0,4}|\w{0,2}[е]([е\s\!@#\$%\^&*+-\|\/]{0,1})[б]([б\s\!@#\$%\^&*+-\|\/]{0,2})[оа@]([оа@\s\!@#\$%\^&*+-\|\/]{0,2})[тн]\w{0,4}|\w{0,5}[ё]([ё\!@#\$%\^&*+-\|\/]{0,2})[б]\w{0,5}|\w{0,2}[п]([п\s\!@#\$%\^&*+-\|\/]{0,1})[ие]([ие\s\!@#\$%\^&*+-\|\/]{0,2})[д]([д\s\!@#\$%\^&*+-\|\/]{0,2})([оа@еи\s\!@#\$%\^&*+-\|\/]{0,1})[р]\w{0,5}|\w{0,3}[с][у]([у\s\!@#\$%\^&*+-\|\/]{0,1})[к]\w{0,5}|\w{0,2}[м]([м\s\!@#\$%\^&*+-\|\/]{0,2})[у]([у\s\!@#\$%\^&*+-\|\/]{0,2})[д]([д\s\!@#\$%\^&*+-\|\/]{0,1})[аи]\w{0,5}|\w{0,2}[её]([её\s\!@#\$%\^&*+-\|\/]{0,2})[п]([п\s\!@#\$%\^&*+-\|\/]{0,2})[т]\w{0,5}/gi;

    for (i=0; i < messages.length; i++) {
        messages[i].message = messages[i].message.replace(pattern, '<font color="red">[$&]</font>');
    }

    return messages;
}

function PutDataToChat( data ) {
	channelId = GetChannelId( chat_channel_id );
	
	if( $.cookie( 'is_moderator') ) {
		data = data.replace('class="censured"', 'class="red"');
		$( '#chat' ).html( data );
		AddChannelTitles();
		channelClassPath = 'div.channel-' + channelId;
		$( channelClassPath ).attr(
			'style', 'background-color:#333333 !important;'
		);
	}
	else {
		// TODO убрать?
		DIV = document.createElement( 'DIV' );
		DIV.innerHTML = data;
		$( '#chat' ).html( $( 'div.channel-' + channelId, DIV) );
	}
	
	if (autoScroll == 1) {
		$("#chat").scrollTop(10000000);
	}
}

function MakeShrinkUrl( str, proto, url ) {
    if ( url.length > 60 ) {
		length = url.length;
		return '<a rel="nofollow" href="' + str + '" target="_blank" title="' + str + '">' + url.substring( 0, 30 ) + '...' + url.substring( length - 20) + '</a>';
	}
	return '<a rel="nofollow" href="' + str + '" target="_blank">' + url + '</a>';
}


// всевозможные замены
function ProcessReplaces( str ) {
	// смайлы
	for( i = 0; i < smilesCount; i++ ) {
		smileHtml = '<img src="' + CHAT_IMG_DIR + smiles[ i ].img +'" width="' + smiles[ i ].width + '" height="' + smiles[ i ].height+ '" class="chat-smile"/>';
		var smilePattern = new RegExp( RegExp.escape( ':s' + smiles[ i ].code ), 'gi' );
		if ( $.cookie( 'chat-img' ) == '1' ) {
			str = str.replace( smilePattern, smileHtml );
		}
		else if ( $.cookie( 'chat-img' ) == '0' ) {
			str = str.replace( smilePattern, '' );
		}
	}
	
	// URL
	str = str.replace( urlPattern, MakeShrinkUrl );

	return str;
}

// спеццвета
function GetSpecColor( uid ) {
	var color = '';
	switch( uid ) {
		// Laylah
		case '20546':
		// Kitsune
		case '11378':
		// Mary_zerg
		case '65377':
		// Siena
		case '8324':
        //milkSHake
        case '22600':
			color = '#FFC0CB';
		break;
		// Kas
		case '62395':
			color = '#5DA130';
        break;
        // Usual color of regular users
        //BIT
        case '41386':
            color = '#7797BE';
        break;
		default:
			color = '';
	}
	return color;
}

RegExp.escape = function(text) {
	if ( text != undefined ) {
		return text.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&");
	}
}

// в игноре ли пользователь
function IsUserIgnored( uid ) {
	if ( $.cookie( 'chat_ignored' ) == '' || $.cookie( 'chat_ignored' ) == undefined ) {
		return false;
	}
	
	var ignoredUids = $.cookie( 'chat_ignored').split( ',' );
	
	if ( !$.isArray( ignoredUids ) ) {
		return false;
	}
	
	var ignoredCount = ignoredUids.length;
	for( var k = 0; k < ignoredCount; k++ ) {
		if ( ignoredUids[ k ] == uid ) {
			return true;
		}
	}
	
	return false;
}

function BuildChat( dataForBuild ) {
	if ( IsAnon() == true ) {
		userInfo.type = 'anon';
	}
	else if ( dataForBuild == null ) {
		// данных для сборки нет, запрашиваем сервер
		$.ajaxSetup( { async: false, cache: false } );
		
		$.getJSON( CHAT_URL + 'gate.php?task=GetUserInfo&ref=' + document.referrer, function( data ) {
			userInfo = data;
		});
		
		$.ajaxSetup({ async: true, cache: true });
	}
	else {
		userInfo = dataForBuild;
	}
	
	switch( userInfo.type ){
		case 'anon':
			myform = form_anon;
		break;
		case 'newbie':
			myform = form_newbie;
		break;
		case 'bannedInChat':
		case 'bannedOnSite':
			myform = form_banned;
		break;
		default:
			myform = form_chat;
	}
	
	if ( userInfo.isModerator == '1' ) {
		$.cookie( 'is_moderator', '1', { expires: 365, path: '/'} );
	}
	
	$('#dialog2').html('<div id="add_styles"></div><div class="chat-channel-name"><div title="перейти на главный канал" class="channel 0">main</div><div id="stream-room" title="перейти на другой канал" class="channel other">other</div><br style="clear:both"/></div><div id="chat"></div>'+myform);
  
	if ( top === self ) {
		$( '#dialog2' ).css( 'background-color', '#000000' );
	}
	
	// chat window size
	var chatWindowHeight = getParameterByName( 'height' );
	
	if ( chatWindowHeight == undefined || chatWindowHeight == '' ) {
		$('#dialog2').css( 'height', '440px' );
		$('#chat').css( 'height', '375px' );
	}
	else {
		$('#dialog2').css( 'height', chatWindowHeight );
		$('#chat').css( 'height', parseInt( chatWindowHeight ) - 65 + 'px' );
	}
	
	var chatWindowWidth = getParameterByName( 'width' );
	
	if ( chatWindowWidth == undefined || chatWindowWidth == '' ) {
		$('#dialog2').css( 'width', '224px' );
		$('#chat').css( 'width', '214px' );
		$('.chat-text').css( 'width', '191px' );
	}
	else {
		$('#dialog2').css( 'width', chatWindowWidth );
		$('#chat').css( 'width', parseInt( chatWindowWidth ) - 10 + 'px' );
		$('.chat-text').css( 'width', parseInt( chatWindowWidth ) - 33 + 'px' );
	}
	
	toogleImgBtn();
	
	$( '#chat-form-id' ).submit(function() {
		WriteMessage();
		return false;
	});
	
	$( '#smile-btn').click( function(){
		$( '#chat-smile-panel > span').removeClass( 'active' );
		$( '#chat-smile-panel > div' ).hide();
		$( '#chat-smile-panel' ).show();
		$( '#chat-smile-panel > div#smile-panel-tab-1' ).show();
	});
	
	chatObj = document.getElementById( 'chat' );
	
	$( '#chat' ).scroll( function(){
		autoScroll = (chatObj.scrollHeight-chatObj.scrollTop<chatObj.clientHeight+5) ? 1:0;
	});
	
	$( '.chat-smile' ).click( function(){
		$( '#chat-smile-panel > div' ).hide();
		$( '#chat-smile-panel' ).hide();
		chat_text = $( '.chat-text' ).val();
		$( '.chat-text' ).val( chat_text + ' ' + $(this).attr( 'title' ) + ' ' );
		$( '.chat-text' ).focus();
	});
	
	$( '#chat-smile-panel-close').click( function(){
		$( '#chat-smile-panel > div' ).hide();
		$( '#chat-smile-panel' ).hide();
	});
	
	$( '#chat-on' ).click( function(){
		StartChat();
	});
	
	$( '#chat-off' ).click( function(){
		StopChat( true, '' );
	});
	
	$( '#chat-smile-panel > span').click( function(){
		$( '#chat-smile-panel > div' ).hide();
		smilePanelTabNum = $(this).html().replace( /[[\] ]/g, '' );
		$( '#chat-smile-panel > div#smile-panel-tab-' + smilePanelTabNum ).show();
		$( '#chat-smile-panel > span').removeClass( 'active' );
		$(this).addClass( 'active' );
	});
	
	//toogle color nick btn
	//need refactoring
	$( '#clr_nick_on').click( function(){
		$.cookie( 'chat_color_nicks_off', '0', { expires: 365, path: '/'} );
		$(this).hide();
		$( '#clr_nick_off').show();
	});	

	$( '#clr_nick_off').click( function(){
		$.cookie( 'chat_color_nicks_off', '1', { expires: 365, path: '/'} );
		$(this).hide();
		$( '#clr_nick_on').show();
	});
	
	toogleStreamChatRoom();	
	toogleChatRooms();
}

//change stream room when userstream channel is loading
function toogleStreamChatRoom() {
	$("#stream-room").attr({
		'class': 'channel ' + chat_channel_id,
		title: 'канал ' + chat_channel_id,
		style: 'color: #BBB !important'
	}).text( 'stream' );
}

function toogleChatRooms() {
	$( 'div.' + chat_channel_id ).attr( 'style', 'color: #BBB !important' );
	$( 'div.chat-channel-name > div.channel' ).on('click', function() {
		if($(this).attr( 'id' ) == 'stream-room' ) {
			chat_channel_id = getParameterByName( 'channelId' );
			toogleStreamChatRoom();
		}
		
		channel_name = $(this).attr( 'class' );
		chat_channel_id = channel_name.replace( 'channel ', '' );
		
		$( 'div.chat-channel-name > div' ).attr( 'style', '' );
		$( this ).attr( 'style', 'color: #BBB !important' );
		ReadChat();
	});
}

function BanUser( uid, user_name, duration, mid, channelId ){
	$.post( CHAT_URL + 'gate.php', { task: 'BanUser', banUserId: uid, userName: user_name, duration: duration, messageId: mid, channelId: channelId, token: userInfo.token }, function( data ) {
		data = $.parseJSON( data );
		CheckUserState( data );
		if( data.code == 0 ) {
			$( '.menushka' ).html( data.error );
		}
		else if ( data.code == 1 ) {
			$( '.menushka' ).html( user_name + ' забанен на ' + duration + ' мин' );
		}
		ReadChat();
		$('.menushka').fadeOut( 5000 );
	});
}

function DeleteMessage( mid, channelId ) {
	$.post( CHAT_URL + 'gate.php', { task: 'DeleteMessage', messageId: mid, channelId: channelId, token: userInfo.token }, function(data) {
		data = $.parseJSON( data );
		CheckUserState( data );
		if( data.code == 0 ) {
			show_error( data.error );
		}
		ReadChat();
		$('.menushka').remove();
	});
}

function IgnoreUnignore( username, uid ) {
	// 1й игнор
	if( !$.cookie( 'chat_ignored' ) ) {
		$.cookie( 'chat_ignored', uid + ',', { expires: 365, path: '/'} );
		alert( 'Вы заигнорили ' + username );
		$('.menushka').remove();
		return true;
	}
	
	ignoredString = $.cookie( 'chat_ignored' );
	
	// пользователь не найден в игнорлисте, заносим в него
	if( ignoredString.indexOf( uid ) == -1 ) {
		ignoredString += uid + ',';
		alert( 'Вы заигнорили ' + username );
	}
	else {
		// пользователь найден, снимаем игнор
		uidPattern = new RegExp( '[,]*' + uid + ',', 'i' );
		ignoredString = ignoredString.replace( uidPattern, ',' );
		alert( 'Вы разблокировали ' + username );
	}
	if ( ignoredString == ',' ) {
		ignoredString = '';
	}
	
	$.cookie( 'chat_ignored', ignoredString, { expires: 365, path: '/'} );
	$('.menushka').remove();
}


function VoteForUserBan( uid, user_name, mid, reasonId ) {
	$.ajaxSetup({ async: false });
	$.post( CHAT_URL + 'gate.php', {task: 'CitizenVoteForUserBan', banUserId: uid, userName: user_name, messageId: mid, reasonId: reasonId, token: userInfo.token }, function( data ) {
		data = $.parseJSON( data );
		CheckUserState( data );
		$('.menushka').html( data.result );
	});
	$.ajaxSetup({ async: true });
	
	$('.menushka').fadeOut( 10000 );
}

function ShowBanMenuForCitizen( uid, user_name, mid ) {
	currentMenushaTop = $('.menushka').css( 'top' );
	$('.menushka').css( 'top', 95 );
	$('.menushka').html( '<li class="citizen-li" id="citizenBanReasonId-1">Мат</li><li class="citizen-li" id="citizenBanReasonId-5">Серьезные оскорбления</li><li class="citizen-li" id="citizenBanReasonId-6">Национализм, нацизм</li><li class="citizen-li" id="citizenBanReasonId-12">Вредные ссылки</li><li class="citizen-li" id="citizenBanReasonId-2">Завуалированный мат</li><li class="citizen-li" id="citizenBanReasonId-3">Спам грубыми словами</li><li class="citizen-li" id="citizenBanReasonId-4">Легкие оскорбления</li><li class="citizen-li" id="citizenBanReasonId-7">Реклама</li><li class="citizen-li" id="citizenBanReasonId-8">Спам</li><li class="citizen-li" id="citizenBanReasonId-9">Клевета</li><li class="citizen-li" id="citizenBanReasonId-10">Негативный троллинг</li><li class="citizen-li" id="citizenBanReasonId-11">Транслит, удаффщина, капсы</li><li class="citizen-li" id="citizenBanReasonId-13">Вредные флэшмобы</li><li class="citizen-li" id="citizenBanReasonId-14">Спойлер</li><span class="menushka_close">X</span>');
	
	$( '.citizen-li' ).bind('click', function(){
		reasonId = $(this).attr( 'id' );
		reasonId = reasonId.replace( 'citizenBanReasonId-', '' );
		VoteForUserBan( uid, user_name, mid, reasonId );
	} );
	
	$( '.menushka_close' ).bind('click', function(){
		$('.menushka').remove();
	} );
}

function otvet(nick){
	$('.chat-text').val('[b]'+nick+'[/b], ');
	$('.chat-text').focus();
	$('.menushka').remove();
}

function getmenu( nick, mid, uid, channelId ) {
	user_name = $( nick ).html();
	
	// do not show menu for system messages
	if ( uid == '-1' ) {
		return false;
	}
	
	$( '.menushka' ).remove();
	
	// for banned users show only ignore/unignore
	if ( userInfo.type == 'bannedInChat' || userInfo.type == 'bannedOnSite' ) {
		$( 'body' ).append( '<ul class="menushka" style="display:block;"><li onclick="IgnoreUnignore(user_name, ' + uid + ');">Ignore\Unignore</li><span class="menushka_close" onclick="$(\'.menushka\').remove();">X</span></ul>' );
		return false;
	}
		
	rid = parseInt( userInfo.rid );
	
	switch( rid ) {
		// юзер
		case 2:
		// журналист
		case 6:
		// редактор
		case 7:
		// стример
		case 9:
		// фанстример
		case 10:
		// real стример
		case 14:
			$( 'body' ).append( '<ul class="menushka" style="display:block;"><li onclick=otvet(user_name)>Ответить</li><li><a href="' + SC2TV_URL + '/messages/new/' + uid + '" target="_blank" onclick="$(\'.menushka\').remove();">Послать ЛС</a></li><li onclick="ShowBanMenuForCitizen(' + uid +',user_name,' + mid + ')">Забанить</li><li onclick="IgnoreUnignore(user_name, ' + uid + ');">Ignore\Unignore</li><span class="menushka_close" onclick="$(\'.menushka\').remove();">X</span></ul>' );
		break;
		
		// root
		case 3:
		// админ
		case 4:
			$( 'body' ).append( '<ul class="menushka" style="display:block;"><li onclick=otvet(user_name)>Ответить</li><li onclick="DeleteMessage( ' + mid + ', ' + channelId + ')">Удалить сообщение</li><li onclick="JumpToUserChannel(' + mid + ')">В канал к юзеру</li><li><a href="' + SC2TV_URL + '/messages/new/' + uid + '" target="_blank" onclick="$(\'.menushka\').remove();">Послать ЛС</a></li><li onclick="BanUser( ' + uid + ', user_name, 10, ' + mid + ', ' + channelId + ')">Молчать 10 мин.</li><li onclick="BanUser(' + uid + ', user_name, 1440, ' + mid + ', ' + channelId + ')">Молчать сутки</li><li onclick="BanUser( ' + uid + ', user_name, 4320, ' + mid + ', ' + channelId + ')">Молчать 3 дня</li><li onclick="ShowBanMenuForCitizen(' + uid +',user_name,' + mid + ')">Забанить</li><li onclick="IgnoreUnignore(user_name, ' + uid + ' );">Ignore\Unignore</li><span class="menushka_close" onclick="$(\'.menushka\').remove();">X</span></ul>' );
		// модер
		case 5:
			$( 'body' ).append( '<ul class="menushka" style="display:block;"><li onclick=otvet(user_name)>Ответить</li><li onclick="DeleteMessage( ' + mid + ', ' + channelId + ')">Удалить сообщение</li><li onclick="JumpToUserChannel(' + mid + ')">В канал к юзеру</li><li><a href="' + SC2TV_URL + '/messages/new/' + uid + '" target="_blank" onclick="$(\'.menushka\').remove();">Послать ЛС</a></li><li onclick="BanUser( ' + uid + ', user_name, 10, ' + mid + ', ' + channelId + ')">Молчать 10 мин.</li><li onclick="BanUser(' + uid + ', user_name, 1440, ' + mid + ', ' + channelId + ')">Молчать сутки</li><li onclick="BanUser( ' + uid + ', user_name, 4320, ' + mid + ', ' + channelId + ')">Молчать 3 дня</li><li onclick="ShowBanMenuForCitizen(' + uid +',user_name,' + mid + ')">Забанить</li><span class="menushka_close" onclick="$(\'.menushka\').remove();">X</span></ul>' );
		break;
		
		default:
			return false;
	}
}

// сборка html для канала
function BuildHtml( messageList ) {
	var data = '';
	
	var messageCount = messageList.length;
	
	if ( messageCount == 0 ) {
		return '';
	}
	
	for( i=0; i < messageCount; i++ ) {
		var nicknameClass = 'nick';
		var color = '';
		var customColorStyle = '';
		
		// сообщения пользователей и системы выглядят по-разному
		if ( messageList[ i ].uid != -1 ) {
			var textClass = 'text';
			
			// подсветка ников выключена
			if ( $.cookie( 'chat_color_nicks_off') == '1') {
				nicknameClass += ' user-2';
			}
			else {
				color = GetSpecColor( messageList[ i ].uid );
				// если не блат, то цвет по классу группы
				if ( color == '' ) {
					nicknameClass += ' user-' + messageList[ i ].rid;
				}
				else {
					customColorStyle = ' style="color:' + color + ';"';
				}
			}
		}
		else {
			nicknameClass = 'system-nick';
			var textClass = 'system_text';
			customColorStyle = '';
		}
		
		channelId = messageList[ i ].channelId;
		
		// TODO убрать лишнее
		if( IsUserIgnored( messageList[ i ].uid ) == true ) {
			nicknameClass += ' ignored';
			textClass += ' ignored';
		}
		
		var userMenu = '';
		if ( userInfo.rid != 8 ) {
			userMenu = 'onClick="getmenu(this,' + messageList[ i ].id + ',' + messageList[ i ].uid + ', ' + channelId + ')" ';
		}
		
		// img on/off
		smileOnly = false;
		
		if( $.cookie( 'chat-img' ) == '0' ) {
			msg = messageList[ i ].message;
			msg = msg.replace( /[\s]+/g, '' );
			regexp = /^(<b>[^<]*<\/b>[,\s]*){0,}[\s]*(:s:[^:]+:[\s]*){1,}[\s]*$/gi;
			smileOnly = regexp.test( msg );
		}
		
		if ( smileOnly == false ) {
			data = '<div class="channel-' + channelId + ' mess message_' + messageList[ i ].id + '"><span' + customColorStyle + ' class="' + nicknameClass + '"' + userMenu + 'title="' + messageList[ i ].date + '">' + messageList[ i ].name + '</span><p class="' + textClass + '">' + messageList[ i ].message + '</p></div>' + data;
		}
	}
	
	data = ProcessReplaces( data );
	
	if( !$.cookie( 'chat_channel_id') ) {
		$.cookie( 'chat_channel_id', chat_channel_id, {path: '/'} );
	}
	
	if ( userInfo.name != undefined ) {
		// подсветка своих сообщений
		var regExp = new RegExp( '><b>' + RegExp.escape( userInfo.name ) +'<\/b>,', 'mig' );
		data = data.replace(regExp, " style='color:#f36223' $&");
	}
	
	return data;
}

// изменение кода смайлов, к которым привыкли пользователи, на коды, которые можно выделить регуляркой в php
function FixSmileCode( str ) {
	for( i = 0; i < smilesCount; i++) {
		var smilePattern = new RegExp( RegExp.escape( smiles[ i ].code ), 'gi' );
		str = str.replace( smilePattern, ':s' + smiles[ i ].code  );
	}
	return str;
}

function WriteMessage(){
	msg = $( '.chat-text' ).val();
	
	/* удаляем явно не разрешенные символы
	разрешены
	U+0020 - U+003F - знаки препинания и арабские цифры
	U+0040 - U+007E http://ru.wikipedia.org/wiki/Латинский_алфавит_в_Юникоде
	U+0400 - U+045F, U+0490, U+0491, U+0207, U+0239 http://ru.wikipedia.org/wiki/Кириллица_в_Юникоде
	U+2012, U+2013, U+2014 - тире
	*/
	// whitespaces
	msg = msg.replace( /[^\u0020-\u007E\u0400-\u045F\u0490\u0491\u0207\u0239\u2012\u2013\u2014]+/g, '' );
	msg = msg.replace( /[\s]+/g, ' ' );
	
	if( msg == '' ) {
		show_error( CHAT_USER_MESSAGE_EMPTY );
		return false;
	}
	
	if( IsStringCapsOrAbuse( msg ) == true ) {
		show_error( CHAT_USER_NO_CAPS );
		return false;
	}
	
	msg = FixSmileCode( msg );
	
	if ( CheckForAutoBan( msg ) == true ) {
		show_error( CHAT_USER_NO_SPAM_SMILES );
		return false;
	}
	
	$.ajaxSetup({ async: false });
	$.post( CHAT_URL + 'gate.php', { task: 'WriteMessage', message: msg, channel_id: chat_channel_id, token: userInfo.token }, function( jsonData ) {
		data = $.parseJSON( jsonData );
		
		CheckUserState( data );
		
		if( data.error == '' ) {
			$( '.chat-text' ).val('');
			ReadChat();
		}
		else {
			show_error( CHAT_USER_MESSAGE_ERROR );
		}
	});
	$.ajaxSetup({ async: true });
}

function CheckUserState( currentUserData ) {
	if( currentUserData.type == userInfo.type && currentUserData.token == userInfo.token ) {
		// наверное, пусть лучше будет задержка между отправкой сообщения и появлением его в чате (или другим действием),
		// чем "прыжки", когда сразу виден чат с сообщением, а потом все затирается старой версией из-за тормозов сервера\сети
		//ReadChat();
	}
	else {
		show_error( currentUserData.error );
		BuildChat( currentUserData );
	}
}

function IsStringCapsOrAbuse( str ) {
	// удаляем обращения вроде [b]MEGAKILLER[/b], bb-код [b][/b]
	tempStr = str.replace( /\[b\][-\.\w\u0400-\u045F\u0490\u0491\u0207\u0239\[\]]+\[\/b\]|\[b\]|\[\/b\]/gi, '' );
	
	regexp = /[^\s]+/gi;
	lettersInStr = regexp.test( tempStr );
	
	// если остались только пробельные символы, это абуз
	if ( lettersInStr == false ) {
		return true;
	}
	
	// url
	tempStr = tempStr.replace( urlPattern, '' );
	
	// коды смайлов
	tempStr = tempStr.replace( /:s:[^:]+:/gi, '' );
	
	// общее кол-во букв независимо от регистра
	// fix for Opera, [a-z\u0400-\u045F\u0490\u0491\u0207\u0239] doensn't work
	regexp = /[a-z]|[\u0400-\u045F\u0490\u0491\u0207\u0239]/gi;
	letters = tempStr.match( regexp );
	
	if ( letters == null ) {
		return false;
	}
	
	len = letters.length;
	
	// кол-во букв в верхнем регистре
	regexp = /[A-ZА-Я]/g;
	caps = tempStr.match( regexp );
	
	if ( caps == null ) {
		return false;
	}
	
	if( caps != '' && caps.length >= 5 && caps.length > ( len / 2 ) ) {
		if ( 'console' in window ) {
			console.log( 'caps detect' );
			console.log( 'tempStr: ' + tempStr );
			console.log( 'letters: ' + letters );
			console.log( 'caps=' + caps );
			console.log( 'len = ' + len );
			console.log( 'caps.length = ' + caps.length + ' > ' + len/2 );
		}
		return true;
	}
	else {
		return false;
	}
}

function CheckForAutoBan( str ) {
	// 3 смайла
	regexp = /(?::s:[^:]+:.*){3,}/gi;
	stringWithThreeSmiles = str.match( regexp );
	
	if ( stringWithThreeSmiles == null ) {
		return false;
	}
	else {
		return true;
	}
}
	
function show_error( err ) {
	alert( 'Ошибка: ' + err );
}

function show_result(res){
	//alert (res);
}

function IsAnon(){
	return $.cookie( 'drupal_user' ) === null;
}
