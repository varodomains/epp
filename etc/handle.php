<?php
	$response = false;

	// MAKE SURE YOU'RE LOGGED IN
	if (!@$GLOBALS["users"][$rID]["loggedIn"]) {
		switch ($command) {
			case "check":
			case "info":
			case "create":
			case "update":
			case "delete":
			case "renew":
				$response = reply($from, $data, "message", 2201, "You're not logged in.");
				goto end;
				break;

			case "logout":
				$response = reply($from, $data, "message", 2002, "You tried to logout, but never logged in.");
				goto end;
				break;
		}
	}

	switch ($command) {
		case "hello":
			$response = reply($from, $data, "greeting");
			break;

		case "login":
			$username = $data->clID;
			$password = $data->pw;
			$newPassword = @$data->newPW;

			if (!$username) {
				$response = reply($from, $data, "message", 2003, "Missing username.");
				goto end;
			}
			if (!$password) {
				$response = reply($from, $data, "message", 2003, "Missing password.");
				goto end;
			}

			$getUser = @sql("SELECT * FROM `registrars` WHERE `username` = ?", [$username])[0];
			if (!$getUser) {
				$response = reply($from, $data, "message", 2200, "Account doesn't exist.");
				goto end;
			}
			if (!$getUser["active"]) {
				$response = reply($from, $data, "message", 2200, "Your account is not active.");
				goto end;
			}

			if (password_verify($password, $getUser["password"])) {
				if (@$newPassword) {
					if (validPassword($newPassword)) {
						$hashed = password_hash($newPassword, PASSWORD_BCRYPT);
						sql("UPDATE `registrars` SET `password` = ? WHERE `id` = ?", [$hashed, $getUser["id"]]);
						$response = reply($from, $data, "message", 1000, "Login Success! Password changed.");
					}
					else {
						$response = reply($from, $data, "message", 2005, "Password must be a minimum of 8 characters with at least 1 lowercase letter, 1 uppercase letter, 1 number, and 1 symbol.");
					}
				}
				else {
					$response = reply($from, $data, "message", 1000, "Login Success!");
				}
				
				$GLOBALS["users"][$rID]["loggedIn"] = true;
				$GLOBALS["users"][$rID]["registrar"] = $getUser["username"];
			}
			else {
				$response = reply($from, $data, "message", 2200, "Login Failed!");
			}
			break;

		case "logout":
			$response = reply($from, $data, "message", 1500, "Logout Success!");
			break;

		case "check":
			$data->registerXPathNamespace("domain", "urn:ietf:params:xml:ns:domain-1.0");
			$domains = $data->xpath("//domain:name");

			if (!$domains) {
				$response = reply($from, $data, "message", 2003, "Missing domain/s.");
				goto end;
			}
			
			$chkData = [];
			foreach ($domains as $name) {
				$domain = (string)$name;
				$available = !domainExists($domain);
				$chkData[] = [
					"domain" => $domain,
					"available" => $available
				];
			}

			$response = reply($from, $data, "check", 1000, "Check Success!", $chkData);
			break;

		case "info":
			$data->registerXPathNamespace("domain", "urn:ietf:params:xml:ns:domain-1.0");
			$domain = (string)$data->xpath("//domain:name")[0];

			if (!$domain) {
				$response = reply($from, $data, "message", 2003, "Missing domain.");
				goto end;
			}
			
			$info = infoForSLD($domain);
			if (!$info) {
				$response = reply($from, $data, "message", 2303, "Domain doesn't exist.");
				goto end;
			}

			$ns = nsForDomain($domain);
			$infData = [
				"name" => $info["name"],
				"ns" => $ns,
				"exDate" => date("c", $info["expiration"]),
				"clID" => $info["registrar"]
			];

			$response = reply($from, $data, "info", 1000, "Info Success!", $infData);
			break;

		case "create":
			$data->registerXPathNamespace("domain", "urn:ietf:params:xml:ns:domain-1.0");
			$domain = (string)$data->xpath("//domain:name")[0];
			$years = (int)$data->xpath("//domain:period")[0];
			$ns = $data->xpath("//domain:ns/domain:hostObj");

			$nameservers = [];
			foreach ($ns as $server) {
				$nameservers[] = (string)$server;
			}

			$sld = sldForDomain($domain);
			$tld = tldForDomain($domain);
			$tldInfo = getStakedTLD($tld, true);

			if (!$tldInfo) {
				$response = reply($from, $data, "message", 2005, "Invalid TLD.");
				goto end;
			}

			$type = "sale";
			$price = @$tldInfo["price"];
			$expiration = strtotime("+".$years." years");
			$total = $price * $years;
			$fee = $total * ($GLOBALS["sldFee"] / 100);

			if (!$domain || strlen($domain) < 1) {
				$response = reply($from, $data, "message", 2003, "Missing domain.");
				goto end;
			}
			if (nameIsInvalid($sld)) {
				$response = reply($from, $data, "message", 2005, "Invalid domain.");
				goto end;
			}
			if (!$years) {
				$response = reply($from, $data, "message", 2003, "Missing years.");
				goto end;
			}
			if (!count($nameservers)) {
				$response = reply($from, $data, "message", 2003, "Missing nameservers.");
				goto end;
			}

			$domainAvailable = domainAvailable($domain);
			if (!$domainAvailable) {
				$response = reply($from, $data, "message", 2302, "Domain isn't available.");
				goto end;
			}

			$zone = registerSLD($tldInfo, $domain, @$user, $sld, $tld, $type, $expiration, $price, $total, $fee, $GLOBALS["users"][$rID]["registrar"]);
			if ($zone) {
				updateNS($zone, $nameservers);
			}

			$creData = [
				"name" => $domain,
				"exDate" => date("c", $expiration)
			];

			$response = reply($from, $data, "create", 1000, "Create Success!", $creData);
			break;

		case "update":
			$data->registerXPathNamespace("domain", "urn:ietf:params:xml:ns:domain-1.0");
			$domain = (string)$data->xpath("//domain:name")[0];
			$add = $data->xpath("//domain:add/domain:ns/domain:hostObj");
			$remove = $data->xpath("//domain:rem/domain:ns/domain:hostObj");

			if (!$domain || strlen($domain) < 1) {
				$response = reply($from, $data, "message", 2003, "Missing domain.");
				goto end;
			}

			$info = infoForSLD($domain);
			if (!$info) {
				$response = reply($from, $data, "message", 2303, "Domain doesn't exist.");
				goto end;
			}

			if (@$info["registrar"] !== @$GLOBALS["users"][$rID]["registrar"]) {
				$response = reply($from, $data, "message", 2202, "You don't have permission to modify this domain.");
				goto end;
			}

			$nameservers = nsForDomain($domain);
			$nameservers = array_diff($nameservers, $remove);
			
			foreach ($add as $ns) {
				$nameservers[] = (string)$ns;
			}

			updateNS($info["uuid"], $nameservers);

			$response = reply($from, $data, "check", 1000, "Update Success!");
			break;

		case "delete":
			$data->registerXPathNamespace("domain", "urn:ietf:params:xml:ns:domain-1.0");
			$domain = (string)$data->xpath("//domain:name")[0];

			if (!$domain || strlen($domain) < 1) {
				$response = reply($from, $data, "message", 2003, "Missing domain.");
				goto end;
			}

			$info = infoForSLD($domain);
			if (!$info) {
				$response = reply($from, $data, "message", 2303, "Domain doesn't exist.");
				goto end;
			}

			if (@$info["registrar"] !== @$GLOBALS["users"][$rID]["registrar"]) {
				$response = reply($from, $data, "message", 2202, "You don't have permission to modify this domain.");
				goto end;
			}

			updateNS($info["uuid"], []);
			sql("DELETE FROM `pdns`.`records` WHERE `domain_id` = ?", [$info["id"]]);
			sql("DELETE FROM `pdns`.`domains` WHERE `id` = ?", [$info["id"]]);

			$response = reply($from, $data, "message", 1000, "Delete Success!");
			break;

		case "renew":
			$data->registerXPathNamespace("domain", "urn:ietf:params:xml:ns:domain-1.0");
			$domain = (string)$data->xpath("//domain:name")[0];
			$years = (int)$data->xpath("//domain:period")[0];

			if (!$domain || strlen($domain) < 1) {
				$response = reply($from, $data, "message", 2003, "Missing domain.");
				goto end;
			}

			if (!$years) {
				$response = reply($from, $data, "message", 2003, "Missing years.");
				goto end;
			}

			$info = infoForSLD($domain);
			if (!$info) {
				$response = reply($from, $data, "message", 2303, "Domain doesn't exist.");
				goto end;
			}

			if (@$info["registrar"] !== @$GLOBALS["users"][$rID]["registrar"]) {
				$response = reply($from, $data, "message", 2202, "You don't have permission to modify this domain.");
				goto end;
			}

			$sld = sldForDomain($domain);
			$tld = tldForDomain($domain);
			$tldInfo = getStakedTLD($tld, true);
			$type = "renew";
			$price = @$tldInfo["price"];
			$total = $price * $years;
			$fee = $total * ($GLOBALS["sldFee"] / 100);
			$expiration = strtotime(date("c", $info["expiration"])." +".$years." years");

			sql("UPDATE `pdns`.`domains` SET `expiration` = ? WHERE `name` = ?", [$expiration, $domain]);
			sql("INSERT INTO `sales` (user, name, tld, type, price, fee, time, registrar) VALUES (?,?,?,?,?,?,?,?)", [@$user, $sld, $tld, $type, $total, $fee, time(), $$GLOBALS["users"][$rID]["registrar"]]);

			$renData = [
				"name" => $domain,
				"exDate" => date("c", $expiration)
			];

			$response = reply($from, $data, "renew", 1000, "Renew Success!", $renData);
			break;
		
		default:
			$response = reply($from, $data, "message", 2000, "Unknown Command.");
			break;
	}

	end:
	if ($response) {
		$from->send($response);
	}
?>