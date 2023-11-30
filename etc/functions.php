<?php
	function arrayToXml($array, &$xml){
	    foreach ($array as $key => $value) {
	        if(is_int($key)){
	            $key = "e";
	        }
	        if(is_array($value)){
	            $label = $xml->addChild($key);
	            $this->arrayToXml($value, $label);
	        }
	        else {
	            $xml->addChild($key, $value);
	        }
	    }
	}

	function xmlToArray($xmlstring) {
		$xml = simplexml_load_string($xmlstring, "SimpleXMLElement", LIBXML_NOCDATA);
		$json = json_encode($xml);
		$array = json_decode($json,TRUE);

		return $array;
	}

	function reply($socket, $msg, $command, $code=0, $message="", $data=[]) {
		$template = file_get_contents($GLOBALS["eppPath"]."etc/replies/".$command.".xml");

		if ($msg !== "") {
			$msg->registerXPathNamespace("epp", "urn:ietf:params:xml:ns:epp-1.0");
	        $clTRID = @(string)$msg->xpath("//epp:command/epp:clTRID")[0];
	    }
		
		$variables["siteName"] = $GLOBALS["siteName"];
		$variables["hostName"] = $GLOBALS["icannHostname"];
		
		$variables["code"] = $code;
		$variables["message"] = $message;
		$variables["date"] = date("c");
		$variables["clTRID"] = @$clTRID;
		$variables["svTRID"] = $GLOBALS["siteName"]."-".generateID(16);
		
		include $GLOBALS["eppPath"]."etc/reply.php";

		$header = '<?xml version="1.0" encoding="UTF-8"?>';
		$body = replaceVariables($template, $variables);
		$combined = prettify(minify($header.$body));
		$reply = $combined;
		$length = pack("N", strlen($reply)+4);
		$output = $length.$reply;

		//echo "Reply: ".$output;
		logMessage($socket, $reply, "OUT");

		return $output;
	}

	function minify($html) {
		$search = ['/(\n|^)(\x20+|\t)/', '/(\n|^)\/\/(.*?)(\n|$)/', '/\n/', '/\<\!--.*?-->/', '/(\x20+|\t)/', '/\>\s+\</', '/(\"|\')\s+\>/', '/=\s+(\"|\')/'];
		$replace = ["\n", "\n", " ", "", " ", "><", "$1>", "=$1"];
	    $html = preg_replace($search, $replace, $html);
	    return $html;
	}

	function prettify($html) {
		$xml = $html;
		$dom = new \DOMDocument('1.0');
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = true;
		$dom->loadXML($xml);
		$xml_pretty = $dom->saveXML();
		return $xml_pretty;
	}

	function logMessage($socket, $msg, $direction) {
		$time = date("c");
		$rID = $socket->resourceId;
		$output = "[".$rID."] [".$time."] ";

		switch ($direction) {
			case "CONNECT":
			case "DISCONNECT":
				$msg = $direction.": ".@$GLOBALS["users"][$rID]["ipAddress"];
				break;

			case "IN":
				$output .= "RECEIVED: ";
				break;

			case "OUT":
				$output .= "SENT: ";
				break;
		}

		$output .= minify($msg);

		file_put_contents("/var/log/varo-epp.log", $output."\n", FILE_APPEND);
	}
?>