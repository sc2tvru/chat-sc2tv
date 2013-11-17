<?php

require_once 'core.php';

header( 'Content-Type: application/javascript' );

$db = GetDb();

$smilesResult = $db->Query( 'SELECT * FROM chat_smile' );

if ( $smilesResult === FALSE ) {
    SaveForDebug( 'Fetching smiles failed' );
    return;
}

$smiles = array();
while ( $smile = $smilesResult->fetch_assoc() ) {
    $smile['roles'] = array();
    $smile['private'] = false;
    $smiles[$smile['code']] = $smile;
}


$rolesResult = $db->Query( 'SELECT * FROM role_smiles' );

if ( $rolesResult === FALSE ) {
    SaveForDebug( 'Fetching role smiles failed' );
    return;
}

$smile_roles = array();
while ( $role = $rolesResult->fetch_assoc() ) {
    $rid = intval($role['rid']);
    foreach (explode(',', $role['smiles']) as $smile) {
        if ( array_key_exists( $smile, $smiles ) ) {
            $smiles[$smile]['roles'][] = $rid;
        }
    }
}

$public = array();
$private = array();
foreach ( $smiles as $smile ) {
    if ( count( array_diff( $smile['roles'], array( 2 ) ) ) > 0 ) {
        $smile['private'] = true;
        $private[] = $smile;
    } else {
        $public[] = $smile;
    }
}



echo "var smiles = ";
echo json_encode( array_merge( $public, $private) );
echo ";

var CHAT_IMG_DIR = '/img/';
var smilesCount = smiles.length;

var smileHtmlReplacement = [];
for( i = 0; i < smilesCount; i++ ) {
	smileHtmlReplacement[ i ] =
		'<img src=\"' + CHAT_IMG_DIR + smiles[ i ].img +
		'\" width=\"' + smiles[ i ].width +
		'\" height=\"' + smiles[ i ].height +
		'\" class=\"chat-smile\"/>';
}";
