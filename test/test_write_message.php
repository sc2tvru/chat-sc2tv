<?php

define( 'CURRENT_DATE', 'today' );

require_once '../chat.php';

class MysqliStub {
    private $message;

    public function setCorrectResult($message) {
        $this->message = $message;
    }

    public function real_escape_string($message) {
        if($this->message !== $message) {
            print "Test failed:\n" ;
            print "Expected \"" . $this->message . "\" but was \"" . $message . "\"\n";
        }

        return $message;
    }
}

class QueryResultStub {
    function __construct($smiles) {
        $this->smiles = $smiles;
    }

    public function fetch_assoc() {
        if($data = array_shift($this->smiles)) {
            return array( 'smiles' => $data, );
        }

        return false;
    }
}

class FakeDB {
    function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    public function Query( $query ) {
        if ( $query === 'SELECT smiles FROM role_smiles WHERE rid in (1)' ) {
            return new QueryResultStub(array(''));
        } else if ( $query === 'SELECT smiles FROM role_smiles WHERE rid in (2,3)' ) {
            return new QueryResultStub(array(':a:', ':b:'));
        }

        return false;
    }
}

class ChatMock extends Chat {
    function __construct() {
        parent::__construct(null);

        $this->channelId = 10;
    }
}

$chat = new ChatMock();
$mysqlStub = new MysqliStub();
$chat->SetDatabase(new FakeDB($mysqlStub));


$chat->user[ 'roleIds' ] = array(1);

$mysqlStub->setCorrectResult('sdfdsfsd fsd f sadf sdf');
$chat->WriteMessage('sdfdsfsd fsd:s:d-f:f sadf sdf');

$mysqlStub->setCorrectResult('sdf dsfsd fsdf sad:dd sdf:f sdf');
$chat->WriteMessage('sdf:s:c:dsfsd fsdf sad:dd sdf:f sdf');



$chat->user[ 'roleIds' ] = array(2, 3);

$mysqlStub->setCorrectResult('sdf:s:a:dsfsd fsdf sa df sdf');
$chat->WriteMessage('sdf:s:a:dsfsd fsdf sa:s:c:df sdf');

$mysqlStub->setCorrectResult('sdfds:s:gg_ff:fs d fs:s:b:df sadf sdf');
$chat->WriteMessage('sdfds:s:gg_ff:fs d fs:s:b:df sadf sdf');

$mysqlStub->setCorrectResult('какой-то текст со :s:b: смайлом');
$chat->WriteMessage('какой-то:s:c:текст со :s:b: смайлом');

$mysqlStub->setCorrectResult('какой-то:s:пп:текст со :s:b: смайлом');
$chat->WriteMessage('какой-то:s:пп:текст со :s:b: смайлом');
