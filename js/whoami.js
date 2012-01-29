var SC2TV_URL = 'http://sc2tv.ru';
var CHAT_URL =  'http://chat.sc2tv.ru/';
var CHAT_HISTORY_URL = CHAT_URL + 'memfs/history/';
var CHAT_AUTH_INFO_FOR_USERS_ONLY = 'Информация доступна только для авторизованных в чате пользователей.';
var userInfo = [];

function ShowHistory( historyData ) {
	$( '#history').html( historyData );
}

function GetGroupNameByRid( rid ){
	switch( rid ){
		case '2':
			name = 'пользователь';
		break;
		case '3':
			name = 'root';
		break;
		case '4':
			name = 'admin';
		break;
		case '5':
			name = 'модератор';
		break;
		case '6':
			name = 'журналист';
		break;
		case '7':
			name = 'редактор';
		break;
		case '8':
			name = 'забанен на сайте';
		break;
		case '9':
			name = 'стример';
		break;
		default:
			name = 'anon';
		break;
	}
	return name;
}

function show_error( error ) {
	$( '#history' ).html( error );
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
	}
}

function ConvertDate( thisDate ){
	var dateJsObj = new Date( thisDate * 1000 );
	return dateJsObj.toUTCString();
}

$( document ).ready( function(){
	if ( IsAnon() === true ) {
		$( '#history-form' ).remove();
		$( '#history' ).html( CHAT_AUTH_INFO_FOR_USERS_ONLY );
	}
	else {
		Login();
		userInfoHtml = 'Uid: ' + userInfo.uid + '<br/>Имя: ' + userInfo.name + '<br/>Дата регистрации: ' + ConvertDate( userInfo.created ) + '<br/>Группа: ' + GetGroupNameByRid( userInfo.rid ) + '<br/>Тип: ' + userInfo.type;
		
		if ( userInfo.ban == '1' ) {
			userInfoHtml += '<br/>Дата бана: ' + ConvertDate( userInfo.banTime );
			userInfoHtml += '<br/>Дата истечения бана: ' + ConvertDate( userInfo.banExpirationTime );
			userInfoHtml += '<br/>Продолжительность бана: ' + ( userInfo.banExpirationTime - userInfo.banTime ) / 60 + ' мин';
		}
		
		if ( !( userInfo.error == '' || userInfo.error == undefined ) ){
			userInfoHtml += '<br/>Ошибка: ' + userInfo.error;
		}
		
		$( '#history' ).html( userInfoHtml );
	}
});