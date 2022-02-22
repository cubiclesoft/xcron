<?php
	// Email notifier for xcron.
	// (C) 2022 CubicleSoft.  All Rights Reserved.

	class XCronNotifier_email
	{
		public function __construct()
		{
			$rootpath = str_replace("\\", "/", dirname(__FILE__));

			require_once $rootpath . "/../support/smtp.php";
		}

		public function CheckValid(&$notifyinfo)
		{
			if (!isset($notifyinfo["from"]))  return array("success" => false, "error" => "Missing 'from' address.", "errorcode" => "missing_from");
			if (!is_string($notifyinfo["from"]))  return array("success" => false, "error" => "Invalid 'from' address.  Expected a string.", "errorcode" => "invalid_from");
			if (!isset($notifyinfo["to"]))  return array("success" => false, "error" => "Missing 'to' address(es).", "errorcode" => "missing_to");
			if (!is_string($notifyinfo["to"]))  return array("success" => false, "error" => "Invalid 'to' address(es).  Expected a string.", "errorcode" => "invalid_to");
			if (isset($notifyinfo["prefix"]) && !is_string($notifyinfo["prefix"]))  return array("success" => false, "error" => "Invalid subject line 'prefix'.  Expected a string.", "errorcode" => "invalid_prefix");
			if (isset($notifyinfo["error_limit"]) && !is_int($notifyinfo["error_limit"]))  return array("success" => false, "error" => "Invalid 'error_limit'.  Expected an integer.", "errorcode" => "invalid_error_limit");

			if (!isset($notifyinfo["options"]))  $notifyinfo["options"] = array();
			if (!is_array($notifyinfo["options"]))  return array("success" => false, "error" => "Invalid options.  Expected an object.", "errorcode" => "invalid_options");

			$tempnames = array();
			$tempaddrs = array();
			if (!SMTP::EmailAddressesToNamesAndEmail($tempnames, $tempaddrs, $notifyinfo["from"], true, $notifyinfo["options"]))  return array("success" => false, "error" => "Invalid 'from' address.", "errorcode" => "invalid_from");

			$tempnames = array();
			$tempaddrs = array();
			if (!SMTP::EmailAddressesToNamesAndEmail($tempnames, $tempaddrs, $notifyinfo["to"], true, $notifyinfo["options"]))  return array("success" => false, "error" => "Invalid 'to' address(es).", "errorcode" => "invalid_to");

			// Normalize SMTP library options.
			$options = array();
			$warnings = array();

			if (isset($notifyinfo["options"]["usedns"]))
			{
				if (!is_bool($notifyinfo["options"]["usedns"]))  $warnings[] = "The 'usedns' option is not a boolean - ignoring.";
				else  $options["usedns"] = $notifyinfo["options"]["usedns"];
			}

			if (isset($notifyinfo["options"]["nameservers"]))
			{
				if (!is_array($notifyinfo["options"]["nameservers"]))  $warnings[] = "The 'nameservers' option is not an array - ignoring.";
				else
				{
					$options["nameservers"] = array();
					foreach ($notifyinfo["options"]["nameservers"] as $ns)
					{
						if (!is_string($ns))  $warnings[] = "A 'nameservers' entry is not a string - ignoring.";
						else if (trim($ns) !== "")  $options["nameservers"] = trim($ns);
					}
				}
			}

			if (isset($notifyinfo["options"]["usemail"]))
			{
				if (!is_bool($notifyinfo["options"]["usemail"]))  $warnings[] = "The 'usemail' option is not a boolean - ignoring.";
				else if ($notifyinfo["options"]["usemail"])  $options["usemail"] = true;
			}

			if (!isset($options["usemail"]))
			{
				if (isset($notifyinfo["options"]["server"]))
				{
					if (!is_string($notifyinfo["options"]["server"]))  $warnings[] = "The 'server' option is not a string - ignoring.";
					else  $options["server"] = $notifyinfo["options"]["server"];
				}

				if (isset($notifyinfo["options"]["port"]))
				{
					if (!is_numeric($notifyinfo["options"]["port"]) || (int)$notifyinfo["options"]["port"] < 1 || (int)$notifyinfo["options"]["port"] > 65535)  $warnings[] = "The 'port' option is not an integer between 1 and 65535 inclusive - ignoring.";
					else  $options["port"] = (int)$notifyinfo["options"]["port"];
				}

				if (isset($notifyinfo["options"]["secure"]))
				{
					if (!is_bool($notifyinfo["options"]["secure"]))  $warnings[] = "The 'secure' option is not a boolean - ignoring.";
					else  $options["secure"] = $notifyinfo["options"]["secure"];
				}

				if (isset($notifyinfo["options"]["username"]))
				{
					if (!is_string($notifyinfo["options"]["username"]))  $warnings[] = "The 'username' option is not a string - ignoring.";
					else  $options["username"] = $notifyinfo["options"]["username"];
				}

				if (isset($notifyinfo["options"]["password"]))
				{
					if (!is_string($notifyinfo["options"]["password"]))  $warnings[] = "The 'password' option is not a string - ignoring.";
					else  $options["password"] = $notifyinfo["options"]["password"];
				}

				if (isset($notifyinfo["options"]["sslopts"]))
				{
					if (!is_array($notifyinfo["options"]["sslopts"]))  $warnings[] = "The 'sslopts' option is not an object - ignoring.";
					else
					{
						$options["sslopts"] = SMTP::GetSafeSSLOpts();
						$options["sslopts"]["auto_peer_name"] = true;

						if (isset($notifyinfo["options"]["sslopts"]["cafile"]))
						{
							if (!is_string($notifyinfo["options"]["sslopts"]["cafile"]))  $warnings[] = "The 'cafile' option is not a string - ignoring.";
							else if (!is_file($notifyinfo["options"]["sslopts"]["cafile"]))  $warnings[] = "The file specified by 'cafile' does not exist - ignoring.";
							else
							{
								unset($options["sslopts"]["auto_cainfo"]);

								$options["sslopts"]["cafile"] = $notifyinfo["options"]["sslopts"]["cafile"];
							}
						}

						if (isset($notifyinfo["options"]["sslopts"]["local_cert"]))
						{
							if (!is_string($notifyinfo["options"]["sslopts"]["local_cert"]))  $warnings[] = "The 'local_cert' option is not a string - ignoring.";
							else if (!is_file($notifyinfo["options"]["sslopts"]["local_cert"]))  $warnings[] = "The file specified by 'local_cert' does not exist - ignoring.";
							else  $options["sslopts"]["local_cert"] = $notifyinfo["options"]["sslopts"]["local_cert"];
						}

						if (isset($notifyinfo["options"]["sslopts"]["local_pk"]))
						{
							if (!is_string($notifyinfo["options"]["sslopts"]["local_pk"]))  $warnings[] = "The 'local_pk' option is not a string - ignoring.";
							else if (!is_file($notifyinfo["options"]["sslopts"]["local_pk"]))  $warnings[] = "The file specified by 'local_pk' does not exist - ignoring.";
							else  $options["sslopts"]["local_pk"] = $notifyinfo["options"]["sslopts"]["local_pk"];
						}

						if (isset($notifyinfo["options"]["sslopts"]["passphrase"]))
						{
							if (!is_string($notifyinfo["options"]["sslopts"]["passphrase"]))  $warnings[] = "The 'passphrase' option is not a string - ignoring.";
							else  $options["sslopts"]["passphrase"] = $notifyinfo["options"]["sslopts"]["passphrase"];
						}
					}
				}

				if (isset($notifyinfo["options"]["sslhostname"]))
				{
					if (!is_string($notifyinfo["options"]["sslhostname"]))  $warnings[] = "The 'sslhostname' option is not a string - ignoring.";
					else  $options["sslhostname"] = $notifyinfo["options"]["sslhostname"];
				}

				if (isset($notifyinfo["options"]["hostname"]))
				{
					if (!is_string($notifyinfo["options"]["hostname"]))  $warnings[] = "The 'hostname' option is not a string - ignoring.";
					else  $options["hostname"] = $notifyinfo["options"]["hostname"];
				}
			}

			if (isset($notifyinfo["options"]["replyto"]))
			{
				if (!is_string($notifyinfo["options"]["replyto"]))  $warnings[] = "The 'replyto' option is not a string - ignoring.";
				else  $options["replytoaddr"] = $notifyinfo["options"]["replyto"];
			}

			if (isset($notifyinfo["options"]["cc"]))
			{
				if (!is_string($notifyinfo["options"]["cc"]))  $warnings[] = "The 'cc' option is not a string - ignoring.";
				else  $options["ccaddr"] = $notifyinfo["options"]["cc"];
			}

			if (isset($notifyinfo["options"]["bcc"]))
			{
				if (!is_string($notifyinfo["options"]["bcc"]))  $warnings[] = "The 'bcc' option is not a string - ignoring.";
				else  $options["bccaddr"] = $notifyinfo["options"]["bcc"];
			}

			if (isset($notifyinfo["options"]["useragent"]))
			{
				if (!is_string($notifyinfo["options"]["useragent"]))  $warnings[] = "The 'useragent' option is not a string - ignoring.";
				else  $options["headers"] = SMTP::GetUserAgent($notifyinfo["options"]["useragent"]);
			}

			$notifyinfo["options"] = $options;

			if (count($warnings))  return array("success" => true, "warning" => implode("  ", $warnings));

			return array("success" => true);
		}

		public function Notify($notifykey, &$notifyinfo, $numerrors, &$sinfo, $schedulekey, $name, $userdisp, $data)
		{
			if (isset($notifyinfo["error_limit"]) && $numerrors > $notifyinfo["error_limit"])  return array("success" => false, "error" => "Notification not sent due to exceeding error limit.", "errorcode" => "limit_exceeded");

			$subject = (isset($notifyinfo["prefix"]) ? $notifyinfo["prefix"] : "") . $userdisp . " | " . $name . " " . (isset($data["success"]) ? ($data["success"] ? "\xE2\x9C\x94" : "\xE2\x9D\x8C") : "(other/test)");

			$message = $userdisp . " | " . $name . "\n\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

			$smtpoptions = $notifyinfo["options"];
			$smtpoptions["textmessage"] = $message;

			$result = SMTP::SendEmail($notifyinfo["from"], $notifyinfo["to"], $subject, $smtpoptions);
			if (!$result["success"])  return $result;

			return array("success" => true);
		}
	}
?>