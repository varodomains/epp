<?php
    $proxy = substr($msg, 0, 5);
    if ($proxy === "PROXY") {
        preg_match("/PROXY TCP4 (?<ip>.+?) .+/", $msg, $match);

        if ($match) {
            $GLOBALS["users"][$rID]["ipAddress"] = $match["ip"];
        }

        logMessage($from, "", "CONNECT");
        $greeting = reply($from, "", "greeting");
        $from->send($greeting);
    }
    else {
        $header = substr($msg, 0, 4);
        $length = @unpack("N", $header)[1];

        if ($length > 4) {
            $length -= 4;
            
            $msg = substr($msg, 4, $length);

            $xml = new SimpleXMLElement($msg);
            $data = $xml->command->children()[0];
            $command = $data->getName();

            //echo "Message ({$from->resourceId}): ".$msg;
            logMessage($from, $msg, "IN");

            include $GLOBALS["eppPath"]."etc/handle.php";
        }
    }
?>