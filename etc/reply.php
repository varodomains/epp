<?php
	switch ($command) {
		case "check":
			$chkData = [];
			foreach ($data as $key => $info) {
				$chkData[] = '<domain:cd><domain:name avail="'.(int)$info["available"].'">'.$info["domain"].'</domain:name></domain:cd>';
			}
			$variables["chkData"] = implode("", $chkData);
			break;

		case "info":
			$infData = [];
			foreach ($data as $key => $value) {
				switch ($key) {
					case "ns":
						$nameservers = "";
						foreach ($value as $k => $ns) {
							$nameservers .= '<domain:hostObj>'.$ns.'</domain:hostObj>';
						}
						$infData[] = '<domain:'.$key.'>'.$nameservers.'</domain:'.$key.'>'; 
						break;
					
					default:
						$infData[] = '<domain:'.$key.'>'.$value.'</domain:'.$key.'>'; 
						break;
				}
			}
			$variables["infData"] = implode("", $infData);
			break;

		case "create":
			$creData = [];
			foreach ($data as $key => $value) {
				$creData[] = '<domain:'.$key.'>'.$value.'</domain:'.$key.'>'; 
			}
			$variables["creData"] = implode("", $creData);
			break;

		case "renew":
			$renData = [];
			foreach ($data as $key => $value) {
				$renData[] = '<domain:'.$key.'>'.$value.'</domain:'.$key.'>'; 
			}
			$variables["renData"] = implode("", $renData);
			break;

		default:
			break;
	}
?>