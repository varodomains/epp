<?php
    use Ratchet\MessageComponentInterface;
    use Ratchet\ConnectionInterface;
    use Ratchet\Server\IoServer;

    include "etc/config.php";

    require_once $GLOBALS["eppPath"]."etc/vendor/autoload.php";
    include $GLOBALS["eppPath"]."etc/functions.php";
    include $GLOBALS["varoPath"]."etc/includes.php";
    
    class EPPSocket implements MessageComponentInterface {
        public function __construct() {
           	$GLOBALS["users"] = [];
        }

        public function onOpen(ConnectionInterface $conn) {
            $rID = $conn->resourceId;
            $GLOBALS["users"][$rID] = [];
        }

        public function onMessage(ConnectionInterface $from, $msg) {
            $rID = $from->resourceId;
            include $GLOBALS["eppPath"]."etc/parse.php";
        }

        public function onClose(ConnectionInterface $conn) {
            $rID = $conn->resourceId;
            logMessage($conn, "", "DISCONNECT");
            unset($GLOBALS["users"][$rID]);
        }

        public function onError(ConnectionInterface $conn, \Exception $e) {
        	//var_dump($e);
        }
    }

    $socket = new EPPSocket();
    $server = IoServer::factory($socket, $GLOBALS["port"]);
    $server->run();
?>