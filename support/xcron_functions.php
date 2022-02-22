<?php
	// xcron/xcrontab helper functions.
	// (C) 2022 CubicleSoft.  All Rights Reserved.

	class XCronHelper
	{
		protected static $user2uinfomap = array();

		protected static $scheduleparams = array(
			"tz" => true, "base_weekday" => true, "reload_at_start" => true, "reload_at_boot" => true, "schedule" => true,
			"allow_remote_time" => true, "output_file" => true, "alert_after" => true, "term_after" => true, "term_output" => true, "stderr_error" => true,
			"notify" => true, "user" => true, "win_elevated" => true, "dir" => true, "cmds" => true, "env" => true,
			"random_delay" => true, "min_uptime" => true, "min_battery" => true, "max_cpu" => true, "max_ram" => true,
			"depends_on" => true, "retry_freq" => true, "password" => true, "max_queue" => true, "max_running" => true
		);

		protected static $sensitiveparams = array(
			"output_file" => true, "dir" => true, "cmds" => true, "env" => true, "password" => true
		);

		public static $debug, $os, $windows, $rootpath, $env, $logpath, $em;

		public static function Init()
		{
			self::$debug = false;

			self::$os = php_uname("s");
			self::$windows = (strtoupper(substr(self::$os, 0, 3)) == "WIN");

			self::$rootpath = str_replace("\\", "/", dirname(__FILE__));

			require_once self::$rootpath . "/process_helper.php";

			self::$env = ProcessHelper::GetCleanEnvironment();

			if (self::$windows)
			{
				$dir = str_replace("\\", "/", sys_get_temp_dir());
				if (substr($dir, -1) !== "/")  $dir .= "/";
			}
			else
			{
				$dir = "/var/log/";
			}

			self::$logpath = $dir . "xcron";
		}

		// Server extension event registration helper function.
		public static function EventRegister($eventname, $objorfuncname, $funcname = false)
		{
			return self::$em->Register($eventname, $objorfuncname, $funcname);
		}

		// Similar to CLI::DisplayError() but with syslog() integration for xcron.
		// Windows is always LOG_NOTICE due to issues with displaying LOG_ERR messages.
		public static function DisplayMessageAndLog($loglevel, $msg, $result = false, $exit = false)
		{
			CLI::LogMessage($msg);
			syslog((self::$windows ? LOG_NOTICE : $loglevel), $msg);

			if ($result !== false && is_array($result) && isset($result["error"]) && isset($result["errorcode"]))
			{
				CLI::LogMessage("[Error] " . $result["error"] . " (" . $result["errorcode"] . ")", (isset($result["info"]) ? $result["info"] : null));
				syslog((self::$windows ? LOG_NOTICE : $loglevel), "[Error] " . $result["error"] . " (" . $result["errorcode"] . ")");
			}

			if ($exit)  exit();

			CLI::ResetLogMessages();
		}

		public static function GetCurrentUserInfo()
		{
			// Determine access level.
			$allusers = false;
			if (self::$windows)
			{
				$cmd = escapeshellarg(self::$rootpath . "/windows/gettokeninformation-win.exe") . " /c=TokenUser /c=TokenGroups /c=TokenOwner /c=TokenPrimaryGroup /c=TokenElevation /c=TokenIntegrityLevel";

				$result = ProcessHelper::StartProcess($cmd);
				if (!$result["success"])  return array("success" => false, "error" => "Unable to start process.", "errorcode" => "processhelper_startprocess_failed", "info" => $result);
				if (self::$debug)  echo $result["info"]["cmd"] . "\n";

				$result2 = ProcessHelper::Wait($result["proc"], $result["pipes"]);
				$currtoken = @json_decode($result2["stdout"], true);
				if (!is_array($currtoken) || !$currtoken["success"])  return array("success" => false, "error" => "Unable to retrieve process token.", "errorcode" => "invalid_token_response", "info" => $currtoken);

				$uid = $currtoken["TokenUser"]["info"]["sid"];

				$allusers = ($currtoken["TokenElevation"]["success"] && $currtoken["TokenElevation"]["info"] > 0);

				return array("success" => true, "windows" => true, "uid" => $uid, "name" => $currtoken["TokenUser"]["info"]["domain"] . "\\" . $currtoken["TokenUser"]["info"]["account"], "currtoken" => $currtoken, "allusers" => $allusers);
			}
			else
			{
				if (!function_exists("posix_geteuid"))  return array("success" => false, "error" => "Unable to determine the effective UID.", "errorcode" => "missing_posix_geteuid", "info" => false);
				if (!function_exists("posix_getegid"))  return array("success" => false, "error" => "Unable to determine the effective GID.", "errorcode" => "missing_posix_getegid", "info" => false);

				$uid = posix_geteuid();
				$gid = posix_getegid();

				$allusers = ($uid === 0);

				$uinfo = self::GetUserInfo($uid);

				return array("success" => true, "windows" => false, "uid" => $uid, "gid" => $gid, "name" => ($uinfo["success"] ? $uinfo["name"] : false), "allusers" => $allusers);
			}
		}

		public static function ResetUserInfoCache()
		{
			self::$user2uinfomap = array();
		}

		public static function GetUserInfo($user)
		{
			if (count(self::$user2uinfomap) > 1000)  self::ResetUserInfoCache();

			if (!isset(self::$user2uinfomap[$user]))
			{
				if (self::$windows)
				{
					$cmd = escapeshellarg(self::$rootpath . "/windows/getsidinfo-win.exe") . " " . escapeshellarg($user);

					$result = ProcessHelper::StartProcess($cmd);
					if (!$result["success"])  self::$user2uinfomap[$user] = array("success" => false, "error" => "Unable to start process.", "errorcode" => "processhelper_startprocess_failed", "info" => $result);
					else
					{
						if (self::$debug)  echo $result["info"]["cmd"] . "\n";

						$result2 = ProcessHelper::Wait($result["proc"], $result["pipes"]);
						$sidinfo = json_decode($result2["stdout"], true);
						if (!is_array($sidinfo) || !isset($sidinfo[$user]))  self::$user2uinfomap[$user] = array("success" => false, "error" => "Unable to retrieve user information.", "errorcode" => "invalid_info_response", "info" => $sidinfo);
						else  self::$user2uinfomap[$user] = $sidinfo[$user];
					}
				}
				else
				{
					$uinfo = posix_getpwnam($user);
					if ($uinfo === false)  $uinfo = posix_getpwuid($user);

					if ($uinfo === false)  self::$user2uinfomap[$user] = array("success" => false, "error" => "Unable to retrieve user information.", "errorcode" => "getpwnam_getpwuid_failed");
					else
					{
						$uinfo["success"] = true;

						self::$user2uinfomap[$user] = $uinfo;
					}
				}
			}

			return self::$user2uinfomap[$user];
		}

		public static function GetXCrontabPathFile($user, $elevated)
		{
			$uinfo = self::GetUserInfo($user);
			if (!$uinfo["success"])  return $uinfo;

			if (self::$windows)
			{
				$user = $uinfo["sid"];
				$useralt = $uinfo["domain"] . "\\" . $uinfo["account"];

				if (isset($uinfo["net_info"]) && $uinfo["net_info"]["profile"] != "")  $basepath = $uinfo["net_info"]["profile"];
				else if (isset($uinfo["reg_profile_path"]))  $basepath = $uinfo["reg_profile_path"];
				else  return array("success" => false, "error" => "User profile path is not able to be determined.", "errorcode" => "user_profile_path_not_found", "info" => $uinfo);

				$path = $basepath . "\\AppData\\Local\\xcron";
				$filename = $path . "\\xcrontab" . ($elevated ? "_elevated" : "") . ".txt";
			}
			else
			{
				$user = $uinfo["name"];
				$useralt = $uinfo["uid"];

				$path = "/var/spool/cron/xcrontabs";
				$filename = "/var/spool/cron/xcrontabs/" . $user;
			}

			return array("success" => true, "path" => $path, "filename" => $filename, "user" => $user, "useralt" => $useralt);
		}

		public static function GetBootTimestamp()
		{
			if (self::$windows)
			{
				$wmicexe = ProcessHelper::FindExecutable("wmic.exe");
				if ($wmicexe === false)  return array("success" => false, "error" => "Unable to locate wmic.exe.", "errorcode" => "missing_exe");

				$cmd = escapeshellarg($wmicexe) .  " path Win32_OperatingSystem get LastBootUpTime";

				$result = ProcessHelper::StartProcess($cmd);
				if (!$result["success"])  return array("success" => false, "error" => "Unable to start process.", "errorcode" => "processhelper_startprocess_failed", "info" => $result);
				if (self::$debug)  echo $result["info"]["cmd"] . "\n";

				$result2 = ProcessHelper::Wait($result["proc"], $result["pipes"]);
				$lines = explode("\n", $result2["stdout"]);

				if (strtolower(trim($lines[0])) !== "lastbootuptime")  return array("success" => false, "error" => "WMIC responded with an unexpected response.", "errorcode" => "unexpected_response", "info" => $result2);

				$ts = trim($lines[1]);
				$pos = strpos($ts, ".");
				if ($pos !== false)  $ts = substr($ts, 0, $pos);
				$y = strlen($ts);
				if ($y < 14)  return array("success" => false, "error" => "WMIC responded with an invalid/unexpected timestamp format.", "errorcode" => "unexpected_response", "info" => $result2);
				$ts = mktime(substr($ts, $y - 6, 2), substr($ts, $y - 4, 2), substr($ts, $y - 2, 2), substr($ts, $y - 10, 2), substr($ts, $y - 8, 2), substr($ts, 0, $y - 10));

				return array("success" => true, "ts" => $ts);
			}
			else
			{
				$uptime = ProcessHelper::FindExecutable("uptime", "/usr/bin");
				if ($uptime !== false)
				{
					$cmd = escapeshellarg($uptime) .  " -s";

					$result = ProcessHelper::StartProcess($cmd);
					if ($result["success"])
					{
						if (self::$debug)  echo $result["info"]["cmd"] . "\n";

						$result2 = ProcessHelper::Wait($result["proc"], $result["pipes"]);
						$lines = explode("\n", $result2["stdout"]);

						if (preg_match('/^[0-9 :-]*$/', $lines[0]))
						{
							$ts = strtotime($lines[0]);

							if ($ts > 0)  return array("success" => true, "ts" => $ts);
						}
					}
				}

				if (file_exists("/proc"))  return array("success" => true, "ts" => filectime("/proc"));
				if (file_exists("/dev"))  return array("success" => true, "ts" => filectime("/dev"));

				return array("success" => false, "error" => "Last boot time is unavailable.", "errorcode" => "boot_time_not_available");
			}
		}

		// Some functions for parsing Unix utility output.  Similarish to awk.
		public static function ExtractUnixProcLineHeaders($str)
		{
			$str = rtrim($str);

			$result = array();

			$lastkey = false;
			$x = 0;
			$y = strlen($str);
			while ($x < $y)
			{
				$startx = ($x ? $x + 1 : $x);

				for (; $x < $y && ($str[$x] === " " || $str[$x] === "\t"); $x++);

				if ($lastkey !== false)  $result[$lastkey][3] = ($x < $y ? $x - 1 : $y);

				if ($x < $y)
				{
					for ($x2 = $x; $x2 < $y && $str[$x2] !== " " && $str[$x2] !== "\t"; $x2++);

					$lastkey = strtoupper(substr($str, $x, $x2 - $x));
					$result[$lastkey] = array($startx, $x, $x2);

					$x = $x2;
				}
			}

			if ($lastkey !== false)  $result[$lastkey][3] = $y;

			return $result;
		}

		public static function ExtractUnixProcLineValue(&$headermap, $key, $boundleft, $boundright, &$str)
		{
			if (!isset($headermap[$key]))  return false;

			$pos = ($boundleft ? $headermap[$key][1] : $headermap[$key][0]);
			$pos2 = ($boundright ? $headermap[$key][2] : $headermap[$key][3]);

			return trim(substr($str, $pos, $pos2 - $pos));
		}

		public static function GetUnixProcLineStartPos(&$headermap, $key, $boundleft)
		{
			if (!isset($headermap[$key]))  return false;

			$pos = ($boundleft ? $headermap[$key][1] : $headermap[$key][0]);

			return $pos;
		}

		public static function GetUnixProcLineEndPos(&$headermap, $key, $boundright)
		{
			if (!isset($headermap[$key]))  return false;

			$pos = ($boundright ? $headermap[$key][2] : $headermap[$key][3]);

			return $pos;
		}

		public static function GetClientTCPUser($localipaddr, $localport, $remoteipaddr, $remoteport)
		{
			if (self::$windows)
			{
				// Get the PID associated with the local TCP/IP socket.
				$cmd = escapeshellarg(self::$rootpath . "/windows/getiptables-win.exe") . " /tcponly " . escapeshellarg("/localip=" . $localipaddr) . " " . escapeshellarg("/localport=" . $localport) . " " . escapeshellarg("/remoteip=" . $remoteipaddr) . " " . escapeshellarg("/remoteport=" . $remoteport);

				$result = ProcessHelper::StartProcess($cmd);
				if (!$result["success"])  return array("success" => false, "error" => "Unable to start process.", "errorcode" => "processhelper_startprocess_failed", "info" => $result);
				if (self::$debug)  echo $result["info"]["cmd"] . "\n";

				$result2 = ProcessHelper::Wait($result["proc"], $result["pipes"]);
				$ipinfo = @json_decode($result2["stdout"], true);
				if (!is_array($ipinfo) || !$ipinfo["success"])  return array("success" => false, "error" => "Unable to retrieve IP table information.", "errorcode" => "invalid_ip_info_response", "info" => $ipinfo);

				if (!isset($ipinfo["tcp4"]) || !$ipinfo["tcp4"]["success"] || !count($ipinfo["tcp4"]["info"]) || !isset($ipinfo["tcp4"]["info"][0]["pid"]))  return array("success" => false, "error" => "Unable to retrieve TCP/IP table information.", "errorcode" => "missing_tcp_info", "info" => $ipinfo);

				// Get the process token.
				$cmd = escapeshellarg(self::$rootpath . "/windows/gettokeninformation-win.exe") . " /c=TokenUser /c=TokenGroups /c=TokenOwner /c=TokenPrimaryGroup /c=TokenElevation /c=TokenIntegrityLevel /pid=" . $ipinfo["tcp4"]["info"][0]["pid"];

				$result = ProcessHelper::StartProcess($cmd);
				if (!$result["success"])  return array("success" => false, "error" => "Unable to start process.", "errorcode" => "processhelper_startprocess_failed", "info" => $result);
				if (self::$debug)  echo $result["info"]["cmd"] . "\n";

				$result2 = ProcessHelper::Wait($result["proc"], $result["pipes"]);
				$currtoken = @json_decode($result2["stdout"], true);
				if (!is_array($currtoken) || !$currtoken["success"])  return array("success" => false, "error" => "Unable to retrieve process token.", "errorcode" => "invalid_token_response", "info" => $currtoken);

				$uid = $currtoken["TokenUser"]["info"]["sid"];

				$allusers = ($currtoken["TokenElevation"]["success"] && $currtoken["TokenElevation"]["info"] > 0);

				return array("success" => true, "windows" => true, "uid" => $uid, "currtoken" => $currtoken, "allusers" => $allusers);
			}
			else
			{
				$result = false;
				$handler = false;

				$templocaladdr = $localipaddr . ":" . $localport;
				$tempremoteaddr = $remoteipaddr . ":" . $remoteport;

				// Linux/FreeBSD (via procfs).
				// This is currently the most performant method of getting UID from the remote side of the TCP/IP socket.
				if (file_exists("/proc/net/tcp"))
				{
					$fp = fopen("/proc/net/tcp", "rb");
					if ($fp !== false)
					{
						$line = fgets($fp);
						$headermap = self::ExtractUnixProcLineHeaders($line);
						while (($line = fgets($fp)) !== false)
						{
							$line = rtrim($line);
							if ($line !== "")
							{
								$addr = self::ExtractUnixProcLineValue($headermap, "LOCAL_ADDRESS", true, false, $line);
								$templocaladdr2 = hexdec(substr($addr, 6, 2)) . "." . hexdec(substr($addr, 4, 2)) . "." . hexdec(substr($addr, 2, 2)) . "." . hexdec(substr($addr, 0, 2)) . ":" .hexdec(substr($addr, 9, 4));

								if ($templocaladdr2 === $templocaladdr)
								{
									$addr = self::ExtractUnixProcLineValue($headermap, "REM_ADDRESS", true, false, $line);
									$tempremoteaddr2 = hexdec(substr($addr, 6, 2)) . "." . hexdec(substr($addr, 4, 2)) . "." . hexdec(substr($addr, 2, 2)) . "." . hexdec(substr($addr, 0, 2)) . ":" .hexdec(substr($addr, 9, 4));

									if ($tempremoteaddr2 === $tempremoteaddr)
									{
										$uid = (int)self::ExtractUnixProcLineValue($headermap, "UID", false, true, $line);

										$uinfo = self::GetUserInfo($uid);
										if ($uinfo["success"])
										{
//											echo $templocaladdr . " => " . $tempremoteaddr . " | " . $uid . "\n";

											$allusers = ($uid === 0);

											$result = array("success" => true, "windows" => false, "uid" => $uid, "name" => ($uinfo["success"] ? $uinfo["name"] : false), "allusers" => $allusers);

											break;
										}
									}
								}
							}
						}

						fclose($fp);

						$handler = true;
					}
				}

				// Mac OSX/various *NIX.
				if ($result === false)
				{
					$cmd = ProcessHelper::FindExecutable("lsof", "/usr/bin");
					if ($cmd !== false)
					{
						$cmd = escapeshellarg($cmd) . " -n -P -i " . escapeshellarg("4TCP@" . $templocaladdr);

						$result = ProcessHelper::StartProcess($cmd);
						if ($result["success"])
						{
							if (self::$debug)  echo $result["info"]["cmd"] . "\n";

							$result2 = ProcessHelper::Wait($result["proc"], $result["pipes"]);
							$lines = explode("\n", $result2["stdout"]);
							$line = array_shift($lines);
							$headermap = self::ExtractUnixProcLineHeaders($line);
							$y = count($lines);
							for ($x = 0; $x < $y; $x++)
							{
								$line = rtrim($lines[$x]);
								if ($line !== "")
								{
									$pos = self::GetUnixProcLineStartPos($headermap, "NAME", true);
									$name = substr($line, $pos);

									if ($name === $templocaladdr . "->" . $tempremoteaddr . " (ESTABLISHED)")
									{
										$user = self::ExtractUnixProcLineValue($headermap, "USER", false, true, $line);

										$uinfo = self::GetUserInfo($user);
										if ($uinfo["success"])
										{
											$uid = $uinfo["uid"];

//											echo $name . " | " . $uid . "\n";

											$allusers = ($uid === 0);

											$result = array("success" => true, "windows" => false, "uid" => $uid, "name" => ($uinfo["success"] ? $uinfo["name"] : false), "allusers" => $allusers);

											break;
										}
									}
								}
							}

							$handler = true;
						}
					}
				}

				// BSD.
				if ($result === false)
				{
					$cmd = ProcessHelper::FindExecutable("fstat", "/usr/bin");
					if ($cmd !== false)
					{
						$cmd = escapeshellarg($cmd) . " -s";

						$result = ProcessHelper::StartProcess($cmd);
						if ($result["success"])
						{
							if (self::$debug)  echo $result["info"]["cmd"] . "\n";

							$result2 = ProcessHelper::Wait($result["proc"], $result["pipes"]);
							$lines = explode("\n", $result2["stdout"]);
							$line = array_shift($lines);
							$headermap = self::ExtractUnixProcLineHeaders($line);
							$basepos = self::GetUnixProcLineEndPos($headermap, "FD", true);
							$y = count($lines);
							for ($x = 0; $x < $y; $x++)
							{
								$line = rtrim($lines[$x]);
								if ($line !== "")
								{
									$pos = stripos($line, "* internet stream tcp ", $basepos);
									if ($pos !== false)
									{
										$pos = strpos($line, " ", $pos + 24);
										if ($pos !== false)
										{
											$ipaddrs = trim(substr($line, $pos + 1));

											if ($ipaddrs === $templocaladdr . " <-> " . $tempremoteaddr)
											{
												$user = self::ExtractUnixProcLineValue($headermap, "USER", true, false, $line);

												$uinfo = self::GetUserInfo($user);
												if ($uinfo["success"])
												{
													$uid = $uinfo["uid"];

//													echo $ipaddrs . " | " . $uid . "\n";

													$allusers = ($uid === 0);

													$result = array("success" => true, "windows" => false, "uid" => $uid, "name" => ($uinfo["success"] ? $uinfo["name"] : false), "allusers" => $allusers);

													break;
												}
											}
										}
									}
								}
							}

							$handler = true;
						}
					}
				}

				if ($result === false)
				{
					if ($handler)  $result = array("success" => false, "error" => "Unable to match incoming connection with a user ID.", "errorcode" => "match_not_found");
					else  $result = array("success" => false, "error" => "No handler is available on the system to retrieve user information.", "errorcode" => "handler_not_found");
				}

				return $result;
			}
		}

		public static function ConvertStrToSeconds($str)
		{
			$str = strtolower(trim($str));

			switch (substr($str, -1))
			{
				case "d":  return (int)((double)trim(substr($str, 0, -1)) * 86400);
				case "h":  return (int)((double)trim(substr($str, 0, -1)) * 3600);
				case "m":  return (int)((double)trim(substr($str, 0, -1)) * 60);
				case "s":  return (int)trim(substr($str, 0, -1));
			}

			$str = explode(":", $str);

			$result = (int)trim(array_pop($str));

			if (count($str))  $result += (int)((double)trim(array_pop($str)) * 60);
			if (count($str))  $result += (int)((double)trim(array_pop($str)) * 3600);
			if (count($str))  $result += (int)((double)trim(array_pop($str)) * 86400);

			return $result;
		}

		// Makes a CalendarEvent object from a cron schedule.
		public static function MakeCalendarEvent($schedule, $ts)
		{
			if (!is_string($schedule["schedule"]) && !is_array($schedule["schedule"]))  return array("success" => false, "error" => "Expected 'schedule' to be a string or array to create a calendar event object.", "errorcode" => "invalid_schedule");

			$opts = array();
			if (isset($schedule["tz"]))  $opts["tz"] = $schedule["tz"];
			if (isset($schedule["base_weekday"]))  $opts["startweekday"] = trim($schedule["base_weekday"]);

			if (is_string($schedule["schedule"]) && substr($schedule["schedule"], 0, 5) !== "cron ")  $schedule["schedule"] = "cron " . $schedule["schedule"];

			$calevent = new CalendarEvent($opts);
			$calevent->SetTime($ts);
			$result = $calevent->AddSchedule($schedule["schedule"]);
			if (!$result["success"])
			{
				if (!isset($result["errorcode"]))  $result["errorcode"] = "invalid_schedule";

				return $result;
			}

			$result["calevent"] = $calevent;

			return $result;
		}

		// Make adjustments to various parts of a schedule to simplify things later on.
		public static function ValidateSchedule(&$warnings, &$scheduleinfo, $name, &$schedule, $allusers)
		{
			if ($name === "")  return array("success" => false, "error" => "Empty schedule name encountered.  Possibly invalid UTF-8.", "errorcode" => "empty_name");
			if (!isset($schedule["schedule"]))  return array("success" => false, "error" => "No 'schedule' defined for '" . $name . "'.", "errorcode" => "missing_schedule");
			if (!isset($schedule["cmd"]) && !isset($schedule["cmds"]))  return array("success" => false, "error" => "No 'cmd' or 'cmds' defined for schedule '" . $name . "'.", "errorcode" => "missing_cmd_cmds");
			if (isset($schedule["cmd"]) && !is_string($schedule["cmd"]))  return array("success" => false, "error" => "Expected 'cmd' to be a string for schedule '" . $name . "'.", "errorcode" => "invalid_cmd");
			if (isset($schedule["cmds"]) && !is_array($schedule["cmds"]))  return array("success" => false, "error" => "Expected 'cmds' to be an array of strings for schedule '" . $name . "'.", "errorcode" => "invalid_cmds");

			if (isset($schedule["tz"]))
			{
				try
				{
					new DateTimeZone($schedule["tz"]);
				}
				catch (Exception $e)
				{
					return array("success" => false, "error" => "Invalid 'tz' for schedule '" . $name . "'.", "errorcode" => "invalid_tz");
				}
			}

			if (isset($schedule["base_weekday"]) && !isset(CalendarEvent::$allweekdays[strtolower(substr(trim($schedule["base_weekday"]), 0, 3))]))  return array("success" => false, "error" => "Invalid 'base_weekday' for schedule '" . $name . "'.", "errorcode" => "invalid_base_weekday");
			if (isset($schedule["reload_at_start"]) && !is_bool($schedule["reload_at_start"]))  return array("success" => false, "error" => "Expected 'reload_at_start' to be a boolean for schedule '" . $name . "'.", "errorcode" => "invalid_reload_at_start");
			if (isset($schedule["reload_at_boot"]) && !is_bool($schedule["reload_at_boot"]))  return array("success" => false, "error" => "Expected 'reload_at_boot' to be a boolean for schedule '" . $name . "'.", "errorcode" => "invalid_reload_at_boot");

			if (is_string($schedule["schedule"]) || is_array($schedule["schedule"]))
			{
				$result = self::MakeCalendarEvent($schedule, time());
				if (!$result["success"])  return array("success" => false, "error" => "Invalid 'schedule' (" . $schedule["schedule"] . ") for schedule '" . $name . "'.  " . $result["error"] . " (" . $result["errorcode"] . ")", "errorcode" => "make_calendar_event_failed");
			}
			else if (!is_bool($schedule["schedule"]) && !is_int($schedule["schedule"]))
			{
				return array("success" => false, "error" => "Expected 'schedule' to be a string, array, integer, or boolean for schedule '" . $name . "'.", "errorcode" => "invalid_schedule");
			}

			if (isset($schedule["allow_remote_time"]) && !is_bool($schedule["allow_remote_time"]))  return array("success" => false, "error" => "Expected 'allow_remote_time' to be a boolean for schedule '" . $name . "'.", "errorcode" => "invalid_allow_remote_time");

			if (isset($schedule["output_file"]))
			{
				if (!$allusers)  return array("success" => false, "error" => "Must be " . (self::$windows ? "elevated" : "root") . " to use the 'output_file' option.  Error in schedule '" . $name . "'.", "errorcode" => "permission_denied");

				if (!is_string($schedule["output_file"]))  return array("success" => false, "error" => "Expected 'output_file' to be a string for schedule '" . $name . "'.", "errorcode" => "invalid_output_file");
			}

			if (isset($schedule["alert_after"]))
			{
				if (!is_string($schedule["alert_after"]) && !is_int($schedule["alert_after"]))  return array("success" => false, "error" => "Expected 'alert_after' to be a string or integer for schedule '" . $name . "'.", "errorcode" => "invalid_output_file");

				if (is_string($schedule["alert_after"]))  $schedule["alert_after"] = self::ConvertStrToSeconds($schedule["alert_after"]);

				if ($schedule["alert_after"] < 1)  $warnings[] = "The 'alert_after' value is less than one second for schedule '" . $name . "'.";
			}

			if (isset($schedule["term_after"]))
			{
				if (!is_string($schedule["term_after"]) && !is_int($schedule["term_after"]))  return array("success" => false, "error" => "Expected 'term_after' to be a string or integer for schedule '" . $name . "'.", "errorcode" => "invalid_term_after");

				if (is_string($schedule["term_after"]))  $schedule["term_after"] = self::ConvertStrToSeconds($schedule["term_after"]);

				if ($schedule["term_after"] < 1)  return array("success" => false, "error" => "The 'term_after' value is less than one second for schedule '" . $name . "'.", "errorcode" => "invalid_term_after");
			}

			if (isset($schedule["term_output"]))
			{
				if (!is_string($schedule["term_output"]) && !is_int($schedule["term_output"]))  return array("success" => false, "error" => "Expected 'term_output' to be a string or integer for schedule '" . $name . "'.", "errorcode" => "invalid_term_output");

				if (is_string($schedule["term_output"]))  $schedule["term_output"] = Str::ConvertUserStrToBytes($schedule["term_output"]);

				if ($schedule["term_output"] < 0)  $warnings[] = "The 'term_output' value is less than zero for schedule '" . $name . "'.";
			}

			if (isset($schedule["stderr_error"]) && !is_bool($schedule["stderr_error"]))  return array("success" => false, "error" => "Expected 'stderr_error' to be a boolean for schedule '" . $name . "'.", "errorcode" => "invalid_stderr_error");

			if (isset($schedule["notify"]))
			{
				if (is_string($schedule["notify"]))  $schedule["notify"] = array($schedule["notify"]);

				if (!is_array($schedule["notify"]))  return array("success" => false, "error" => "Expected 'notify' to be an array for schedule '" . $name . "'.", "errorcode" => "invalid_notify");

				$notify = array();
				foreach ($schedule["notify"] as $val)
				{
					if (!isset($scheduleinfo["notifiers"][$val]) && !isset($scheduleinfo["notifiergroups"][$val]))  $warnings[] = "The notifier '" . $val . "' for schedule '" . $name . "' does not exist.";
					else  $notify[] = $val;
				}

				$schedule["notify"] = $notify;
			}

			if (isset($schedule["user"]))
			{
				if (!$allusers)  return array("success" => false, "error" => "Must be " . (self::$windows ? "elevated" : "root") . " to run schedules as another user.  Error in schedule '" . $name . "'.", "errorcode" => "permission_denied");

				if (self::$windows)
				{
					if (!is_string($schedule["user"]))  return array("success" => false, "error" => "Expected 'user' to be a string for schedule '" . $name . "'.", "errorcode" => "invalid_user");

					if (isset($schedule["win_elevated"]) && !is_bool($schedule["win_elevated"]))  return array("success" => false, "error" => "Expected 'win_elevated' to be a boolean for schedule '" . $name . "'.", "errorcode" => "invalid_win_elevated");
				}
				else
				{
					if (!is_string($schedule["user"]) && !is_int($schedule["user"]))  return array("success" => false, "error" => "Expected 'user' to be a string or integer for schedule '" . $name . "'.", "errorcode" => "invalid_user");
				}

				$result = self::GetUserInfo($schedule["user"]);
				if (!$result["success"])  return array("success" => false, "error" => "Invalid 'user' for schedule '" . $name . "'.  " . $result["error"] . " (" . $result["errorcode"] . ")", "errorcode" => "invalid_user");
			}

			if (isset($schedule["dir"]))
			{
				if (!is_string($schedule["dir"]))  return array("success" => false, "error" => "Expected 'dir' to be a string for schedule '" . $name . "'.", "errorcode" => "invalid_dir");
				if (!is_dir($schedule["dir"]))  return array("success" => false, "error" => "The starting 'dir' for schedule '" . $name . "' does not exist.", "errorcode" => "dir_missing");
			}

			if (isset($schedule["cmd"]))
			{
				$schedule["cmds"] = array($schedule["cmd"]);

				unset($schedule["cmd"]);
			}

			$cmds = array();
			foreach ($schedule["cmds"] as $cmd)
			{
				if (!is_string($cmd))  $warnings[] = "Expected a string for each command for schedule '" . $name . "'.";
				else  $cmds[] = $cmd;
			}

			$schedule["cmds"] = $cmds;

			if (!count($schedule["cmds"]))  return array("success" => false, "error" => "Expected at least one command in 'cmds' for schedule '" . $name . "'.", "errorcode" => "invalid_cmds");

			if (isset($schedule["env"]))
			{
				if (!is_array($schedule["env"]))  return array("success" => false, "error" => "Expected 'env' to be an array for schedule '" . $name . "'.", "errorcode" => "invalid_env");

				$env = array();
				foreach ($schedule["env"] as $key => $val)
				{
					if (!is_string($val))  $warnings[] = "Expected value for '" . $key . "' in 'env' to be a string for schedule '" . $name . "'.";
					else  $env[$key] = $val;
				}

				$schedule["env"] = $env;
			}

			if (isset($schedule["random_delay"]))
			{
				if (!is_string($schedule["random_delay"]) && !is_int($schedule["random_delay"]))  return array("success" => false, "error" => "Expected 'random_delay' to be a string or integer for schedule '" . $name . "'.", "errorcode" => "invalid_random_delay");

				if (is_string($schedule["random_delay"]))  $schedule["random_delay"] = self::ConvertStrToSeconds($schedule["random_delay"]);

				if ($schedule["random_delay"] < 0)
				{
					$warnings[] = "The 'random_delay' value is less than zero for schedule '" . $name . "'.";

					unset($schedule["random_delay"]);
				}
			}

			if (isset($schedule["min_uptime"]))
			{
				if (!is_string($schedule["min_uptime"]) && !is_int($schedule["min_uptime"]))  return array("success" => false, "error" => "Expected 'min_uptime' to be a string or integer for schedule '" . $name . "'.", "errorcode" => "invalid_min_uptime");

				if (is_string($schedule["min_uptime"]))  $schedule["min_uptime"] = self::ConvertStrToSeconds($schedule["min_uptime"]);

				if ($schedule["min_uptime"] < 0)  $warnings[] = "The 'min_uptime' value is less than zero for schedule '" . $name . "'.";

				if (!isset($schedule["reload_at_boot"]))  $schedule["reload_at_boot"] = true;
			}

			if (isset($schedule["min_battery"]) && (!is_int($schedule["min_battery"]) || $schedule["min_battery"] < 0 || $schedule["min_battery"] > 100))  return array("success" => false, "error" => "Expected 'min_battery' to be an integer between 0 and 100 for schedule '" . $name . "'.", "errorcode" => "invalid_min_battery");
			if (isset($schedule["max_cpu"]) && (!is_int($schedule["max_cpu"]) || $schedule["max_cpu"] < 0 || $schedule["max_cpu"] > 100))  return array("success" => false, "error" => "Expected 'max_cpu' to be an integer between 0 and 100 for schedule '" . $name . "'.", "errorcode" => "invalid_max_cpu");

			if (isset($schedule["max_ram"]))
			{
				if (!is_string($schedule["max_ram"]) && !is_int($schedule["max_ram"]))  return array("success" => false, "error" => "Expected 'max_ram' to be a string or integer for schedule '" . $name . "'.", "errorcode" => "invalid_max_ram");

				if (is_string($schedule["max_ram"]))  $schedule["max_ram"] = Str::ConvertUserStrToBytes($schedule["max_ram"]);

				if ($schedule["max_ram"] < 0)  $warnings[] = "The 'max_ram' value is less than zero for schedule '" . $name . "'.";
			}

			if (isset($schedule["depends_on"]))
			{
				if (is_string($schedule["depends_on"]))  $schedule["depends_on"] = array($schedule["depends_on"]);

				foreach ($schedule["depends_on"] as $name2)
				{
					if (!is_string($val2))  return array("success" => false, "error" => "Expected 'depends_on' to be an array of strings for schedule '" . $name . "'.", "errorcode" => "invalid_depends_on");
					if (!isset($scheduleinfo["schedules"][$name2]))  return array("success" => false, "error" => "The 'depends_on' schedule is missing for schedule '" . $name . "'.  All dependent schedules must be defined before this schedule.", "errorcode" => "missing_depends_on");
				}
			}

			if (isset($schedule["retry_freq"]))
			{
				if (is_string($schedule["retry_freq"]))  $schedule["retry_freq"] = explode(",", $schedule["retry_freq"]);

				if (!is_array($schedule["retry_freq"]))  return array("success" => false, "error" => "Expected 'retry_freq' to be an array for schedule '" . $name . "'.", "errorcode" => "invalid_retry_freq");

				$retryfreqs = array();
				foreach ($schedule["retry_freq"] as $val)
				{
					if (is_string($val))  $retryfreqs[] = self::ConvertStrToSeconds($val);
					else if (is_int($val))  $retryfreqs[] = $val;
					else  $warnings[] = "A retry frequency for schedule '" . $name . "' is invalid.";
				}

				$schedule["retry_freq"] = $retryfreqs;
			}

			if (isset($schedule["password"]) && !is_string($schedule["password"]))  return array("success" => false, "error" => "Expected 'password' to be a string for schedule '" . $name . "'.", "errorcode" => "invalid_password");

			if (!isset($schedule["max_queue"]))  $schedule["max_queue"] = -1;
			else if (!is_int($schedule["max_queue"]))  return array("success" => false, "error" => "Expected 'max_queue' to be an integer for schedule '" . $name . "'.", "errorcode" => "invalid_max_queue");

			if (!isset($schedule["max_running"]))  $schedule["max_running"] = 1;
			else if (!is_int($schedule["max_running"]))  return array("success" => false, "error" => "Expected 'max_running' to be an integer for schedule '" . $name . "'.", "errorcode" => "invalid_max_running");

			// Remove invalid keys.
			foreach ($schedule as $key => $val)
			{
				if (!isset(self::$scheduleparams[$key]))
				{
					$bestmatch = 0;
					$bestkey = false;
					foreach (self::$scheduleparams as $key2 => $val2)
					{
						similar_text($key, $key2, $percent);

						if ($bestmatch < $percent)
						{
							$bestmatch = $percent;
							$bestkey = $key2;
						}
					}

					$warnings[] = "Invalid key '" . $key . "' found for schedule '" . $name . "'." . ($bestmatch > 0.85 ? "  Did you mean '" . $bestkey . "'?" : "");

					unset($schedule[$key]);
				}
			}

			return array("success" => true);
		}

		// Reloads the schedule trigger.
		public static function ReloadScheduleTrigger(&$cachedata, $schedulekey, $name, $schedule, $currts)
		{
			if (!isset($cachedata["schedules"][$schedulekey]))  $cachedata["schedules"][$schedulekey] = array();
			if (!isset($cachedata["schedules"][$schedulekey][$name]))  $cachedata["schedules"][$schedulekey][$name] = array();

			// Remove cache trigger.
			if (isset($cachedata["triggers"][$schedulekey]) && isset($cachedata["triggers"][$schedulekey][$name]))
			{
				unset($cachedata["triggers"][$schedulekey][$name]);

				if (!count($cachedata["triggers"][$schedulekey]))  unset($cachedata["triggers"][$schedulekey]);
			}

			$sinfo = &$cachedata["schedules"][$schedulekey][$name];

			unset($sinfo["times"]);
			unset($sinfo["reload"]);

			$ts = false;
			$mints = (isset($schedule["min_uptime"]) ? max($cachedata["boot"] + $schedule["min_uptime"], $currts) : $currts);

			if (is_string($schedule["schedule"]) || is_array($schedule["schedule"]))
			{
				$result3 = self::MakeCalendarEvent($schedule, $mints);
				$calevent = $result3["calevent"];

				$calevent->RebuildCalendar();
				$result3 = $calevent->NextTrigger();
				if ($result3 !== false)
				{
					$sinfo["times"] = $calevent->GetTimes($result3["id"]);

					$ts = $result3["ts"];
				}
			}
			else if (is_bool($schedule["schedule"]))
			{
				if ($schedule["schedule"] === true)
				{
					$ts = $mints;

					$schedule["schedule"] = false;
				}
			}
			else if (is_int($schedule["schedule"]))
			{
				$ts = max($mints, $schedule["schedule"]);

				$schedule["schedule"] = false;
			}

			if ($ts !== false && isset($schedule["random_delay"]))  $ts += mt_rand(0, $schedule["random_delay"]);

			$sinfo["schedule"] = $schedule;
			$sinfo["ts"] = $ts;

			if ($ts !== false)
			{
				if (!isset($cachedata["triggers"][$schedulekey]))  $cachedata["triggers"][$schedulekey] = array();

				$cachedata["triggers"][$schedulekey][$name] = true;
			}
		}

		// Should only be called by the xcron server.
		public static function GetLogOutputFilenameBase($schedulekey, $name)
		{
			@mkdir(self::$logpath, 0750, true);

			return self::$logpath . "/" . str_replace(array(".", "\\", "/", "|", ":", "?", "*", "\"", "'", "<", ">"), "_", $schedulekey . "--" . $name);
		}

		public static function InitLogOutputFile($filename)
		{
			if (!file_exists($filename))  @touch($filename);

			return @chmod($filename, 0640);
		}

		public static function InitScheduleStats(&$cachedata, $schedulekey, $name)
		{
			if (!isset($cachedata["stats"][$schedulekey]))  $cachedata["stats"][$schedulekey] = array();

			if (!isset($cachedata["stats"][$schedulekey][$name]))  $cachedata["stats"][$schedulekey][$name] = array();

			$sinfo = &$cachedata["stats"][$schedulekey][$name];

			if (!isset($sinfo["total"]) || !isset($sinfo["boot"]) || !isset($sinfo["yesterday"]) || !isset($sinfo["today"]))
			{
				$initvals = array("runs" => 0, "triggered" => 0, "dates_run" => 0, "errors" => 0, "notify" => 0, "time_alerts" => 0, "terminations" => 0, "cmds" => 0, "runtime" => 0, "longest_runtime" => 0, "returned_stats" => 0);

				if (!isset($sinfo["total"]))  $sinfo["total"] = $initvals;
				if (!isset($sinfo["boot"]))  $sinfo["boot"] = $initvals;
				if (!isset($sinfo["lastday"]))  $sinfo["lastday"] = $initvals;
				if (!isset($sinfo["today"]))  $sinfo["today"] = $initvals;
			}
		}

		public static function AddStatsResult(&$cachedata, $schedulekey, $name, $keymap, $mostkeymap)
		{
			if (!isset($cachedata["stats"][$schedulekey][$name]))  self::InitScheduleStats($cachedata, $schedulekey, $name);

			$sinfo = &$cachedata["stats"][$schedulekey][$name];

			foreach ($keymap as $key => $val)
			{
				if (!is_int($val) && !is_double($val)) continue;

				if (!isset($sinfo["total"][$key]))  $sinfo["total"][$key] = 0;
				if (!isset($sinfo["boot"][$key]))  $sinfo["boot"][$key] = 0;
				if (!isset($sinfo["lastday"][$key]))  $sinfo["lastday"][$key] = 0;
				if (!isset($sinfo["today"][$key]))  $sinfo["today"][$key] = 0;

				$sinfo["total"][$key] += $val;
				$sinfo["boot"][$key] += $val;
				$sinfo["today"][$key] += $val;

				if (isset($mostkeymap[$key]))
				{
					$key2 = $mostkeymap[$key];

					if (!isset($sinfo["total"][$key2]))  $sinfo["total"][$key2] = 0;
					if (!isset($sinfo["boot"][$key2]))  $sinfo["boot"][$key2] = 0;
					if (!isset($sinfo["lastday"][$key2]))  $sinfo["lastday"][$key2] = 0;
					if (!isset($sinfo["today"][$key2]))  $sinfo["today"][$key2] = 0;

					if ($sinfo["total"][$key2] < $val)  $sinfo["total"][$key2] = $val;
					if ($sinfo["boot"][$key2] < $val)  $sinfo["boot"][$key2] = $val;
					if ($sinfo["today"][$key2] < $val)  $sinfo["today"][$key2] = $val;
				}
			}
		}

		public static function GetUserDisplayName(&$schedules, $schedulekey)
		{
			if (!isset($schedules[$schedulekey]))  return $schedulekey;

			$sinfo = &$schedules[$schedulekey];

			return $sinfo[(self::$windows ? "useralt" : "user")] . (self::$windows && $sinfo["elevated"] ? " (Elevated)" : "");
		}

		// Sends a notification.  Response from this function is generally ignored except for test notifications.
		public static function NotifyScheduleResult(&$notifiers, &$cachedata, &$schedules, $schedulekey, $name, $data, $force = false)
		{
			$sinfo = &$schedules[$schedulekey];
			$csinfo = &$cachedata["schedules"][$schedulekey][$name];

			$userdisp = self::GetUserDisplayName($schedules, $schedulekey);

			if (isset($data["success"]))
			{
				if ($data["success"])
				{
					$csinfo["errors"] = 0;

					if (isset($data["stderr_warn"]))  self::DisplayMessageAndLog(LOG_WARNING, "[Warning] " . $userdisp . " | " . $name . " | " . $data["stderr_warn"]);
					else  self::DisplayMessageAndLog(LOG_NOTICE, "[Success] " . $userdisp . " | " . $name);
				}
				else
				{
					if (!isset($csinfo["errors"]))  $csinfo["errors"] = 0;

					$csinfo["errors"]++;

					self::DisplayMessageAndLog(LOG_ERR, "[Error] " . $userdisp . " | " . $name . " | " . $data["error"] . " (" . $data["errorcode"] . ")");
				}
			}

			if (isset($csinfo["schedule"]["notify"]))  $notifykeys = $csinfo["schedule"]["notify"];
			else if (isset($sinfo["notifiergroups"]["default"]))  $notifykeys = array("default");
			else  return array("success" => false, "error" => "No notifiers for schedule.", "errorcode" => "no_notifiers");

			self::AddStatsResult($cachedata, $schedulekey, $name, array("notify" => 1), array());

			$notifykeys2 = array();
			foreach ($notifykeys as $notifykey)
			{
				if (!isset($sinfo["notifiergroups"][$notifykey]))  $notifykeys2[$notifykey] = true;
				else
				{
					foreach ($sinfo["notifiergroups"][$notifykey] as $notifykey2)  $notifykeys2[$notifykey2] = true;
				}
			}

			$result = array("success" => true, "notifiers" => array());
			foreach ($notifykeys2 as $notifykey => $val)
			{
				$result["notifiers"][$notifykey] = $notifiers[$sinfo["notifiers"][$notifykey]["type"]]->Notify($notifykey, $sinfo["notifiers"][$notifykey], (isset($data["success"]) && !$force ? $csinfo["errors"] : 0), $sinfo, $schedulekey, $name, $userdisp, $data);
			}

			return $result;
		}

		// Starts a process from the schedule cache.  Supports both schedules and triggers.
		public static function StartScheduleProcess(&$procs, &$procmap, &$fpcache, &$schedules, &$cachedata, $schedulekey, $name, $xcronid, $ts, $cmdnum, $triggered, $data, $overwritemap = array())
		{
			if (!isset($cachedata["stats"][$schedulekey][$name]))  self::InitScheduleStats($cachedata, $schedulekey, $name);

			$sinfo = &$schedules[$schedulekey];
			$csinfo = &$cachedata["schedules"][$schedulekey][$name];

			// Check dependencies for positive completion.
			if (isset($csinfo["schedule"]["depends_on"]))
			{
				foreach ($csinfo["schedule"]["depends_on"] as $name2)
				{
					if (isset($cachedata["triggers"][$schedulekey][$name2]) && $cachedata["triggers"][$schedulekey][$name2] === false)  return array("success" => false, "error" => "Failed to start due to the dependency '" . $name2 . "' that is still running.", "errorcode" => "dependency_still_running");

					if (!isset($cachedata["schedules"][$schedulekey][$name2]["last_result"]) || !$cachedata["schedules"][$schedulekey][$name2]["last_result"]["success"])  return array("success" => false, "error" => "Failed to start due to the dependency '" . $name2 . "' failing.", "errorcode" => "dependency_failed");
				}
			}

			// Build the options.
			$options = array(
				"stdin" => false
			);

			if (isset($csinfo["schedule"]["dir"]))
			{
				if (!is_dir($csinfo["schedule"]["dir"]))  return array("success" => false, "error" => "Failed to start due to non-existent starting directory.", "errorcode" => "dir_missing");

				$options["dir"] = $csinfo["schedule"]["dir"];
			}

			$options["env"] = self::$env;

			if (isset($csinfo["schedule"]["env"]))
			{
				foreach ($csinfo["schedule"]["env"] as $key => $val)  $options["env"][$key] = $val;
			}

			$options["env"]["XCRON_LAST_RESULT"] = (string)json_encode((isset($csinfo["last_result"]) ? $csinfo["last_result"] : false), JSON_UNESCAPED_SLASHES);
			$options["env"]["XCRON_LAST_TS"] = (string)(isset($csinfo["last_success"]) ? $csinfo["last_success"] : 0);
			$options["env"]["XCRON_CURR_TS"] = (string)$ts;
			$options["env"]["XCRON_DATA"] = (string)$data;

			if (isset($csinfo["schedule"]["user"]))
			{
				$result = self::GetUserInfo($csinfo["schedule"]["user"]);
				if (!$result["success"])  return array("success" => false, "error" => "Invalid 'user' for the schedule.  " . $result["error"] . " (" . $result["errorcode"] . ")", "errorcode" => "invalid_user");

				$elevated = (self::$windows && isset($csinfo["schedule"]["win_elevated"]) && $csinfo["schedule"]["win_elevated"] === true);
			}
			else
			{
				$result = self::GetUserInfo($sinfo["user"]);
				if (!$result["success"])  return array("success" => false, "error" => "Invalid default user for the schedule.  " . $result["error"] . " (" . $result["errorcode"] . ")", "errorcode" => "invalid_default_user");

				$elevated = $sinfo["elevated"];
			}

			if (!self::$windows)
			{
				// Mac/Linux user.
				if ($result["uid"] != 0)  $options["user"] = $result["name"];
			}
			else if ($result["type"] == 5)
			{
				// Well-known SID.  NT AUTHORITY probably with an existing token somewhere.  Attempt to use an existing security token.  Skip if NT AUTHORITY\SYSTEM.
				if ($result["sid"] !== "S-1-5-18")  $options["createprocess_exe_opts"] = "/usetoken=" . $result["sid"] . " /mergeenv /f=SW_HIDE /f=DETACHED_PROCESS";
			}
			else if (!isset($result["net_info"]) || $result["net_info"]["flags"] & 0x00000002)  return array("success" => false, "error" => "Failed to start due to invalid or disabled 'user' account.", "errorcode" => "invalid_or_disabled_user_account");
			else
			{
				// Specified user is a real account that is not disabled.
				// Create a new security token from scratch.  This is a giant mess and kind of dangerous.
				// Watch:  https://www.youtube.com/watch?v=pmteqkbBfAY
				$options["createprocess_exe_opts"] = "/createtoken=" . $result["sid"] . ";";

				// Group SIDs and attributes.
				$groupsid = substr($result["sid"], 0, strrpos($result["sid"], "-") + 1) . $result["net_info"]["primary_group_id"];
				$groupattrs = array();
				$groupattrs[] = $groupsid . ":7";
				$groupattrs[] = "S-1-1-0:7";   // Everyone.
				if ($result["net_info"]["priv_level"] == 2)
				{
					if ($elevated)
					{
						$groupattrs[] = "S-1-5-114:7";   // NT AUTHORITY\Local account and member of Administrators group
						$groupattrs[] = "S-1-5-32-544:15";   // BUILTIN\Administrators
					}
					else
					{
						$groupattrs[] = "S-1-5-114:16";   // NT AUTHORITY\Local account and member of Administrators group
						$groupattrs[] = "S-1-5-32-544:16";   // BUILTIN\Administrators
					}
				}
				else if ($result["net_info"]["priv_level"] == 1)
				{
					// Does the order of groups matter?  BUILTIN\Users appears before BUILTIN\Performance Log Users for regular users.
				}
				else
				{
					// Guest accounts apparently get both BUILTIN\Guests and BUILTIN\Users in Windows.
					$groupattrs[] = "S-1-5-32-546:7";   // BUILTIN\Guests
				}

				$groupattrs[] = "S-1-5-32-559:7";   // BUILTIN\Performance Log Users
				$groupattrs[] = "S-1-5-32-545:7";   // BUILTIN\Users
				$groupattrs[] = "S-1-5-4:7";   // NT AUTHORITY\INTERACTIVE
				$groupattrs[] = "S-1-2-1:7";   // CONSOLE LOGON
				$groupattrs[] = "S-1-5-11:7";   // NT AUTHORITY\Authenticated Users
				$groupattrs[] = "S-1-5-15:7";   // NT AUTHORITY\This Organization
				$groupattrs[] = "S-1-5-113:7";   // NT AUTHORITY\Local account
				$groupattrs[] = "S-1-2-0:7";   // LOCAL
				$groupattrs[] = "S-1-5-64-10:7";   // NT AUTHORITY\NTLM Authentication

				if ($elevated)  $groupattrs[] = "S-1-16-12288:96";   // Mandatory Label\High Mandatory Level
				else  $groupattrs[] = "S-1-16-8192:96";   // Mandatory Label\Medium Mandatory Level

				$options["createprocess_exe_opts"] .= implode(",", $groupattrs) . ";";

				// Privileges and attributes.
				if ($elevated)
				{
					$privattrs = array(
						"SeIncreaseQuotaPrivilege:0",
						"SeSecurityPrivilege:0",
						"SeTakeOwnershipPrivilege:0",
						"SeLoadDriverPrivilege:0",
						"SeSystemProfilePrivilege:0",
						"SeSystemtimePrivilege:0",
						"SeProfileSingleProcessPrivilege:0",
						"SeIncreaseBasePriorityPrivilege:0",
						"SeCreatePagefilePrivilege:0",
						"SeBackupPrivilege:0",
						"SeRestorePrivilege:0",
						"SeShutdownPrivilege:0",
						"SeDebugPrivilege:0",
						"SeSystemEnvironmentPrivilege:0",
						"SeChangeNotifyPrivilege:3",
						"SeRemoteShutdownPrivilege:0",
						"SeUndockPrivilege:0",
						"SeManageVolumePrivilege:0",
						"SeImpersonatePrivilege:3",
						"SeCreateGlobalPrivilege:3",
						"SeIncreaseWorkingSetPrivilege:0",
						"SeTimeZonePrivilege:0",
						"SeCreateSymbolicLinkPrivilege:0",
						"SeDelegateSessionUserImpersonatePrivilege:0",
					);
				}
				else
				{
					// Guests apparently get regular user privileges in Windows too.
					$privattrs = array(
						"SeShutdownPrivilege:0",
						"SeChangeNotifyPrivilege:3",
						"SeUndockPrivilege:0",
						"SeIncreaseWorkingSetPrivilege:0",
						"SeTimeZonePrivilege:0",
					);
				}

				$options["createprocess_exe_opts"] .= implode(",", $privattrs) . ";";

				// Owner SID.
				if ($result["net_info"]["priv_level"] == 2 && $elevated)  $options["createprocess_exe_opts"] .= "S-1-5-32-544;";
				else  $options["createprocess_exe_opts"] .= $result["sid"] . ";";

				// Primary Group SID.
				$options["createprocess_exe_opts"] .= $groupsid . ";";

				// Default DACL.  GENERIC ALL access to the process token for BUILTIN\Administrators, SYSTEM, and the user.
				$options["createprocess_exe_opts"] .= "D:(A;;GA;;;BA)(A;;GA;;;SY)(A;;GA;;;" . $result["sid"] . ");";

				// Token Source and Source LUID.  "User32 \x00"
				$options["createprocess_exe_opts"] .= "5573657233322000:0";

				$options["createprocess_exe_opts"] .= " /mergeenv /f=SW_HIDE /f=DETACHED_PROCESS";
			}

			// Only write output to disk if this is not a triggered run.  Active monitoring can still happen.
			$outputfile = false;
			if (!$triggered)
			{
				$outputfile = (isset($csinfo["schedule"]["output_file"]) ? $csinfo["schedule"]["output_file"] : self::GetLogOutputFilenameBase($schedulekey, $name)) . ".log";

				if (!self::InitLogOutputFile($outputfile))  return array("success" => false, "error" => "Failed to start due to inability to prepare the output file.", "errorcode" => "chmod_failed");

				$fp = fopen($outputfile, ($cmdnum == 0 ? "w+b" : "a+b"));

				if ($fp === false)  return array("success" => false, "error" => "Failed to start due to inability to open the output file.", "errorcode" => "fopen_failed");

				if (!isset($fpcache[$outputfile]))  $fpcache[$outputfile] = array("fp" => $fp, "refs" => 1, "xcronid" => $xcronid);
				else
				{
					fclose($fpcache[$outputfile]["fp"]);

					$fpcache[$outputfile]["fp"] = $fp;
					$fpcache[$outputfile]["refs"]++;
					$fpcache[$outputfile]["xcronid"] = $xcronid;
				}
			}

			$result2 = ProcessHelper::StartProcess($csinfo["schedule"]["cmds"][$cmdnum], $options);
			if (!$result2["success"])
			{
				unset($result2["info"]);

				// Clean up file handle if it was opened a moment ago.
				if ($outputfile !== false)
				{
					$fpcache[$outputfile]["refs"]--;

					if ($fpcache[$outputfile]["refs"] < 1)
					{
						fclose($fpcache[$outputfile]["fp"]);

						unset($fpcache[$outputfile]);
					}
				}

				return $result2;
			}

			if (self::$debug)  echo $result2["info"]["cmd"] . "\n";

			$result2["xcronid"] = $xcronid;
			$result2["startts"] = $ts;
			$result2["truestartts"] = microtime(true);
			$result2["nextcmd"] = $cmdnum + 1;
			$result2["triggered"] = $triggered;
			$result2["alerted"] = false;
			$result2["data"] = $data;
			$result2["outputfile"] = $outputfile;
			$result2["bytesread"] = 0;
			$result2["outdata"] = "";
			$result2["eol"] = true;
			$result2["outpos"] = 0;
			$result2["lastline"] = "";
			$result2["stdout"] = "";
			$result2["stderr"] = "";
			$result2["monitors"] = array();
			$result2["result"] = array("success" => true);

			foreach ($overwritemap as $key => $val)  $result2[$key] = $val;

			if (!isset($procs[$schedulekey]))  $procs[$schedulekey] = array();
			if (!isset($procs[$schedulekey][$name]))  $procs[$schedulekey][$name] = array();

			$procs[$schedulekey][$name][$result2["pid"]] = $result2;

			$procmap[$xcronid] = array("schedulekey" => $schedulekey, "name" => $name, "pid" => $result2["pid"]);

			return array("success" => true, "pid" => $result2["pid"]);
		}

		public static function GetSafeSchedule(&$schedules, &$cachedata, &$startqueuenums, $schedulekey, $name)
		{
			$csinfo = &$cachedata["schedules"][$schedulekey][$name];

			$result = array(
				"queued" => (isset($startqueuenums[$schedulekey][$name]) ? $startqueuenums[$schedulekey][$name] : 0),
				"schedule" => $csinfo["schedule"],
				"next_ts" => $csinfo["ts"]
			);

			if (isset($csinfo["run_ts"]) && ($csinfo["ts"] === false || $csinfo["run_ts"] < $csinfo["ts"]))  $result["next_ts"] = $csinfo["run_ts"];

			if (isset($csinfo["suspend_until"]))  $result["suspend_until_ts"] = $csinfo["suspend_until"];

			if (isset($csinfo["last_run"]))  $result["last_run_ts"] = $csinfo["last_run"];
			if (isset($csinfo["last_success"]))  $result["last_success_ts"] = $csinfo["last_success"];
			if (isset($csinfo["last_result"]))  $result["last_result"] = $csinfo["last_result"];
			if (isset($csinfo["retries"]))  $result["retries"] = $csinfo["retries"];
			if (isset($csinfo["start_retries"]))  $result["start_retries"] = $csinfo["start_retries"];

			foreach (self::$sensitiveparams as $param => $val)  unset($result["schedule"][$param]);

			return $result;
		}
	}
?>