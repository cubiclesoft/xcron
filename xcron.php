<?php
	// xcron.
	// (C) 2022 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	define("XCRON_VER", "1.0.0");

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/support/cli.php";
	require_once $rootpath . "/support/utf8.php";
	require_once $rootpath . "/support/str_basics.php";
	require_once $rootpath . "/support/event_manager.php";
	require_once $rootpath . "/support/process_helper.php";
	require_once $rootpath . "/support/generic_server.php";
//	require_once $rootpath . "/support/generic_server_libev.php";
	require_once $rootpath . "/support/calendar_event.php";
	require_once $rootpath . "/support/xcron_functions.php";

	// Process the command-line options.
	$options = array(
		"shortmap" => array(
			"d" => "debug",
			"?" => "help"
		),
		"rules" => array(
			"debug" => array("arg" => true),
			"reset" => array("arg" => false),
			"help" => array("arg" => false)
		),
		"allow_opts_after_param" => false
	);
	$args = CLI::ParseCommandLine($options);

	if (isset($args["opts"]["help"]))
	{
		echo "xcron\n";
		echo "Purpose:  Main xcron system service for cron/task/job scheduling.\n";
		echo "\n";
		echo "Syntax:  " . $args["file"] . " [options] [cmd]\n";
		echo "Options:\n";
		echo "\t-d DATETIME   Enable debug mode and sets the internal time.  Does not save/cache schedules to disk.\n";
		echo "\t-reset        Clear all cached schedules.  Must reload to load schedules.\n";
		echo "\n";
		echo "Commands:\n";
		echo "\tinstall       Install the system service.\n";
		echo "\tuninstall     Uninstall the system service.\n";
		echo "\tdumpconfig    Dump the system service configuration.\n";
		echo "\n";
		echo "Examples:\n";
		echo "\tphp " . $args["file"] . "\n";
		echo "\tphp " . $args["file"] . " -d " . escapeshellarg(date("Y-m-d") . " 23:58") . "\n";

		exit();
	}

	$origargs = $args;

	XCronHelper::Init();

	if (isset($args["opts"]["debug"]))  XCronHelper::$debug = true;

	openlog("xcron", LOG_NDELAY, (XCronHelper::$windows ? LOG_USER : LOG_CRON));

	// Verify that xcron was started as NT AUTHORITY\SYSTEM or root.
	$result = XCronHelper::GetCurrentUserInfo();
	if (!$result["success"])  XCronHelper::DisplayMessageAndLog(LOG_ERR, "[Error] " . $result["error"] . " (" . $result["errorcode"] . ")", $result["info"], true);

	$windows = $result["windows"];
	$uid = $result["uid"];
	$allusers = $result["allusers"];

	if ($windows)  $currtoken = $result["currtoken"];
	else  $gid = $result["gid"];

	if (!$windows)
	{
		if (!$allusers)  XCronHelper::DisplayMessageAndLog(LOG_ERR, "[Error] xcron must be run as root.", false, true);

		// Create 'xcrontab' group if missing.
		$uinfo = posix_getgrnam("xcrontab");
		if ($uinfo === false)
		{
			// Linux.
			$cmd = ProcessHelper::FindExecutable("groupadd", "/usr/sbin");
			if ($cmd !== false)  $cmd = escapeshellarg($cmd) . " xcrontab";
			else
			{
				// Mac OSX.
				$cmd = ProcessHelper::FindExecutable("dseditgroup", "/usr/sbin");
				if ($cmd !== false)  $cmd = escapeshellarg($cmd) . " -q -o create xcrontab";
				else
				{
					// BSD.
					$cmd = ProcessHelper::FindExecutable("pw", "/usr/sbin");
					if ($cmd !== false)  $cmd = escapeshellarg($cmd) . " groupadd xcrontab";
				}
			}

			if ($cmd !== false)  system($cmd);

			$uinfo = posix_getgrnam("xcrontab");
			if ($uinfo === false)  XCronHelper::DisplayMessageAndLog(LOG_ERR, "[Error] xcron was unable to create the 'xcrontab' group.", false, true);
		}

		// Create the shell script if needed.
		if (!file_exists($rootpath . "/xcrontab") || count($args["params"]))
		{
			$cmd = ProcessHelper::FindExecutable("php", "/usr/bin");
			if ($cmd === false)  $cmd = PHP_BINARY;

			if (file_exists("/usr/bin/php") && realpath("/usr/bin/php") === $cmd)  $cmd = "/usr/bin/php";

			$data = "#!/bin/sh\n\n";
			$data .= escapeshellarg($cmd) . " " . escapeshellarg($rootpath . "/xcrontab.php") . " \"\$@\"\n";

			file_put_contents($rootpath . "/xcrontab", $data);

			chmod($rootpath . "/xcrontab", 0755);
		}
	}

	if (count($args["params"]))
	{
		// Service Manager PHP SDK.
		require_once $rootpath . "/servicemanager/sdks/servicemanager.php";

		$sm = new ServiceManager($rootpath . "/servicemanager");

		echo "Service manager:  " . $sm->GetServiceManagerRealpath() . "\n\n";

		$servicename = "xcron";

		if ($args["params"][0] == "install")
		{
			// Install the service.
			$args = array();
			$options = array();

			$result = $sm->Install($servicename, __FILE__, $args, $options, true);
			if (!$result["success"])  CLI::DisplayError("Unable to install the '" . $servicename . "' service.", $result);

			if (!$windows)
			{
				@copy($rootpath . "/xcrontab", "/usr/local/bin/xcrontab");
				@chmod("/usr/local/bin/xcrontab", 0755);
			}
		}
		else if ($args["params"][0] == "start")
		{
			// Start the service.
			$result = $sm->Start($servicename, true);
			if (!$result["success"])  CLI::DisplayError("Unable to start the '" . $servicename . "' service.", $result);
		}
		else if ($args["params"][0] == "stop")
		{
			// Stop the service.
			$result = $sm->Stop($servicename, true);
			if (!$result["success"])  CLI::DisplayError("Unable to stop the '" . $servicename . "' service.", $result);
		}
		else if ($args["params"][0] == "uninstall")
		{
			// Uninstall the service.
			$result = $sm->Uninstall($servicename, true);
			if (!$result["success"])  CLI::DisplayError("Unable to uninstall the '" . $servicename . "' service.", $result);

			if (!$windows)  @unlink("/usr/local/bin/xcrontab");
		}
		else if ($args["params"][0] == "dumpconfig")
		{
			$result = $sm->GetConfig($servicename);
			if (!$result["success"])  CLI::DisplayError("Unable to retrieve the configuration for the '" . $servicename . "' service.", $result);

			echo "Service configuration:  " . $result["filename"] . "\n\n";

			echo "Current service configuration:\n\n";
			foreach ($result["options"] as $key => $val)  echo "  " . $key . " = " . $val . "\n";
		}
		else
		{
			echo "Command not recognized.  Run the service manager directly for anything other than 'install', 'start', 'stop', 'uninstall', and 'dumpconfig'.\n";
		}

		exit();
	}

	if (isset($args["opts"]["debug"]))
	{
		$debugstartts = time();

		$ts = strtotime($args["opts"]["debug"]);
		if ($ts < 1)  CLI::DisplayError("Unable to parse supplied debug date/time.");

		// Force seconds to match the system clock to make it easier to know when schedules will fire.
		$debugbasets = mktime(date("H", $ts), date("i", $ts), date("s"), date("n", $ts), date("j", $ts), date("Y", $ts));

		echo "Debug mode.\n";
		echo "Simulating:  " . date("Y-m-d H:i:s", $debugbasets) . "\n";
	}

	if ($windows)
	{
		// This check is done later since only elevated Administrator users can install/manage system services (i.e. not NT AUTHORITY\SYSTEM).
		if ($uid !== "S-1-5-18")  XCronHelper::DisplayMessageAndLog(LOG_ERR, "[Error] xcron must be run as NT AUTHORITY\\SYSTEM.  Run 'system_cmd.bat' to create a Command Prompt that is elevated to NT AUTHORITY\\SYSTEM and then try again.", false, true);

		$schedulespath = getenv("LOCALAPPDATA");
		if ($schedulespath === false)
		{
			$schedulespath = getenv("USERPROFILE");
			if ($schedulespath === false)  XCronHelper::DisplayMessageAndLog(LOG_ERR, "[Error] LOCALAPPDATA and USERPROFILE are not set.  Failed to find the user profile.", false, true);

			$schedulespath .= "\\AppData\\Local";
		}

		$cachepath = getenv("TEMP");
		if ($cachepath === false)  $cachepath = getenv("TMP");
		if ($cachepath === false)  XCronHelper::DisplayMessageAndLog(LOG_ERR, "[Error] TEMP and TMP are not set.  Failed to find temporary cache path.", false, true);

		$schedulespath .= "\\xcron";
		$schedulesfile = $schedulespath . "\\xcron_schedules.json";

		@mkdir($schedulespath, 0770, true);

		$cachefile = $cachepath . "\\xcron_cache.json";
	}
	else
	{
		$schedulespath = "/var/spool/cron/xcrontabs";
		$schedulesfile = $schedulespath . "/.xcron_schedules.json";

		@mkdir($schedulespath, 0770, true);
		@chgrp($schedulespath, "xcrontab");
		@chmod($schedulespath, 01730);

		$cachepath = "/var/cache";
		$cachefile = $cachepath . "/xcron_cache.json";
	}

	// Load notifier classes.
	$notifiers = array();
	$dir = opendir($rootpath . "/notifiers");
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			if (substr($file, -4) === ".php")
			{
				$name = substr($file, 0, -4);
				$name2 = "XCronNotifier_" . $name;

				require_once $rootpath . "/notifiers/" . $file;

				if (class_exists($name2, false))  $notifiers[$name] = new $name2;
			}
		}

		closedir($dir);
	}

	// Initialize the extension event manager.
	XCronHelper::$em = new EventManager();

	function GetServerInfo()
	{
		global $ts, $nextts, $cachedata, $todayts, $tomorrowts, $nextid, $startqueue, $procmap, $maxprocs, $fpcache, $schedulemonitors, $futureattachmap, $gs;

		$result = array(
			"server" => "xcron " . XCRON_VER,
			"ts" => $ts,
			"next_ts" => $nextts,
			"boot_ts" => $cachedata["boot"],
			"cache_today_ts" => $cachedata["today"],
			"today_ts" => $todayts,
			"tomorrow_ts" => $tomorrowts,
			"next_id" => $nextid,
			"num_start_queue" => count($startqueue),
			"num_procs" => count($procmap),
			"max_procs" => $maxprocs,
			"num_open_files" => count($fpcache),
			"num_schedule_monitors" => count($schedulemonitors),
			"num_future_attach" => count($futureattachmap),
			"num_clients" => $gs->NumClients()
		);

		return $result;
	}

	function CanNotifySchedule($schedulekey, $name, &$filter)
	{
		global $schedules;

		return (isset($schedules[$schedulekey]) && (!isset($filter["user"]) || $schedules[$schedulekey]["user"] === $filter["user"]) && (!isset($filter["elevated"]) || $schedules[$schedulekey]["elevated"] === $filter["elevated"]) && (!isset($filter["name"]) || $filter["name"] === $name));
	}

	function NotifyJobScheduleMonitors(&$excludecids, $result)
	{
		global $schedulemonitors, $gs;

		foreach ($schedulemonitors as $cid => &$filters)
		{
			$client = $gs->GetClient($cid);

			if ($client !== false)
			{
				foreach ($filters as &$filter)
				{
					if (CanNotifySchedule($result["schedule"], $result["name"], $filter))
					{
						if (!isset($result["serv_info"]))  $result["serv_info"] = GetServerInfo();

						$excludecids[$cid] = true;

						$client->writedata .= json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";

						$gs->UpdateClientState($cid);

						break;
					}
				}
			}
		}
	}

	function NotifyAllJobMonitors(&$pinfo, &$result)
	{
		global $gs;

		// Schedule monitors.
		NotifyJobScheduleMonitors($excludecids, $result);

		// Process monitors.
		$excludecids = array();
		foreach ($pinfo["monitors"] as $cid => $fids)
		{
			$client = $gs->GetClient($cid);

			if ($client !== false)
			{
				if (isset($excludecids[$cid]))  continue;

				foreach ($fids as $fid => $val)
				{
					$result["file_id"] = $fid;
					$client->writedata .= json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
					unset($result["file_id"]);
				}

				$gs->UpdateClientState($cid);
			}
		}
	}

	function NotifyJobProcessDone(&$pinfo, &$psinfo, $result)
	{
		NotifyAllJobMonitors($pinfo, $result);
	}

	XCronHelper::EventRegister("schedule_proc_done", "NotifyJobProcessDone");

	function NotifyJobProcessStart(&$sinfo, &$result, &$pinfo, $result2)
	{
		if ($pinfo !== false)  NotifyAllJobMonitors($pinfo, $result2);
		else
		{
			$excludecids = array();

			NotifyJobScheduleMonitors($excludecids, $result2);
		}
	}

	XCronHelper::EventRegister("schedule_proc_start_error", "NotifyJobProcessStart");
	XCronHelper::EventRegister("schedule_proc_set", "NotifyJobProcessStart");

	function NotifyJobDone(&$sinfo, &$pinfo, $result)
	{
		NotifyAllJobMonitors($pinfo, $result);
	}

	XCronHelper::EventRegister("schedule_job_done", "NotifyJobDone");


	function NotifyScheduleMonitors_ReloadedSchedule($schedulekey, $name)
	{
		global $schedulemonitors, $gs, $schedules, $cachedata, $startqueuenums;

		$result = false;
		$stats = false;

		foreach ($schedulemonitors as $cid => &$filters)
		{
			$client = $gs->GetClient($cid);

			if ($client !== false)
			{
				foreach ($filters as &$filter)
				{
					if (CanNotifySchedule($schedulekey, $name, $filter))
					{
						if ($result === false)
						{
							$result = array(
								"success" => true,
								"monitor" => "schedule",
								"type" => "reloaded_schedule",
								"serv_info" => GetServerInfo(),
								"schedule" => $schedulekey,
								"schedule_disp" => XCronHelper::GetUserDisplayName($schedules, $schedulekey),
								"name" => $name,
								"schedule_info" => XCronHelper::GetSafeSchedule($schedules, $cachedata, $startqueuenums, $schedulekey, $name)
							);
						}

						if (!$filter["stats"])  unset($result["stats"]);
						else
						{
							if ($stats === false)
							{
								if (!isset($cachedata["stats"][$schedulekey][$name]))  XCronHelper::InitScheduleStats($cachedata, $schedulekey, $name);

								$csinfo = &$cachedata["stats"][$schedulekey][$name];

								$stats = array(
									"keys" => array_keys($csinfo["total"]),
									"total" => array_values($csinfo["total"]),
									"boot" => array_values($csinfo["boot"]),
									"lastday" => array_values($csinfo["lastday"]),
									"today" => array_values($csinfo["today"])
								);
							}

							$result["stats"] = &$stats;
						}

						$client->writedata .= json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";

						$gs->UpdateClientState($cid);

						break;
					}
				}
			}
		}
	}

	function NotifyScheduleMonitors_RemovedSchedule($schedulekey, $name)
	{
		global $schedulemonitors, $gs, $schedules;

		$result = false;

		foreach ($schedulemonitors as $cid => &$filters)
		{
			$client = $gs->GetClient($cid);

			if ($client !== false)
			{
				foreach ($filters as &$filter)
				{
					if (CanNotifySchedule($schedulekey, $name, $filter))
					{
						if ($result === false)
						{
							$result = array(
								"success" => true,
								"monitor" => "schedule",
								"type" => "removed_schedule",
								"serv_info" => GetServerInfo(),
								"schedule" => $schedulekey,
								"schedule_disp" => XCronHelper::GetUserDisplayName($schedules, $schedulekey),
								"name" => $name
							);
						}

						$client->writedata .= json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";

						$gs->UpdateClientState($cid);

						break;
					}
				}
			}
		}
	}

	XCronHelper::EventRegister("reloaded_schedule", "NotifyScheduleMonitors_ReloadedSchedule");
	XCronHelper::EventRegister("removed_schedule", "NotifyScheduleMonitors_RemovedSchedule");


	function NotifyScheduleMonitors_ServerInfo()
	{
		global $schedulemonitors, $gs, $schedules;

		$result = false;

		foreach ($schedulemonitors as $cid => &$filters)
		{
			$client = $gs->GetClient($cid);

			if ($client !== false)
			{
				if ($result === false)
				{
					$result = array(
						"success" => true,
						"monitor" => "schedule",
						"type" => "server_info",
						"serv_info" => GetServerInfo()
					);
				}

				$client->writedata .= json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";

				$gs->UpdateClientState($cid);
			}
		}
	}

	function NotifyScheduleMonitors_NextEvent($ts, $nextts)
	{
		NotifyScheduleMonitors_ServerInfo();
	}

	function NotifyScheduleMonitors_ClientConnectDisconnect($client)
	{
		NotifyScheduleMonitors_ServerInfo();
	}

	function NotifyScheduleMonitors_ProcReadyHandled()
	{
		NotifyScheduleMonitors_ServerInfo();
	}

	XCronHelper::EventRegister("next_event", "NotifyScheduleMonitors_NextEvent");
	XCronHelper::EventRegister("client_connected", "NotifyScheduleMonitors_ClientConnectDisconnect");
	XCronHelper::EventRegister("client_disconnected", "NotifyScheduleMonitors_ClientConnectDisconnect");
	XCronHelper::EventRegister("proc_ready_handled", "NotifyScheduleMonitors_ProcReadyHandled");


	// Load custom extensions.
	$dir = opendir($rootpath . "/extensions");
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			if (substr($file, -4) === ".php")  require_once $rootpath . "/extensions/" . $file;
		}

		closedir($dir);
	}

	XCronHelper::$em->Fire("init", array());

	// For performance reasons, xcron ignores schedules outside of the current day.
	if (XCronHelper::$debug)
	{
		$todayts = mktime(0, 0, 0, date("n", $debugbasets), date("j", $debugbasets), date("Y", $debugbasets));
		$tomorrowts = mktime(0, 0, 0, date("n", $debugbasets), date("j", $debugbasets) + 1, date("Y", $debugbasets));

		echo "Today:  " . date("Y-m-d", $todayts) . "\n";
		echo "Tomorrow:  " . date("Y-m-d", $tomorrowts) . "\n";
	}
	else
	{
		$todayts = mktime(0, 0, 0);
		$tomorrowts = mktime(0, 0, 0, date("n", $todayts), date("j", $todayts) + 1, date("Y", $todayts));
	}
	$nextts = true;

	// Get the timestamp the system was booted.  Note that the returned value actually drifts over time on some systems.
	$result = XCronHelper::GetBootTimestamp();
	if (!$result["success"])  XCronHelper::DisplayMessageAndLog(LOG_ERR, "[Error] " . $result["error"] . " (" . $result["errorcode"] . ")", $result["info"], false, true);
	$ts = $result["ts"];
//var_dump($result);
//exit();

	// Load schedules and cache.
	$schedules = (file_exists($schedulesfile) ? @json_decode(file_get_contents($schedulesfile), true) : false);
	$cachedata = (file_exists($cachefile) ? @json_decode(file_get_contents($cachefile), true) : false);
	$cachedataupdated = true;

	if (isset($args["opts"]["reset"]) || !is_array($schedules))  $schedules = array();
	if (isset($args["opts"]["reset"]) || !is_array($cachedata))  $cachedata = array("schedules" => array(), "triggers" => array(), "livetriggers" => array(), "stats" => array(), "boot" => $ts, "today" => $todayts);

	if (!XCronHelper::$debug && isset($args["opts"]["reset"]))
	{
		@touch($schedulesfile);
		@chmod($schedulesfile, 0600);

		file_put_contents($schedulesfile, json_encode($schedules, JSON_UNESCAPED_SLASHES));
	}

	// Allow for up to 3 seconds of clock drift for the boot timestamp before detecting a reboot.
	$rebooted = (abs($ts - $cachedata["boot"]) > 3);
	$cachedata["boot"] = $ts;

	// Reload missing schedules and startup/boot triggers.  Load all schedules that have timestamps for today or in the past into triggers.
	$ts = (XCronHelper::$debug ? $debugbasets : time());
	foreach ($schedules as $schedulekey => &$sinfo)
	{
		foreach ($sinfo["schedules"] as $name => $schedule)
		{
			if (!isset($cachedata["schedules"][$schedulekey]) || (isset($schedule["reload_at_start"]) && $schedule["reload_at_start"] === true) || ($rebooted && isset($schedule["reload_at_boot"]) && $schedule["reload_at_boot"] === true))
			{
				XCronHelper::ReloadScheduleTrigger($cachedata, $schedulekey, $name, $schedule, $ts);
			}

			if (isset($cachedata["triggers"][$schedulekey][$name]))  $cachedata["triggers"][$schedulekey][$name] = true;
			else if (isset($cachedata["schedules"][$schedulekey][$name]))
			{
				$sinfo = &$cachedata["schedules"][$schedulekey][$name];

				if ($sinfo["ts"] !== false && $sinfo["ts"] < $tomorrowts)
				{
					// Push outdated schedules forward a few minutes into the future to delay until the system has somewhat settled down.
					if ($sinfo["ts"] < $ts - 3600)
					{
						$sinfo["ts"] = $ts + 300;
						$sinfo["reload"] = true;
					}
					else if ($sinfo["ts"] < $ts)
					{
						$sinfo["ts"] = $ts + 60;
						$sinfo["reload"] = true;
					}

					if (!isset($cachedata["triggers"][$schedulekey]))  $cachedata["triggers"][$schedulekey] = array();

					$cachedata["triggers"][$schedulekey][$name] = true;
				}

				if (isset($sinfo["run_ts"]) && $sinfo["run_ts"] < $tomorrowts)
				{
					// Push outdated schedules forward a few minutes into the future to delay until the system has somewhat settled down.
					if ($sinfo["run_ts"] < $ts - 3600)
					{
						$sinfo["run_ts"] = $ts + 300;
						$sinfo["reload"] = true;
					}
					else if ($sinfo["run_ts"] < $ts)
					{
						$sinfo["run_ts"] = $ts + 60;
						$sinfo["reload"] = true;
					}

					if (!isset($cachedata["triggers"][$schedulekey]))  $cachedata["triggers"][$schedulekey] = array();

					$cachedata["triggers"][$schedulekey][$name] = true;
				}

				unset($sinfo);
			}
		}
	}

	// Update schedule stats.
	if ($rebooted)
	{
		foreach ($cachedata["stats"] as $schedulekey => &$namemap)
		{
			foreach ($namemap as $name => &$stats)
			{
				unset($stats["boot"]);

				XCronHelper::InitScheduleStats($cachedata, $schedulekey, $name);
			}
		}
	}

	if ($cachedata["today"] !== $todayts)
	{
		// Move today's stats to last day.
		foreach ($cachedata["stats"] as $schedulekey => &$namemap)
		{
			foreach ($namemap as $name => &$stats)
			{
				$stats["lastday"] = $stats["today"];

				unset($stats["today"]);

				XCronHelper::InitScheduleStats($cachedata, $schedulekey, $name);
			}
		}

		$cachedata["today"] = $todayts;
	}

	$nextid = 1;

	$startqueue = array();
	$startqueuenums = array();
	$procready = false;

	$procs = array();
	$procmap = array();
	$maxprocs = 30;

	$fpcache = array();
	$schedulemonitors = array();
	$futureattachmap = array();

	$serverip = "127.0.0.1";
	$serverport = 10829;

	// Start the server.
//	echo "Starting server" . (LibEvGenericServer::IsSupported() ? " with PECL libev support" : "") . " on " . $serverip . ":" . $serverport . "...\n";
//	$gs = (LibEvGenericServer::IsSupported() ? new LibEvGenericServer() : new GenericServer());
	echo "Starting server on " . $serverip . ":" . $serverport . "...\n";
	$gs = new GenericServer();
	$gs->SetDefaultClientTimeout(300);
//	$gs->SetDebug(true);
	$result = $gs->Start($serverip, $serverport);
	if (!$result["success"])  XCronHelper::DisplayMessageAndLog(LOG_ERR, "[Error] Unable to start server.", $result, true);

	XCronHelper::$em->Fire("ready", array());

	echo "Ready.\n";

	$stopfilename = __FILE__ . ".notify.stop";
	$reloadfilename = __FILE__ . ".notify.reload";
	$lastservicecheck = time();
	$running = true;

	do
	{
		$ts = time();
		if (XCronHelper::$debug)  $ts = $ts - $debugstartts + $debugbasets;

		$timeout = 3;

		XCronHelper::$em->Fire("main_loop", array($ts, &$timeout));

		// Handle daily rollover.
		if ($ts >= $tomorrowts)
		{
			if (XCronHelper::$debug)  echo "Next day!\n";

			XCronHelper::ResetUserInfoCache();

			$ts2 = $todayts;
			$todayts = $tomorrowts;
			$tomorrowts = mktime(0, 0, 0, date("n", $todayts), date("j", $todayts) + 1, date("Y", $todayts));

			if (XCronHelper::$debug)
			{
				echo "Yesterday:  " . date("Y-m-d", $ts2) . "\n";
				echo "Today:  " . date("Y-m-d", $todayts) . "\n";
				echo "Tomorrow:  " . date("Y-m-d", $tomorrowts) . "\n";
			}

			XCronHelper::$em->Fire("next_day", array($ts2, $todayts, $tomorrowts));

			// Update the boot timestamp to minimize clock drift.
			$result = XCronHelper::GetBootTimestamp();
			if ($result["success"])  $cachedata["boot"] = $result["ts"];

			// Load today's schedules into the active triggers.
			foreach ($cachedata["schedules"] as $schedulekey => &$namemap)
			{
				foreach ($namemap as $name => &$sinfo)
				{
					// Some schedules are over 12 months in the future.  Attempt to rebuild the schedule.
					if ($sinfo["ts"] === false && (is_string($sinfo["schedule"]["schedule"]) || is_array($sinfo["schedule"]["schedule"])))
					{
						XCronHelper::ReloadScheduleTrigger($cachedata, $schedulekey, $name, $sinfo["schedule"], $ts);

						XCronHelper::$em->Fire("reloaded_schedule", array($schedulekey, $name));
					}

					if (!isset($cachedata["triggers"][$schedulekey][$name]) && (($sinfo["ts"] !== false && $sinfo["ts"] < $tomorrowts) || (isset($sinfo["run_ts"]) && $sinfo["run_ts"] < $tomorrowts)))
					{
						if (!isset($cachedata["triggers"][$schedulekey]))  $cachedata["triggers"][$schedulekey] = array();

						$cachedata["triggers"][$schedulekey][$name] = true;
					}
				}

				unset($sinfo);
			}

			// Move today's stats to last day.
			foreach ($cachedata["stats"] as $schedulekey => &$namemap)
			{
				foreach ($namemap as $name => &$stats)
				{
					$stats["lastday"] = $stats["today"];

					unset($stats["today"]);

					XCronHelper::InitScheduleStats($cachedata, $schedulekey, $name);
				}
			}

			$cachedata["today"] = $todayts;

			$nextts = true;

			$cachedataupdated = true;

			if (XCronHelper::$debug)  var_dump($cachedata["triggers"]);
		}

		// Process ready schedules.
		if ($nextts === true || ($nextts !== false && $nextts <= $ts))
		{
			$nextts = false;
			foreach ($cachedata["triggers"] as $schedulekey => $names)
			{
				foreach ($names as $name => $val)
				{
					if ($val === true)
					{
						$sinfo = &$cachedata["schedules"][$schedulekey][$name];

						$ts2 = $sinfo["ts"];

						if (isset($sinfo["run_ts"]) && ($sinfo["ts"] === false || $ts2 > $sinfo["run_ts"]))  $ts2 = $sinfo["run_ts"];

						if (isset($sinfo["suspend_until"]))
						{
							if ($ts < $sinfo["suspend_until"])  $ts2 = $sinfo["suspend_until"];
							else  unset($sinfo["suspend_until"]);
						}

						if ($ts2 <= $ts)
						{
							if (XCronHelper::$debug)  echo "Ready:  " . $name . " (" . $ts2 . ")\n";

							$startqueue[$nextid] = array(
								"schedulekey" => $schedulekey,
								"name" => $name,
								"triggered" => false,
								"data" => "false",
								"watch" => false
							);

							$nextid++;

							if (!isset($startqueuenums[$schedulekey]))  $startqueuenums[$schedulekey] = array();
							if (!isset($startqueuenums[$schedulekey][$name]))  $startqueuenums[$schedulekey][$name] = 0;
							$startqueuenums[$schedulekey][$name]++;

							$procready = true;

							$cachedata["triggers"][$schedulekey][$name] = false;

							// Merge remote time into the active schedule time.
							if (isset($sinfo["run_ts"]) && $ts2 == $sinfo["run_ts"])
							{
								$sinfo["ts"] = $ts2;

								unset($sinfo["run_ts"]);
							}

							$timeout = 0;
						}
						else if (!isset($sinfo["reload"]) && $ts2 >= $tomorrowts)
						{
							unset($cachedata["triggers"][$schedulekey][$name]);

							if (!count($cachedata["triggers"][$schedulekey]))  unset($cachedata["triggers"][$schedulekey]);
						}
						else if ($ts2 !== false && ($nextts === false || $nextts > $ts2))
						{
							$nextts = $ts2;
						}

						unset($sinfo);
					}
				}
			}

			$cachedataupdated = true;

			XCronHelper::$em->Fire("next_event", array($ts, $nextts));

			if (XCronHelper::$debug)
			{
				echo "Next event:  " . ($nextts !== false ? date("Y-m-d H:i:s", $nextts) : "None") . "\n";

				echo "Schedule triggers:\n";
				var_dump($cachedata["triggers"]);
			}
		}

		if ($timeout > 0)
		{
			// When close to a scheduled time, reduce the timeout period.
			if ($nextts !== false)
			{
				if ($nextts <= $ts + 1)  $timeout = 1;
				else if ($timeout > 2 && $nextts == $ts + 2)  $timeout = 2;
			}

			// Reduce the timeout when close to crossing over to the next day.
			if ($tomorrowts - $ts < 5)  $timeout = 1;

			// Reduce the timeout when there are active processes.
			if (count($procs))  $timeout = 1;
		}

		// Handle active processes.
		foreach ($procs as $schedulekey => &$namemap)
		{
			foreach ($namemap as $name => &$pidmap)
			{
				foreach ($pidmap as $pid => &$pinfo)
				{
//var_dump($pinfo);
//$result = ProcessHelper::Wait($pinfo["proc"], $pinfo["pipes"]);
//var_dump($result);
//exit();

					// Handle stdout.
					if (isset($pinfo["pipes"][1]))
					{
						$data = fread($pinfo["pipes"][1], 65536);

						XCronHelper::$em->Fire("proc_read_stdout_raw", array(&$pinfo, &$data));

						if ($data === false || ($data === "" && feof($pinfo["pipes"][1])))
						{
							fclose($pinfo["pipes"][1]);

							unset($pinfo["pipes"][1]);

							if ($pinfo["stdout"] !== "" && substr($pinfo["stdout"], -1) !== "\n")  $pinfo["stdout"] .= "\n";
						}
						else
						{
							$pinfo["bytesread"] += strlen($data);
							$pinfo["stdout"] .= $data;

							// Keep track of the last line of non-empty output for later.
							$pinfo["lastline"] .= $data;
							if (strpos($pinfo["lastline"], "\n") !== false)
							{
								$lines = explode("\n", $pinfo["lastline"]);
								for ($x = count($lines); $x && trim($lines[$x - 1]) === ""; $x--)
								{
								}

								$pinfo["lastline"] = ($x ? $lines[$x - 1] . ($x < count($lines) ? "\n" : "") : "");
							}

							// Generally split stdout and stderr on newlines.
							if (strpos($pinfo["stderr"], "\n") !== false && ($pinfo["eol"] || strpos($pinfo["stdout"], "\n") !== false))
							{
								if (!$pinfo["eol"])
								{
									$pos = strpos($pinfo["stdout"], "\n") + 1;
									$pinfo["outdata"] .= substr($pinfo["stdout"], 0, $pos);
									$pinfo["stdout"] = substr($pinfo["stdout"], $pos);
								}

								$pos = strrpos($pinfo["stderr"], "\n") + 1;
								$pinfo["outdata"] .= substr($pinfo["stderr"], 0, $pos);
								$pinfo["stderr"] = substr($pinfo["stderr"], $pos);
							}

							$pinfo["outdata"] .= $pinfo["stdout"];
							$pinfo["eol"] = (substr($pinfo["outdata"], -1) === "\n");
							$pinfo["stdout"] = "";

							$timeout = 0;
						}
					}

					// Handle stderr.
					if (isset($pinfo["pipes"][2]))
					{
						$data = @fread($pinfo["pipes"][2], 65536);

						XCronHelper::$em->Fire("proc_read_stderr_raw", array(&$pinfo, &$data));

						if ($data === false || ($data === "" && feof($pinfo["pipes"][2])))
						{
							fclose($pinfo["pipes"][2]);

							unset($pinfo["pipes"][2]);

							if ($pinfo["stderr"] !== "" && substr($pinfo["stderr"], -1) !== "\n")  $pinfo["stderr"] .= "\n";
						}
						else if ($data !== "")
						{
							$pinfo["bytesread"] += strlen($data);

							// Flush stderr as needed.
							if (strlen($pinfo["stderr"]) > 1024 && strpos($pinfo["stderr"], "\n") !== false)
							{
								$pos = strrpos($pinfo["stderr"], "\n") + 1;
								$pinfo["outdata"] .= substr($pinfo["stderr"], 0, $pos);
								$pinfo["stderr"] = substr($pinfo["stderr"], $pos);
							}
							else if (strlen($pinfo["stderr"]) > 32768)
							{
								$pinfo["outdata"] .= $pinfo["stderr"];
								$pinfo["outdata"] .= "\n";
								$pinfo["stderr"] = "";
							}

							$pinfo["stderr"] .= $data;

							if ($pinfo["eol"] && strpos($pinfo["stderr"], "\n") !== false)
							{
								$pos = strrpos($pinfo["stderr"], "\n") + 1;
								$pinfo["outdata"] .= substr($pinfo["stderr"], 0, $pos);
								$pinfo["stderr"] = substr($pinfo["stderr"], $pos);
							}

							if ($pinfo["result"]["success"])
							{
								if (isset($cachedata["schedules"][$schedulekey][$name]["schedule"]["stderr_error"]) && !$cachedata["schedules"][$schedulekey][$name]["schedule"]["stderr_error"])  $pinfo["result"]["stderr_warn"] = "Process sent output on stderr.";
								else  $pinfo["result"] = array("success" => false, "error" => "Process sent output on stderr.", "errorcode" => "stderr");
							}

							$timeout = 0;
						}
					}

					// Finalize data.
					if (!count($pinfo["pipes"]))
					{
						if ($pinfo["stderr"] !== "")
						{
							$pinfo["outdata"] .= $pinfo["stderr"];
							$pinfo["stderr"] = "";
						}

						if ($pinfo["stdout"] !== "")
						{
							$pinfo["outdata"] .= $pinfo["stdout"];
							$pinfo["stdout"] = "";
						}
					}

					// Send data to the output file and process monitors.
					if ($pinfo["outpos"] < strlen($pinfo["outdata"]))
					{
						$data = substr($pinfo["outdata"], $pinfo["outpos"]);
						$pinfo["eol"] = (substr($data, -1) === "\n");

						XCronHelper::$em->Fire("proc_read_data", array(&$pinfo, &$data));

						if ($pinfo["outputfile"] !== false)
						{
							fseek($fpcache[$pinfo["outputfile"]]["fp"], 0, SEEK_END);
							fwrite($fpcache[$pinfo["outputfile"]]["fp"], $data);

							// Queue data if the client write buffer is empty.
							foreach ($pinfo["monitors"] as $cid => $fids)
							{
								$client = $gs->GetClient($cid);

								if ($client !== false)
								{
									foreach ($fids as $fid => $val)
									{
										if ($client->writedata === "")
										{
											if (isset($client->appdata["files"][$fid]))  $client->appdata["files"][$fid]["pos"] += strlen($data);

											$result2 = array(
												"success" => true,
												"monitor" => "output",
												"id" => $fid,
												"data" => base64_encode($data)
											);

											$client->writedata .= json_encode($result2, JSON_UNESCAPED_SLASHES) . "\n";

											$gs->UpdateClientState($cid);

											break;
										}
									}
								}
							}
						}
						else if (count($pinfo["monitors"]))
						{
							$result2 = array(
								"success" => true,
								"monitor" => "output",
								"id" => 0,
								"data" => base64_encode($data)
							);

							// Queue data only if the client write buffer has space.
							foreach ($pinfo["monitors"] as $cid => $fids)
							{
								$client = $gs->GetClient($cid);

								if ($client !== false)
								{
									foreach ($fids as $fid => $val)
									{
										if (strlen($client->writedata) < 32768)
										{
											$result2["id"] = $fid;

											$client->writedata .= json_encode($result2, JSON_UNESCAPED_SLASHES) . "\n";

											$gs->UpdateClientState($cid);
										}
									}
								}
							}
						}

						// Preserve about 32KB in RAM for new monitors if there is no output file.
						if ($pinfo["outputfile"] !== false)  $pinfo["outdata"] = "";
						else if (strlen($pinfo["outdata"]) > 65536)
						{
							$pos = strrpos($pinfo["outdata"], "\n", 32768);
							if ($pos === false)  $pos = 32767;

							$pinfo["outdata"] = substr($pinfo["outdata"], $pos + 1);
						}

						$pinfo["outpos"] = strlen($pinfo["outdata"]);
					}

					// Handle process exit.
					if (!count($pinfo["pipes"]))
					{
						$psinfo = @proc_get_status($pinfo["proc"]);
						if (!$psinfo["running"])
						{
							// Remove this process from the queue.
							unset($procs[$schedulekey][$name][$pid]);
							if (!count($procs[$schedulekey][$name]))
							{
								unset($procs[$schedulekey][$name]);

								if (!count($procs[$schedulekey]))  unset($procs[$schedulekey]);
							}

							unset($procmap[$pinfo["xcronid"]]);

							if ($pinfo["outputfile"] !== false)
							{
								$fpcache[$pinfo["outputfile"]]["refs"]--;

								if ($fpcache[$pinfo["outputfile"]]["refs"] < 1)
								{
									fclose($fpcache[$pinfo["outputfile"]]["fp"]);

									unset($fpcache[$pinfo["outputfile"]]);
								}
							}

							$timeout = 0;

							// Process last line of output.
							$data = @json_decode($pinfo["lastline"], true);
							if (is_array($data) && isset($data["success"]))
							{
								if (isset($pinfo["result"]["stderr_warn"]))  $data["stderr_warn"] = $pinfo["result"]["stderr_warn"];

								$pinfo["result"] = $data;
							}

							$pinfo["result"]["exit_code"] = $psinfo["exitcode"];

							$result2 = $pinfo["result"];

							$result3 = array(
								"monitor" => "schedule",
								"type" => "proc_done",
								"schedule" => $schedulekey,
								"schedule_disp" => XCronHelper::GetUserDisplayName($schedules, $schedulekey),
								"name" => $name,
								"pid" => $pid,
								"proc_info" => array(
									"id" => $pinfo["xcronid"],
									"cmd" => $pinfo["nextcmd"],
									"start_ts" => $pinfo["startts"],
									"true_start_ts" => $pinfo["truestartts"],
									"triggered" => $pinfo["triggered"]
								)
							);

							foreach ($result3 as $key => $val)  $result2[$key] = $val;

							// Note that this event does not mean the job is done running since there can be multiple commands.
							XCronHelper::$em->Fire("schedule_proc_done", array(&$pinfo, &$psinfo, $result2));

							proc_close($pinfo["proc"]);

							$procready = true;

							if (isset($cachedata["schedules"][$schedulekey][$name]))
							{
								$sinfo = &$cachedata["schedules"][$schedulekey][$name];

								// Handle custom stats.
								if (isset($pinfo["result"]["stats"]) && is_array($pinfo["result"]["stats"]))
								{
									$mostkeymap = array();
									foreach ($pinfo["result"]["stats"] as $key => $val)  $mostkeymap[$key] = "most_" . $key;

									$pinfo["result"]["stats"]["returned_stats"] = 1;

									XCronHelper::AddStatsResult($cachedata, $schedulekey, $name, $pinfo["result"]["stats"], $mostkeymap);
								}

								// Handle replacement schedule.
								if (isset($pinfo["result"]["update_schedule"]) && (is_string($pinfo["result"]["update_schedule"]) || is_array($pinfo["result"]["update_schedule"]) || is_int($pinfo["result"]["update_schedule"]) || is_bool($pinfo["result"]["update_schedule"])))
								{
									if (is_array($pinfo["result"]["update_schedule"]))
									{
										// Don't allow dependencies to be altered.
										unset($pinfo["result"]["update_schedule"]["depends_on"]);

										$pinfo["result"]["update_schedule"] += $sinfo["schedule"];

										$warnings = array();
										$allusers = (($windows && $schedules[$schedulekey]["elevated"]) || (!$windows && $schedules[$schedulekey]["user"] === "root"));

										$result = XCronHelper::ValidateSchedule($warnings, $schedules[$schedulekey], $name, $pinfo["result"]["update_schedule"], $allusers);
										if (!$result["success"])  XCronHelper::NotifyScheduleResult($notifiers, $cachedata, $schedules, $schedulekey, $name, $result);
										else
										{
											if (count($warnings))  XCronHelper::NotifyScheduleResult($notifiers, $cachedata, $schedules, $schedulekey, $name, array("success" => false, "error" => "The replacement schedule produced warnings.", "errorcode" => "replacement_schedule_warnings", "info" => implode("\n", $warnings)));

											$sinfo["schedule"] = $pinfo["result"]["update_schedule"];

											unset($sinfo["times"]);
										}
									}
									else if (is_string($pinfo["result"]["update_schedule"]))
									{
										$schedule = array();
										if (isset($sinfo["schedule"]["tz"]))  $schedule["tz"] = $sinfo["schedule"]["tz"];
										if (isset($sinfo["schedule"]["base_weekday"]))  $schedule["startweekday"] = trim($sinfo["schedule"]["base_weekday"]);

										$result = XCronHelper::MakeCalendarEvent($schedule);
										if (!$result["success"])  XCronHelper::NotifyScheduleResult($notifiers, $cachedata, $schedules, $schedulekey, $name, $result);
										else
										{
											$sinfo["schedule"]["schedule"] = $pinfo["result"]["update_schedule"];

											unset($sinfo["times"]);
										}
									}
									else
									{
										$sinfo["schedule"]["schedule"] = ($pinfo["result"]["update_schedule"] === true ? time() : $pinfo["result"]["update_schedule"]);

										unset($sinfo["times"]);
									}

									XCronHelper::$em->Fire("reloaded_schedule", array($schedulekey, $name));
								}

								unset($pinfo["result"]["update_schedule"]);

								// Queue up the next command if there are multiple commands.
								if (!$pinfo["result"]["success"])  $sinfo["last_result"] = $pinfo["result"];
								else if ($pinfo["nextcmd"] < count($sinfo["schedule"]["cmds"]))
								{
									$result = XCronHelper::StartScheduleProcess($procs, $procmap, $fpcache, $schedules, $cachedata, $schedulekey, $name, $pinfo["xcronid"], $pinfo["startts"], $pinfo["nextcmd"], $pinfo["triggered"], $pinfo["data"], array("truestartts" => $pinfo["truestartts"], "alerted" => $pinfo["alerted"], "bytesread" => $pinfo["bytesread"], "outdata" => $pinfo["outdata"], "outpos" => $pinfo["outpos"], "monitors" => $pinfo["monitors"]));

									if (!$result["success"])
									{
										$sinfo["last_result"] = $result;

										$result2 = array(
											"success" => false,
											"error" => $result["error"],
											"errorcode" => $result["errorcode"],
											"monitor" => "schedule",
											"type" => "proc_start_error",
											"schedule" => $schedulekey,
											"schedule_disp" => XCronHelper::GetUserDisplayName($schedules, $schedulekey),
											"name" => $name,
											"id" => $pinfo["xcronid"],
											"ts" => $pinfo["startts"],
											"cmd" => $pinfo["nextcmd"] + 1,
											"triggered" => $pinfo["triggered"]
										);

										XCronHelper::$em->Fire("schedule_proc_start_error", array(&$sinfo, &$result, &$pinfo, $result2));
									}
									else
									{
										$pinfo2 = &$procs[$schedulekey][$name][$result["pid"]];

										XCronHelper::AddStatsResult($cachedata, $schedulekey, $name, array("cmds" => 1), array());

										$result2 = array(
											"success" => true,
											"monitor" => "schedule",
											"type" => "proc_set",
											"schedule" => $schedulekey,
											"schedule_disp" => XCronHelper::GetUserDisplayName($schedules, $schedulekey),
											"name" => $name,
											"pid" => $result["pid"],
											"proc_info" => array(
												"id" => $pinfo2["xcronid"],
												"cmd" => $pinfo2["nextcmd"],
												"start_ts" => $pinfo2["startts"],
												"true_start_ts" => $pinfo2["truestartts"],
												"triggered" => $pinfo2["triggered"]
											)
										);

										XCronHelper::$em->Fire("schedule_proc_set", array(&$sinfo, &$result, &$pinfo2, $result2));

										unset($pinfo2);
									}
								}
								else
								{
									// The last command was run.
									if (!isset($sinfo["last_result"]) || !$sinfo["last_result"]["success"])  XCronHelper::NotifyScheduleResult($notifiers, $cachedata, $schedules, $schedulekey, $name, $pinfo["result"]);

									$sinfo["last_result"] = $pinfo["result"];
								}

								$schedulenext = false;
								$result2 = false;
								if (!$sinfo["last_result"]["success"])
								{
									// The process did not complete successfully.
									XCronHelper::AddStatsResult($cachedata, $schedulekey, $name, array("runs" => 1, "triggered" => ($pinfo["triggered"] ? 1 : 0), "dates_run" => ($cachedata["stats"][$schedulekey][$name]["today"]["runs"] == 0 ? 1 : 0), "errors" => 1, "runtime" => microtime(true) - $pinfo["truestartts"]), array("runtime" => "longest_runtime"));

									XCronHelper::NotifyScheduleResult($notifiers, $cachedata, $schedules, $schedulekey, $name, $sinfo["last_result"]);

									// Store the gathered output in the correct error file.
									$errorfile = (isset($sinfo["schedule"]["output_file"]) ? $sinfo["schedule"]["output_file"] : XCronHelper::GetLogOutputFilenameBase($schedulekey, $name)) . ($pinfo["triggered"] ? ".triggered" : "") . ".err";

									XCronHelper::$em->Fire("schedule_job_failed", array($schedulekey, $name, &$pinfo, $errorfile));

									if ($pinfo["triggered"])
									{
										if (XCronHelper::InitLogOutputFile($errorfile) && @file_put_contents($errorfile, $pinfo["outdata"]) !== false && isset($fpcache[$errorfile]))
										{
											// The file is being read by someone.  Reopen the file handle.
											$fp = fopen($errorfile, "rb");

											if ($fp !== false)
											{
												fclose($fpcache[$errorfile]["fp"]);

												$fpcache[$errorfile]["fp"] = $fp;
											}
										}
									}
									else
									{
										// Copy the log file to the error log file.
										if ($pinfo["outputfile"] !== false)
										{
											if (@copy($pinfo["outputfile"], $errorfile) && XCronHelper::InitLogOutputFile($errorfile) && isset($fpcache[$errorfile]))
											{
												// The file is being read by someone.  Reopen the file handle.
												$fp = fopen($errorfile, "rb");

												if ($fp !== false)
												{
													fclose($fpcache[$errorfile]["fp"]);

													$fpcache[$errorfile]["fp"] = $fp;
												}
											}
										}

										// Attempt to reschedule if this was a scheduled process.
										if (isset($sinfo["schedule"]["retry_freq"]) && (!isset($sinfo["retries"]) || $sinfo["retries"] < count($sinfo["schedule"]["retry_freq"])))
										{
											$retries = (!isset($sinfo["retries"]) ? 0 : $sinfo["retries"]);

											$sinfo["ts"] = $ts + $sinfo["schedule"]["retry_freq"][$retries];
											$sinfo["retries"] = $retries + 1;

											if (!isset($cachedata["triggers"][$schedulekey]))  $cachedata["triggers"][$schedulekey] = array();

											$cachedata["triggers"][$schedulekey][$name] = true;

											XCronHelper::$em->Fire("reloaded_schedule", array($schedulekey, $name));

											$nextts = true;
										}
										else
										{
											if (isset($sinfo["schedule"]["retry_freq"]))  XCronHelper::NotifyScheduleResult($notifiers, $cachedata, $schedules, $schedulekey, $name, array("success" => false, "error" => "Schedule failed.  Retry limit reached.", "errorcode" => "schedule_failed"), true);

											unset($sinfo["retries"]);

											$schedulenext = true;
										}
									}

									if (!isset($sinfo["last_run"]) || $sinfo["last_run"] < $pinfo["startts"])  $sinfo["last_run"] = $pinfo["startts"];

									$result2 = $sinfo["last_result"];
								}
								else if ($pinfo["nextcmd"] >= count($sinfo["schedule"]["cmds"]))
								{
									// The last command completed running.
									XCronHelper::AddStatsResult($cachedata, $schedulekey, $name, array("runs" => 1, "triggered" => ($pinfo["triggered"] ? 1 : 0), "dates_run" => ($cachedata["stats"][$schedulekey][$name]["today"]["runs"] == 0 ? 1 : 0), "runtime" => microtime(true) - $pinfo["truestartts"]), array("runtime" => "longest_runtime"));

									if (!$pinfo["triggered"])  $schedulenext = true;
									else
									{
										$logfile = (isset($sinfo["schedule"]["output_file"]) ? $sinfo["schedule"]["output_file"] : XCronHelper::GetLogOutputFilenameBase($schedulekey, $name)) . ".triggered.log";

										if (XCronHelper::InitLogOutputFile($logfile) && @file_put_contents($logfile, $pinfo["outdata"]) !== false && isset($fpcache[$logfile]))
										{
											// The file is being read by someone.  Reopen the file handle.
											$fp = fopen($logfile, "rb");

											if ($fp !== false)
											{
												fclose($fpcache[$logfile]["fp"]);

												$fpcache[$logfile]["fp"] = $fp;
											}
										}
									}

									if (!isset($sinfo["last_success"]) || $sinfo["last_success"] < $pinfo["startts"])  $sinfo["last_success"] = $pinfo["startts"];
									if (!isset($sinfo["last_run"]) || $sinfo["last_run"] < $pinfo["startts"])  $sinfo["last_run"] = $pinfo["startts"];

									$result2 = $sinfo["last_result"];
								}

								if ($result2 !== false)
								{
									// Notify process monitors of final output and cleanup associated client.
									foreach ($pinfo["monitors"] as $cid => $fids)
									{
										$client = $gs->GetClient($cid);

										if ($client !== false)
										{
											foreach ($fids as $fid => $val)
											{
												// Send EOF to triggered process monitors.
												if ($pinfo["triggered"])
												{
													$result4 = array(
														"success" => true,
														"monitor" => "output",
														"id" => $fid,
														"eof" => true
													);

													$client->writedata .= json_encode($result4, JSON_UNESCAPED_SLASHES) . "\n";
												}
												else if (isset($client->appdata["files"][$fid]))
												{
													$client->appdata["files"][$fid]["proc"] = false;
												}
											}

											unset($client->appdata["procs"][$pinfo["xcronid"]]);

											$gs->UpdateClientState($cid);
										}
									}

									// Notify monitors of job completion.
									$result3["type"] = "job_done";

									foreach ($result3 as $key => $val)  $result2[$key] = $val;

									XCronHelper::$em->Fire("schedule_job_done", array(&$sinfo, &$pinfo, $result2));
								}

								// Schedule the next run for non-triggered schedules.
								if ($schedulenext)
								{
									if (is_string($sinfo["schedule"]["schedule"]) || is_array($sinfo["schedule"]["schedule"]))
									{
										// Use the 'times' cache to save from reparsing the schedule and regenerating a calendar.
										if (isset($sinfo["times"]) && $pinfo["startts"] >= $todayts && $pinfo["startts"] < $tomorrowts && !isset($sinfo["reload"]))
										{
											$secs = (int)date("H", $ts) * 3600 + (int)date("i", $ts) * 60 + (int)date("s", $ts);
											$newts = CalendarEvent::FindNextTriggerToday($secs, false, $sinfo["times"], "id", 1);

											if ($newts !== false)  $newts = CalendarEvent::ExpandNewTS($newts, (int)date("Y", $ts), (int)date("m", $ts), (int)date("d", $ts));

											if ($newts !== false)
											{
												$sinfo["ts"] = $newts["ts"];
											}
											else
											{
												// Regenerate the schedule if there is no new timestamp for today.
												XCronHelper::ReloadScheduleTrigger($cachedata, $schedulekey, $name, $sinfo["schedule"], $ts);
											}
										}
										else
										{
											// Fallback to regenerating the schedule.
											XCronHelper::ReloadScheduleTrigger($cachedata, $schedulekey, $name, $sinfo["schedule"], $ts);
										}
									}
									else if (is_int($sinfo["schedule"]["schedule"]) && $pinfo["startts"] < $sinfo["schedule"]["schedule"])
									{
										// The process returned a new UNIX timestamp in the future.
										$sinfo["ts"] = $sinfo["schedule"]["schedule"];

										if (isset($schedule["random_delay"]))  $sinfo["ts"] += mt_rand(0, $schedule["random_delay"]);
									}
									else
									{
										// There are no more runs possible for this schedule.
										$sinfo["ts"] = false;
									}

									if ($sinfo["ts"] === false)
									{
										unset($cachedata["triggers"][$schedulekey][$name]);

										if (!count($cachedata["triggers"][$schedulekey]))  unset($cachedata["triggers"][$schedulekey]);
									}
									else
									{
										if (!isset($cachedata["triggers"][$schedulekey]))  $cachedata["triggers"][$schedulekey] = array();

										$cachedata["triggers"][$schedulekey][$name] = true;
									}

									XCronHelper::$em->Fire("reloaded_schedule", array($schedulekey, $name));

									$nextts = true;
								}

								unset($sinfo);

								$cachedataupdated = true;
							}

//var_dump($pinfo);
						}
					}

					if (isset($cachedata["schedules"][$schedulekey][$name]))
					{
						$sinfo = &$cachedata["schedules"][$schedulekey][$name];

						// Handle alert after.
						if (!$pinfo["alerted"] && (count($pinfo["pipes"]) || $psinfo["running"]) && isset($sinfo["schedule"]["alert_after"]) && $ts - $pinfo["truestartts"] >= $sinfo["schedule"]["alert_after"])
						{
							XCronHelper::AddStatsResult($cachedata, $schedulekey, $name, array("time_alerts" => 1), array());

							$sinfo["last_result"] = array("success" => false, "error" => "Process " . $pid . " has been running for more than " . $sinfo["schedule"]["alert_after"] . " seconds.", "errorcode" => "process_time_alert");

							$cachedataupdated = true;

							XCronHelper::NotifyScheduleResult($notifiers, $cachedata, $schedules, $schedulekey, $name, $sinfo["last_result"]);

							$pinfo["alerted"] = true;
						}

						// Handle termination rules.
						if ((count($pinfo["pipes"]) || $psinfo["running"]) && isset($sinfo["schedule"]["term_after"]) && $ts - $pinfo["truestartts"] >= $sinfo["schedule"]["term_after"])
						{
							// Handle terminate after time limit.
							XCronHelper::AddStatsResult($cachedata, $schedulekey, $name, array("terminations" => 1), array());

							$cachedataupdated = true;

							$pinfo["result"] = array("success" => false, "error" => "Process " . $pid . " terminated after " . $sinfo["schedule"]["term_after"] . " seconds.", "errorcode" => "process_term_alert");

							// Prevent the last line and stdout from affecting the process result.
							$pinfo["lastline"] = "";

							if (isset($pinfo["pipes"][1]))
							{
								fclose($pinfo["pipes"][1]);

								unset($pinfo["pipes"][1]);
							}

							ProcessHelper::TerminateProcess($pid);
						}
						else if ((count($pinfo["pipes"]) || $psinfo["running"]) && isset($sinfo["schedule"]["term_output"]) && $pinfo["bytesread"] > $sinfo["schedule"]["term_output"])
						{
							// Handle terminate after output limit.
							XCronHelper::AddStatsResult($cachedata, $schedulekey, $name, array("terminations" => 1), array());

							$cachedataupdated = true;

							$pinfo["result"] = array("success" => false, "error" => "Process " . $pid . " terminated due to output exceeding " . Str::ConvertBytesToUserStr($sinfo["schedule"]["term_output"]) . ".", "errorcode" => "process_term_output");

							// Prevent the last line and stdout from affecting the process result.
							$pinfo["lastline"] = "";

							if (isset($pinfo["pipes"][1]))
							{
								fclose($pinfo["pipes"][1]);

								unset($pinfo["pipes"][1]);
							}

							ProcessHelper::TerminateProcess($pid);
						}
					}
				}
			}
		}

		$result = $gs->Wait($timeout);
		if (!$result["success"])  break;

		// Handle active clients.
		foreach ($result["clients"] as $id => $client)
		{
			if (!isset($client->appdata))
			{
				echo "Client " . $id . " connected.\n";

				$pos = strrpos($client->ipaddr, ":");
				$port = substr($client->ipaddr, $pos + 1);
				$ipaddr = substr($client->ipaddr, 0, $pos);

				$result2 = XCronHelper::GetClientTCPUser($ipaddr, $port, $serverip, $serverport);

				$client->appdata = array("user" => $result2, "close" => false, "pwonly" => false, "procs" => array(), "nextid" => 1, "files" => array());

				XCronHelper::$em->Fire("client_connected", array($client));
			}

			// Send file data when there is space to do so.
			if ($client->appdata["user"]["success"] && strlen($client->writedata) < 32768 && count($client->appdata["files"]))
			{
				foreach ($client->appdata["files"] as $fid => &$finfo)
				{
					$y = strlen($client->writedata);
					if ($y >= 65536)  break;

					$sizeleft = 65536 - $y;

					if (fseek($fpcache[$finfo["key"]]["fp"], $finfo["pos"], SEEK_SET) < 0)  $data = false;
					else  $data = fread($fpcache[$finfo["key"]]["fp"], 32768);

					if ($data != "")
					{
						$finfo["pos"] += strlen($data);

						$result2 = array(
							"success" => true,
							"monitor" => "output",
							"id" => $fid,
							"data" => base64_encode($data)
						);

						$client->writedata .= json_encode($result2, JSON_UNESCAPED_SLASHES) . "\n";

						$gs->UpdateClientState($id);
					}
					else if (!$finfo["proc"])
					{
						$fpcache[$finfo["key"]]["refs"]--;

						if ($fpcache[$finfo["key"]]["refs"] < 1)
						{
							fclose($fpcache[$finfo["key"]]["fp"]);

							unset($fpcache[$finfo["key"]]);
						}

						unset($client->appdata["files"][$fid]);

						$result2 = array(
							"success" => true,
							"monitor" => "output",
							"id" => $fid,
							"eof" => true
						);

						$client->writedata .= json_encode($result2, JSON_UNESCAPED_SLASHES) . "\n";

						$gs->UpdateClientState($id);
					}
				}
			}

			if ($client->appdata["close"])
			{
				$client->readdata = "";

				if ($client->writedata === "")  stream_socket_shutdown($client->fp, STREAM_SHUT_RDWR);
			}

			while (($pos = strpos($client->readdata, "\n")) !== false)
			{
				$data = substr($client->readdata, 0, $pos);
				$client->readdata = substr($client->readdata, $pos + 1);

				echo "Client " . $id . ", received:  " . $data . "\n";
				$data = @json_decode($data, true);
				if (!$client->appdata["user"]["success"])
				{
					$result2 = array(
						"success" => false,
						"error" => "Access denied due to internal server error.  " . $client->appdata["user"]["error"] . " (" . $client->appdata["user"]["errorcode"] . ")",
						"errorcode" => "access_denied"
					);

					$client->readdata = "";
					$client->appdata["close"] = true;

					stream_socket_shutdown($client->fp, STREAM_SHUT_RD);
				}
				else if (is_array($data) && isset($data["action"]) && is_string($data["action"]))
				{
					if ($data["action"] === "set_password_only")
					{
						// Set password-only access for the client.
						$client->appdata["pwonly"] = true;

						$result2 = array("success" => true);
					}
					else if ($data["action"] === "get_server_info" || $data["action"] === "get_schedules")
					{
						// Get server info.
						$result2 = array(
							"success" => true,
							"info" => GetServerInfo()
						);

						// Get schedules.
						if ($data["action"] === "get_schedules")
						{
							if (isset($data["stats"]) && !is_bool($data["stats"]))  $result2 = array("success" => false, "error" => "Invalid 'stats'.  Expected boolean.", "errorcode" => "invalid_stats");
							else if (isset($data["watch"]) && !is_bool($data["watch"]))  $result2 = array("success" => false, "error" => "Invalid 'watch'.  Expected boolean.", "errorcode" => "invalid_watch");
							else if (isset($data["name"]) && !is_string($data["name"]))  $result2 = array("success" => false, "error" => "Invalid 'name'.  Expected string.", "errorcode" => "invalid_name");
							else
							{
								$result2["schedules"] = array();
								$result2["procs"] = array();

								if (!isset($data["stats"]))  $data["stats"] = true;
								if ($data["stats"])  $result2["stats"] = array();

								// Normalize the user.
								if (isset($data["user"]))
								{
									if ($data["user"] === true)  $data["user"] = $client->appdata["user"]["uid"];
									else if (!is_string($data["user"]))  unset($data["user"]);
									else
									{
										$result3 = XCronHelper::GetUserInfo($data["user"]);
										if (!$result3["success"])  unset($data["user"]);
										else  $data["user"] = ($windows ? $result3["sid"] : $result3["name"]);
									}
								}

								if (isset($data["elevated"]))  $data["elevated"] = (bool)$data["elevated"];

								foreach ($cachedata["schedules"] as $schedulekey => &$namemap)
								{
									if ((!isset($data["user"]) || $schedules[$schedulekey]["user"] === $data["user"]) && (!isset($data["elevated"]) || $schedules[$schedulekey]["elevated"] === $data["elevated"]))
									{
										foreach ($namemap as $name => &$sinfo)
										{
											if (!isset($data["name"]) || $data["name"] === $name)
											{
												// Schedules.
												if (!isset($result2["schedules"][$schedulekey]))
												{
													$result2["schedules"][$schedulekey] = array(
														"" => array(
															"user" => $schedules[$schedulekey]["user"],
															"useralt" => $schedules[$schedulekey]["useralt"],
															"elevated" => $schedules[$schedulekey]["elevated"]
														)
													);
												}

												$result2["schedules"][$schedulekey][$name] = XCronHelper::GetSafeSchedule($schedules, $cachedata, $startqueuenums, $schedulekey, $name);

												// Running processes.
												if (isset($procs[$schedulekey][$name]))
												{
													if (!isset($result2["procs"][$schedulekey]))  $result2["procs"][$schedulekey] = array();

													$result2["procs"][$schedulekey][$name] = array();

													foreach ($procs[$schedulekey][$name] as $pid => &$pinfo)
													{
														$result2["procs"][$schedulekey][$name][$pid] = array(
															"id" => $pinfo["xcronid"],
															"cmd" => $pinfo["nextcmd"],
															"start_ts" => $pinfo["startts"],
															"true_start_ts" => $pinfo["truestartts"],
															"triggered" => $pinfo["triggered"]
														);
													}
												}

												// Stats.
												if ($data["stats"])
												{
													if (!isset($cachedata["stats"][$schedulekey][$name]))  XCronHelper::InitScheduleStats($cachedata, $schedulekey, $name);

													if (!isset($result2["stats"][$schedulekey]))  $result2["stats"][$schedulekey] = array();

													$csinfo = &$cachedata["stats"][$schedulekey][$name];

													$result2["stats"][$schedulekey][$name] = array(
														"keys" => array_keys($csinfo["total"]),
														"total" => array_values($csinfo["total"]),
														"boot" => array_values($csinfo["boot"]),
														"lastday" => array_values($csinfo["lastday"]),
														"today" => array_values($csinfo["today"])
													);
												}
											}
										}
									}
								}

								if (isset($data["watch"]) && $data["watch"])
								{
									if (!isset($schedulemonitors[$id]))  $schedulemonitors[$id] = array();
									$schedulemonitors[$id][] = $data;

									$result2["info"]["num_schedule_monitors"] = count($schedulemonitors);
								}
							}
						}
					}
					else if ($data["action"] === "stop_watching_schedules")
					{
						// Stop monitoring schedule changes.
						unset($schedulemonitors[$id]);

						$result2 = array("success" => true);
					}
					else if ($data["action"] === "get_xcrontab" || $data["action"] === "set_xcrontab" || $data["action"] === "reload")
					{
						// Manage xcrontab and reload schedule.
						$result2 = array("success" => true);
						if ($client->appdata["pwonly"])  $result2 = array("success" => false, "error" => "Access denied.  Action '" . $data["action"] . "' is disabled.", "errorcode" => "access_denied");
						else if (isset($data["user"]))
						{
							if (!is_string($data["user"]))  $result2 = array("success" => false, "error" => "Invalid 'user'.  Expected string.", "errorcode" => "invalid_user");
							else if (!$client->appdata["user"]["allusers"])  $result2 = array("success" => false, "error" => "Access denied.  Must be " . ($windows ? "elevated" : "root") . " to access other user xcrontabs.", "errorcode" => ($windows ? "elevation_required" : "root_required"));
							else if ($windows)  $user = $data["user"];
							else
							{
								$uinfo = posix_getpwnam($data["user"]);
								if ($uinfo === false)  $uinfo = posix_getpwuid($data["user"]);

								if ($uinfo === false)  $result2 = array("success" => false, "error" => "Unable to retrieve user information.", "errorcode" => "getpwnam_getpwuid_failed");
								else  $user = $uinfo["name"];
							}
						}
						else if ($windows)
						{
							if (!$client->appdata["user"]["allusers"] && isset($data["elevated"]) && $data["elevated"] === true)  $result2 = array("success" => false, "error" => "Access denied.  Must be elevated to access an elevated xcrontab.", "errorcode" => "elevation_required");
							else  $user = $client->appdata["user"]["uid"];
						}
						else
						{
							if ($client->appdata["user"]["name"] === false)  $result2 = array("success" => false, "error" => "Unable to retrieve user information.", "errorcode" => "getpwuid_failed");
							else  $user = $client->appdata["user"]["name"];
						}

						if ($result2["success"])
						{
							$elevated = ($windows && isset($data["elevated"]) && $data["elevated"] === true);

							$result2 = XCronHelper::GetXCrontabPathFile($user, $elevated);
							if ($result2["success"])
							{
								$path = $result2["path"];
								$filename = $result2["filename"];

								if ($data["action"] === "get_xcrontab")
								{
									// Retrieve the xcrontab file data.
									if (!file_exists($filename))  $result2["data"] = base64_encode(@file_get_contents($rootpath . "/support/xcrontab_template.txt"));
									else
									{
										$data2 = @file_get_contents($filename);
										if ($data2 === false)  $result2 = array("success" => false, "error" => "Unable to read xcrontab from '" . $filename . "'.", "errorcode" => "file_get_contents_failed");
										else  $result2["data"] = base64_encode($data2);
									}
								}
								else if ($data["action"] === "set_xcrontab")
								{
									// Store the xcrontab file data.
									if (!isset($data["data"]))  $result2 = array("success" => false, "error" => "The xcrontab 'data' is required.", "errorcode" => "data_required");
									else if (!is_string($data["data"]))  $result2 = array("success" => false, "error" => "Invalid xcrontab 'data'.  Expected string.", "errorcode" => "invalid_data");
									else
									{
										$data2 = @base64_decode($data["data"]);
										if ($data2 === false)  $result2 = array("success" => false, "error" => "Invalid xcrontab 'data'.  Unable to decode.", "errorcode" => "invalid_data");
										else
										{
											@mkdir($path, 0770, true);

											if (!file_exists($filename))  @touch($filename);
											@chmod($filename, 0600);
											if (!$windows)  @chgrp($filename, "xcrontab");

											if (@file_put_contents($filename, $data2) === false)  $result2 = array("success" => false, "error" => "Unable to write xcrontab to '" . $filename . "'.", "errorcode" => "file_put_contents_failed");
										}
									}
								}
								else
								{
									if (!file_exists($filename))  $result2 = array("success" => false, "error" => "No xcrontab found for '" . $user . "'.  Expected '" . $filename . "'.", "errorcode" => "file_not_found");
									else if (($data2 = @file_get_contents($filename)) === false)  $result2 = array("success" => false, "error" => "Unable to read '" . $filename . "'.", "errorcode" => "file_get_contents_failed");
									else
									{
										$lines = explode("\n", $data2);
										foreach ($lines as $num => $line)
										{
											$line = trim($line);

											if (substr($line, 0, 1) === "#")  $line = "";

											$lines[$num] = $line;
										}
										$data2 = implode("\n", $lines);

										$sections = parse_ini_string($data2, true, INI_SCANNER_RAW);
										if (!is_array($sections))  $result2 = array("success" => false, "error" => "Unable to parse '" . $filename . "'.", "errorcode" => "parse_ini_file_failed");
										else if (!isset($sections["Notifiers"]))  $result2 = array("success" => false, "error" => "Missing [Notifiers] section in '" . $filename . "'.", "errorcode" => "missing_notifiers_section");
										else if (!isset($sections["Schedules"]))  $result2 = array("success" => false, "error" => "Missing [Schedules] section in '" . $filename . "'.", "errorcode" => "missing_schedules_section");
										else
										{
											// At this point, nothing critical will fail but there might be warnings worth sending back.
											$result2["warnings"] = array();

											$schedulekey = $result2["user"] . ($elevated ? "|elevated" : "");

											XCronHelper::$em->Fire("rebuild_schedules_start", array($schedulekey));

											$prevnames = (isset($schedules[$schedulekey]) ? array_keys($schedules[$schedulekey]["schedules"]) : array());

											$schedules[$schedulekey] = array(
												"user" => $result2["user"],
												"useralt" => $result2["useralt"],
												"elevated" => $elevated,
												"notifiers" => array(),
												"notifiergroups" => array(),
												"schedules" => array()
											);

											// Process notifiers and notifier groups.
											foreach ($sections["Notifiers"] as $key => $notifier)
											{
												$key = UTF8::MakeValid(trim($key));

												$notifier2 = @json_decode($notifier, true);
												if (!is_array($notifier2))  $result2["warnings"][] = "Unable to decode the notifier for '" . $key . "'.  Skipping.";
												else if ($key === "")  $result2["warnings"][] = "Empty notifier name encountered.  Possibly invalid UTF-8.  Skipping.";
												else if (isset($schedules[$schedulekey]["notifiers"][$key]))  $result2["warnings"][] = "A notifier for '" . $key . "' already exists.  Skipping.";
												else if (isset($schedules[$schedulekey]["notifiergroups"][$key]))  $result2["warnings"][] = "A notifier group for '" . $key . "' already exists.  Skipping.";
												else if (substr($notifier, 0, 1) === "{")
												{
													if (!isset($notifier2["type"]))  $result2["warnings"][] = "The 'type' of notifier for '" . $key . "' is not specified.  Skipping.";
													else if (!isset($notifiers[$notifier2["type"]]))  $result2["warnings"][] = "The specified notifier type '" . $notifier2["type"] . "' for '" . $key . "' is not a valid xcron notifier type.  Skipping.";
													else
													{
														$result3 = $notifiers[$notifier2["type"]]->CheckValid($notifier2);

														if (!$result3["success"])  $result2["warnings"][] = "The notifier '" . $key . "' is invalid.  Skipping.  " . $result3["error"] . " (" . $result3["errorcode"] . (isset($result3["info"]) && is_string($result3["info"]) ? "; " . $result3["info"] : "") . ")";
														else
														{
															if (isset($result3["warning"]))  $result2["warnings"][] = "The notifier '" . $key . "' may be invalid.  " . $result3["warning"];

															$schedules[$schedulekey]["notifiers"][$key] = $notifier2;
														}
													}
												}
												else
												{
													$notifiergroups = array();
													foreach ($notifier2 as $key2)
													{
														if (isset($schedules[$schedulekey]["notifiers"][$key2]))  $notifiergroups[$key2] = true;
														else if (isset($schedules[$schedulekey]["notifiergroups"][$key2]))
														{
															foreach ($schedules[$schedulekey]["notifiergroups"][$key2] as $key3)  $notifiergroups[$key3] = true;
														}
														else
														{
															$result2["warnings"][] = "Ignoring unknown notifier '" . $key2 . "' in '" . $key . "'.";
														}
													}

													$schedules[$schedulekey]["notifiergroups"][$key] = array_keys($notifiergroups);
												}
											}

											$prevcacheschedules = (isset($cachedata["schedules"][$schedulekey]) ? $cachedata["schedules"][$schedulekey] : array());

											// Remove existing schedules and triggers from the data cache.
											unset($cachedata["triggers"][$schedulekey]);
											unset($cachedata["schedules"][$schedulekey]);

											// Process schedules.
											foreach ($sections["Schedules"] as $name => $schedule)
											{
												$name = UTF8::MakeValid(trim($name));

												if (substr($schedule, 0, 1) === "{")
												{
													$schedule2 = json_decode($schedule, true);

													if (!is_array($schedule2))
													{
														$result2["warnings"][] = "Unable to parse the schedule for '" . $name . "'.  Skipping." . (json_last_error() !== JSON_ERROR_NONE ? "  " . json_last_error_msg() . "." : "");

														continue;
													}
												}
												else
												{
													// Attempt to process as a legacy cron line.
													if (preg_match('/^(([^ ]+\s+){5})(.*)$/', $schedule, $matches))  $schedule2 = array("schedule" => trim($matches[1]), "cmd" => trim($matches[3]));
													else
													{
														$result2["warnings"][] = "Unable to parse the schedule for '" . $name . "'.  Skipping.";

														continue;
													}
												}

												if (isset($schedules[$schedulekey]["schedules"][$name]))  $result2["warnings"][] = "A schedule for '" . $name . "' already exists.  Skipping.";
												else
												{
													// Validate and adjust the schedule.
													$result3 = XCronHelper::ValidateSchedule($result2["warnings"], $schedules[$schedulekey], $name, $schedule2, $client->appdata["user"]["allusers"]);
													if (!$result3["success"])  $result2["warnings"][] = $result3["error"] . " (" . $result3["errorcode"] . ")  Skipped.";
													else
													{
														$schedules[$schedulekey]["schedules"][$name] = $schedule2;

														// Configure the first trigger.
														XCronHelper::ReloadScheduleTrigger($cachedata, $schedulekey, $name, $schedule2, $ts);

														XCronHelper::$em->Fire("reloaded_schedule", array($schedulekey, $name));
													}
												}
											}

											// Remove orphaned stats and notify schedule removal.
											if (!isset($cachedata["stats"][$schedulekey]))  $cachedata["stats"][$schedulekey] = array();

											foreach ($prevnames as $name)
											{
												if (!isset($schedules[$schedulekey]["schedules"][$name]))
												{
													unset($cachedata["stats"][$schedulekey][$name]);

													XCronHelper::$em->Fire("removed_schedule", array($schedulekey, $name));
												}
											}

											// Restore last result.
											foreach ($prevcacheschedules as $name => $prevcacheschedule)
											{
												if (isset($cachedata["schedules"][$schedulekey][$name]) && isset($prevcacheschedule["last_result"]))
												{
													$cachedata["schedules"][$schedulekey][$name]["last_result"] = $prevcacheschedule["last_result"];
												}
											}

											unset($prevcacheschedules);

											// Clean up the start queue.
											$procready = true;

											XCronHelper::$em->Fire("rebuild_schedules_done", array($schedulekey));

											XCronHelper::DisplayMessageAndLog(LOG_NOTICE, "[Reloaded] " . XCronHelper::GetUserDisplayName($schedules, $schedulekey) . (count($result2["warnings"]) ? " | Warnings:  " . count($result2["warnings"]) : ""));

											if (XCronHelper::$debug)
											{
												// Dump the updated schedules/cache data in debug mode.
												var_dump($sections);
												var_dump($schedules);
												var_dump($cachedata);
											}
											else
											{
												// Write the updated schedules to disk.
												@touch($schedulesfile);
												@chmod($schedulesfile, 0600);

												file_put_contents($schedulesfile, json_encode($schedules, JSON_UNESCAPED_SLASHES));
											}

											$nextts = true;
											$cachedataupdated = true;
										}
									}
								}
							}
						}
					}
					else if ($data["action"] === "trigger_run" || $data["action"] === "set_next_run_time" || $data["action"] === "test_notifications" || $data["action"] === "suspend_schedule" || $data["action"] === "get_run_output" || ($data["action"] === "attach_process" && isset($data["name"])) || $data["action"] === "custom_user_action")
					{
						// Handle common multi-user action logic.
						$result2 = array("success" => true);

						$pwreq = $client->appdata["pwonly"];

						if (isset($data["user"]))
						{
							if (!is_string($data["user"]))  $result2 = array("success" => false, "error" => "Invalid 'user'.  Expected string.", "errorcode" => "invalid_user");
							else
							{
								$result3 = XCronHelper::GetUserInfo($data["user"]);
								if (!$result3["success"])  $result2 = $result3;
								else
								{
									$user = ($windows ? $result3["sid"] : $result3["name"]);

									if (!$client->appdata["user"]["allusers"])  $pwreq = true;
								}
							}
						}
						else if ($windows)
						{
							$user = $client->appdata["user"]["uid"];
						}
						else
						{
							if ($client->appdata["user"]["name"] === false)  $result2 = array("success" => false, "error" => "Unable to retrieve user information.", "errorcode" => "getpwuid_failed");
							else  $user = $client->appdata["user"]["name"];
						}

						if (isset($data["elevated"]))
						{
							$data["elevated"] = (bool)$data["elevated"];

							if (!$windows || !$data["elevated"])  unset($data["elevated"]);
							else if (!$client->appdata["user"]["allusers"])  $pwreq = true;
						}

						if ($result2["success"] && $pwreq)
						{
							if (!isset($data["password"]))  $result2 = array("success" => false, "error" => "Password required.", "errorcode" => "password_required");
							else if (!is_string($data["password"]))  $result2 = array("success" => false, "error" => "Invalid 'password'.  Expected string.", "errorcode" => "invalid_password");
						}

						if ($result2["success"])
						{
							$schedulekey = $user . (isset($data["elevated"]) ? "|elevated" : "");

							if (!isset($data["name"]))  $result2 = array("success" => false, "error" => "Schedule 'name' required.", "errorcode" => "name_required");
							else if (!is_string($data["name"]))  $result2 = array("success" => false, "error" => "Invalid schedule 'name'.  Expected string.", "errorcode" => "invalid_name");
							else if (!isset($schedules[$schedulekey]))  $result2 = array("success" => false, "error" => "No schedules found for '" . $schedulekey . "'.", "errorcode" => "invalid_schedule");
							else if (!isset($schedules[$schedulekey]["schedules"][$data["name"]]))  $result2 = array("success" => false, "error" => "The schedule '" . $data["name"] . "' does not exist in '" . $schedulekey . "'.", "errorcode" => "schedule_not_found");
							else
							{
								$name = $data["name"];
								$sinfo = &$cachedata["schedules"][$schedulekey][$name];

								if ($pwreq && !isset($sinfo["schedule"]["password"]))  $result2 = array("success" => false, "error" => "Access denied.  The schedule '" . $name . "' does not have a password set.", "errorcode" => "access_denied");
								else if ($pwreq && Str::CTstrcmp($sinfo["schedule"]["password"], $data["password"]) == 0)  $result2 = array("success" => false, "error" => "Access denied.  Invalid password.", "errorcode" => "access_denied");
								else if ($data["action"] === "trigger_run")
								{
									// Trigger a run.
									if (!isset($data["data"]))  $data["data"] = "{}";
									if (is_string($data["data"]))  $data["data"] = trim($data["data"]);

									if ($sinfo["schedule"]["max_queue"] > -1 && (isset($startqueuenums[$schedulekey][$name]) ? $startqueuenums[$schedulekey][$name] : 0) >= $sinfo["schedule"]["max_queue"])  $result2 = array("success" => false, "error" => "Maximum queue for the schedule has been reached.", "errorcode" => "queue_limit_reached");
									else if (!is_string($data["data"]))  $result2 = array("success" => false, "error" => "Invalid extra 'data'.  Expected string.", "errorcode" => "invalid_extra_data");
									else if (strlen($data["data"]) > 16384)  $result2 = array("success" => false, "error" => "Invalid extra 'data'.  Size of data must be less than or equal to 16,384 bytes.", "errorcode" => "invalid_extra_data");
									else if (substr($data["data"], 0, 1) !== "{" || !is_array(@json_decode($data["data"], true)))  $result2 = array("success" => false, "error" => "Invalid extra 'data'.  Expected string containing a JSON object.", "errorcode" => "invalid_extra_data");
									else if (isset($data["force"]) && !is_bool($data["force"]))  $result2 = array("success" => false, "error" => "Invalid 'force'.  Expected boolean.", "errorcode" => "invalid_force");
									else if (isset($sinfo["suspend_until"]) && (!isset($data["force"]) || !$data["force"]))  $result2 = array("success" => false, "error" => "Schedule is temporarily suspended.  Use the 'force' to bypass.", "errorcode" => "schedule_suspended");
									else if (isset($data["watch"]) && !is_bool($data["watch"]))  $result2 = array("success" => false, "error" => "Invalid 'watch'.  Expected boolean.", "errorcode" => "invalid_watch");
									else
									{
										$startqueue[$nextid] = array(
											"schedulekey" => $schedulekey,
											"name" => $name,
											"triggered" => true,
											"data" => $data["data"],
											"watch" => (isset($data["watch"]) && $data["watch"] ? $id : false)
										);

										$result2 = array(
											"success" => true,
											"id" => $nextid
										);

										$nextid++;

										if (!isset($startqueuenums[$schedulekey]))  $startqueuenums[$schedulekey] = array();
										if (!isset($startqueuenums[$schedulekey][$name]))  $startqueuenums[$schedulekey][$name] = 0;
										$startqueuenums[$schedulekey][$name]++;

										$procready = true;
									}
								}
								else if ($data["action"] === "set_next_run_time")
								{
									// Set the next run time.
									if (!isset($data["ts"]))  $result2 = array("success" => false, "error" => "Timestamp 'ts' required.", "errorcode" => "ts_required");
									else if (!is_int($data["ts"]) || $data["ts"] < 1)  $result2 = array("success" => false, "error" => "Invalid timestamp 'ts'.  Expected integer.", "errorcode" => "invalid_ts");
									else if (isset($data["min_only"]) && !is_bool($data["min_only"]))  $result2 = array("success" => false, "error" => "Invalid 'min_only'.  Expected boolean.", "errorcode" => "invalid_min_only");
									else
									{
										if (!isset($sinfo["run_ts"]) || !isset($data["min_only"]) || !$data["min_only"] || $sinfo["run_ts"] > $data["ts"])
										{
											$sinfo["run_ts"] = $data["ts"];

											if (!isset($cachedata["triggers"][$schedulekey]))  $cachedata["triggers"][$schedulekey] = array();
											if (!isset($cachedata["triggers"][$schedulekey][$name]))  $cachedata["triggers"][$schedulekey][$name] = true;

											if ($nextts === false)  $nextts = true;
											else if ($nextts !== true && $nextts > $sinfo["run_ts"])  $nextts = $sinfo["run_ts"];

											$cachedataupdated = true;
										}

										$result2 = array(
											"success" => true,
											"run_ts" => $sinfo["run_ts"]
										);
									}
								}
								else if ($data["action"] === "test_notifications")
								{
									// Test schedule notifications.
									$result2 = XCronHelper::NotifyScheduleResult($notifiers, $cachedata, $schedules, $schedulekey, $data["name"], array("msg" => "This is a test notification."));

									$cachedataupdated = true;
								}
								else if ($data["action"] === "suspend_schedule")
								{
									// Suspend schedule.
									if ($pwreq)  $result2 = array("success" => false, "error" => "Access denied.  Suspending schedules for other users is not allowed.", "errorcode" => "access_denied");
									else if (!isset($data["ts"]))  $result2 = array("success" => false, "error" => "Timestamp 'ts' required.", "errorcode" => "ts_required");
									else if (!is_int($data["ts"]) || $data["ts"] < 1)  $result2 = array("success" => false, "error" => "Invalid timestamp 'ts'.  Expected integer.", "errorcode" => "invalid_ts");
									else if (isset($data["skip_missed"]) && !is_bool($data["skip_missed"]))  $result2 = array("success" => false, "error" => "Invalid 'skip_missed' option.  Expected boolean.", "errorcode" => "invalid_skip_missed");
									else
									{
										if ($data["ts"] <= $ts)
										{
											// Rewind skipped repeating schedule.
											if ($sinfo["ts"] !== false && $sinfo["ts"] > $ts)
											{
												if (is_string($sinfo["schedule"]["schedule"]) || is_array($sinfo["schedule"]["schedule"]))
												{
													// Regenerate the schedule.
													XCronHelper::ReloadScheduleTrigger($cachedata, $schedulekey, $name, $sinfo["schedule"], $ts);
												}
											}

											unset($sinfo["suspend_until"]);

											$ts2 = $ts;
										}
										else
										{
											$sinfo["suspend_until"] = $data["ts"];

											$ts2 = $data["ts"];
										}

										if (isset($data["skip_missed"]) && $data["skip_missed"])
										{
											if (is_string($sinfo["schedule"]["schedule"]) || is_array($sinfo["schedule"]["schedule"]))
											{
												// Regenerate the schedule.
												if (isset($sinfo["suspend_until"]) || $sinfo["ts"] === false || $sinfo["ts"] <= $ts2)  XCronHelper::ReloadScheduleTrigger($cachedata, $schedulekey, $name, $sinfo["schedule"], $ts2);
											}
											else if (is_int($sinfo["ts"]) && $sinfo["ts"] < $ts2)
											{
												$sinfo["ts"] = $ts2;
											}

											if (isset($sinfo["run_ts"]) && $sinfo["run_ts"] < $ts2)  $sinfo["run_ts"] = $ts2;

											// Force reload after next schedule run.
											$sinfo["reload"] = true;
										}

										XCronHelper::$em->Fire("reloaded_schedule", array($schedulekey, $name));

										$nextts = true;
										$cachedataupdated = true;

										$result2 = array(
											"success" => true
										);
									}
								}
								else if ($data["action"] === "get_run_output")
								{
									// Get last run output.
									if (isset($data["triggered"]) && !is_bool($data["triggered"]))  $result2 = array("success" => false, "error" => "Invalid 'triggered' option.  Expected boolean.", "errorcode" => "invalid_triggered");
									else if (isset($data["error_log"]) && !is_bool($data["error_log"]))  $result2 = array("success" => false, "error" => "Invalid 'error_log' option.  Expected boolean.", "errorcode" => "invalid_error_log");
									else if (isset($data["stream"]) && !is_bool($data["stream"]))  $result2 = array("success" => false, "error" => "Invalid 'stream' option.  Expected boolean.", "errorcode" => "invalid_stream");
									else
									{
										$filename = (isset($sinfo["schedule"]["output_file"]) ? $sinfo["schedule"]["output_file"] : XCronHelper::GetLogOutputFilenameBase($schedulekey, $name)) . (isset($data["triggered"]) && $data["triggered"] ? ".triggered" : "") . (isset($data["error_log"]) && $data["error_log"] ? ".err" : ".log");

										if (!file_exists($filename))  $result2 = array("success" => false, "error" => "The requested file does not exist.", "errorcode" => "file_not_found", "info" => $filename);
										else
										{
											if (!isset($fpcache[$filename]))
											{
												$fp = fopen($filename, "rb");

												if ($fp === false)  $result2 = array("success" => false, "error" => "The requested file was unable to be opened for reading.", "errorcode" => "fopen_failed", "info" => $filename);
												else  $fpcache[$filename] = array("fp" => $fp, "refs" => 0, "xcronid" => false);
											}

											if (isset($fpcache[$filename]))
											{
												if (isset($data["stream"]) && $data["stream"])
												{
													$fpcache[$filename]["refs"]++;

													$client->appdata["files"][$client->appdata["nextid"]] = array("key" => $filename, "pos" => 0, "proc" => false);

													// Attach to a currently running job if requesting non-triggered, non-error output.
													if ((!isset($data["triggered"]) || !$data["triggered"]) && (!isset($data["error_log"]) || !$data["error_log"]) && $fpcache[$filename]["xcronid"] !== false)
													{
														$pminfo = &$procmap[$fpcache[$filename]["xcronid"]];
														$pinfo = &$procs[$pminfo["schedulekey"]][$pminfo["name"]][$pminfo["pid"]];

														if (!isset($pinfo["monitors"][$id]))  $pinfo["monitors"][$id] = array();
														$pinfo["monitors"][$id][$client->appdata["nextid"]] = true;

														unset($pinfo);
														unset($pminfo);

														$client->appdata["procs"][$fpcache[$filename]["xcronid"]] = true;
														$client->appdata["files"][$client->appdata["nextid"]]["proc"] = true;
													}

													clearstatcache();

													$result2 = array(
														"success" => true,
														"file" => $filename,
														"modified_ts" => filemtime($filename),
														"file_id" => $client->appdata["nextid"]
													);

													$client->appdata["nextid"]++;
												}
												else
												{
													// Read up to 32KB off the end of the file.
													fseek($fpcache[$filename]["fp"], 0, SEEK_END);
													$size = ftell($fpcache[$filename]["fp"]);

													if ($size > 32768)  fseek($fpcache[$filename]["fp"], -32768, SEEK_END);
													else  fseek($fpcache[$filename]["fp"], 0, SEEK_SET);

													clearstatcache();

													$result2 = array(
														"success" => true,
														"file" => $filename,
														"modified_ts" => filemtime($filename),
														"data" => base64_encode(fread($fpcache[$filename]["fp"], 32768))
													);

													if ($fpcache[$filename]["refs"] < 1)
													{
														fclose($fpcache[$filename]["fp"]);

														unset($fpcache[$filename]);
													}
												}
											}
										}
									}
								}
								else if ($data["action"] === "attach_process")
								{
									// Attach to a future process.
									if (!isset($data["type"]))  $data["type"] = "any";
									$data["type"] = strtolower($data["type"]);

									if ($data["type"] !== "any" && $data["type"] !== "schedule" && $data["type"] !== "triggered")  $result2 = array("success" => false, "error" => "Invalid 'type'.  Expected 'any', 'schedule', or 'triggered'.", "errorcode" => "invalid_type");
									else if (isset($data["limit"]) && (!is_int($data["limit"]) || $data["limit"] < 1))  $result2 = array("success" => false, "error" => "Invalid 'limit'.  Expected positive integer.", "errorcode" => "invalid_limit");
									else
									{
										if (!isset($futureattachmap[$id]))  $futureattachmap[$id] = array();

										$futureattachmap[$id][] = array("schedulekey" => $schedulekey, "name" => $data["name"], "type" => $data["type"], "limit" => (isset($data["limit"]) ? $data["limit"] : 1));

										$result2 = array(
											"success" => true
										);
									}
								}
								else
								{
									// Custom user action handler.
									$result3 = XCronHelper::$em->Fire("custom_user_action", array($client, &$data));
									if (count($result3))  $result2 = $result3[0];
									else
									{
										$result2 = array(
											"success" => false,
											"error" => "Unhandled 'action'.",
											"errorcode" => "unhandled_action"
										);
									}
								}

								unset($sinfo);
							}
						}
					}
					else if ($data["action"] === "attach_process")
					{
						// Attach to a running process.
						$result2 = array("success" => true);

						$pwreq = $client->appdata["pwonly"];

						if (!isset($data["id"]) && !isset($data["pid"]))  $result2 = array("success" => false, "error" => "Process ID 'pid' or xcron job 'id' required.", "errorcode" => "pid_or_id_required");
						else if (!isset($data["id"]))
						{
							if (!is_int($data["pid"]))  $result2 = array("success" => false, "error" => "Invalid 'pid'.  Expected integer.", "errorcode" => "invalid_pid");
							else
							{
								// Find the xcron job ID associated with a PID.
								foreach ($procs as $schedulekey => &$namemap)
								{
									foreach ($namemap as $name => &$pidmap)
									{
										if (isset($pidmap[$data["pid"]]))
										{
											$data["id"] = $pidmap[$data["pid"]]["xcronid"];

											break;
										}
									}

									if (isset($data["id"]))  break;
								}

								if (!isset($data["id"]))  $result2 = array("success" => false, "error" => "Requested 'pid' does not exist.", "errorcode" => "process_not_found");
							}
						}

						if ($result2["success"])
						{
							if (!is_int($data["id"]))  $result2 = array("success" => false, "error" => "Invalid 'id'.  Expected integer.", "errorcode" => "invalid_id");
							else if (!isset($procmap[$data["id"]]))  $result2 = array("success" => false, "error" => "Requested job 'id' does not exist.", "errorcode" => "job_not_found");
							else if (!$client->appdata["user"]["allusers"])
							{
								// Check user access to the running process.
								if ($windows)
								{
									$user = $client->appdata["user"]["uid"];
								}
								else
								{
									if ($client->appdata["user"]["name"] === false)  $result2 = array("success" => false, "error" => "Unable to retrieve user information.", "errorcode" => "getpwuid_failed");
									else  $user = $client->appdata["user"]["name"];
								}

								if ($result2["success"] && $procmap[$data["id"]]["schedulekey"] !== $user)  $pwreq = true;
							}
						}

						if ($result2["success"] && $pwreq)
						{
							if (!isset($data["password"]))  $result2 = array("success" => false, "error" => "Password required.", "errorcode" => "password_required");
							else if (!is_string($data["password"]))  $result2 = array("success" => false, "error" => "Invalid 'password'.  Expected string.", "errorcode" => "invalid_password");
							else
							{
								$schedulekey = $procmap[$data["id"]]["schedulekey"];
								$name = $procmap[$data["id"]]["name"];

								if (!isset($schedules[$schedulekey]))  $result2 = array("success" => false, "error" => "No schedules found for '" . $schedulekey . "'.", "errorcode" => "invalid_schedule");
								else if (!isset($schedules[$schedulekey]["schedules"][$name]))  $result2 = array("success" => false, "error" => "The schedule '" . $name . "' does not exist in '" . $schedulekey . "'.", "errorcode" => "schedule_not_found");
								else
								{
									$sinfo = &$cachedata["schedules"][$schedulekey][$name];

									if (!isset($sinfo["schedule"]["password"]))  $result2 = array("success" => false, "error" => "Access denied.  The schedule '" . $name . "' in '" . $schedulekey . "' does not have a password set.", "errorcode" => "access_denied");
									else if (Str::CTstrcmp($sinfo["schedule"]["password"], $data["password"]) == 0)  $result2 = array("success" => false, "error" => "Access denied.  Invalid password.", "errorcode" => "access_denied");
								}
							}
						}

						// Attach the client.
						if ($result2["success"])
						{
							$pminfo = &$procmap[$data["id"]];
							$pinfo = &$procs[$pminfo["schedulekey"]][$pminfo["name"]][$pminfo["pid"]];

							if (!isset($pinfo["monitors"][$id]))  $pinfo["monitors"][$id] = array();
							$pinfo["monitors"][$id][$client->appdata["nextid"]] = true;

							$result2 = array(
								"success" => true,
								"file_id" => $client->appdata["nextid"]
							);

							if ($pinfo["outputfile"] === false)
							{
								// Send data backlog to the client.
								$result2["data"] = base64_encode($pinfo["outdata"]);
							}
							else
							{
								$client->appdata["files"][$client->appdata["nextid"]] = array("key" => $pinfo["outputfile"], "pos" => 0, "proc" => true);

								$fpcache[$pinfo["outputfile"]]["refs"]++;
							}

							unset($pinfo);
							unset($pminfo);

							$client->appdata["procs"][$data["id"]] = true;
							$client->appdata["nextid"]++;
						}
					}
					else if ($data["action"] === "detach_process")
					{
						// Detach from a running process.
						if (!isset($data["id"]))  $result2 = array("success" => false, "error" => "Job 'id' required.", "errorcode" => "id_required");
						else if (!is_int($data["id"]))  $result2 = array("success" => false, "error" => "Invalid 'id'.  Expected integer.", "errorcode" => "invalid_id");
						else if (!isset($data["file_id"]))  $result2 = array("success" => false, "error" => "A 'file_id' is required.", "errorcode" => "file_id_required");
						else if (!is_int($data["file_id"]))  $result2 = array("success" => false, "error" => "Invalid 'file_id'.  Expected integer.", "errorcode" => "invalid_file_id");
						else if (!isset($procmap[$data["id"]]))  $result2 = array("success" => false, "error" => "Requested job 'id' does not exist.", "errorcode" => "job_not_found");
						else
						{
							$pminfo = &$procmap[$data["id"]];
							$pinfo = &$procs[$pminfo["schedulekey"]][$pminfo["name"]][$pminfo["pid"]];

							if (isset($pinfo["monitors"][$id]))  unset($pinfo["monitors"][$id][$data["file_id"]]);

							unset($pinfo);
							unset($pminfo);

							if (isset($client->appdata["files"][$data["file_id"]]))
							{
								$filename = $client->appdata["files"][$data["file_id"]]["key"];

								$fpcache[$filename]["refs"]--;
								if ($fpcache[$filename]["refs"] < 1)
								{
									fclose($fpcache[$filename]["fp"]);

									unset($fpcache[$filename]);
								}

								unset($client->appdata["files"][$data["file_id"]]);
							}

							$result2 = array(
								"success" => true
							);
						}
					}
					else
					{
						// Let extensions handle custom actions.
						$result3 = XCronHelper::$em->Fire("custom_client_action", array($client, &$data));
						if (count($result3))  $result2 = $result3[0];
						else
						{
							$result2 = array(
								"success" => false,
								"error" => "Unknown 'action'.",
								"errorcode" => "unknown_action"
							);
						}
					}

					XCronHelper::$em->Fire("modify_response", array($client, &$data, &$result2));
				}
				else
				{
					$result2 = array(
						"success" => false,
						"error" => "Invalid request.",
						"errorcode" => "invalid_request"
					);

					// Disable access for malformed requests.  Prevents web browser clients from attempting to access the server.
					$client->appdata["user"] = array("success" => false, "error" => "A previous request was invalid.", "errorcode" => "invalid_request");
				}

				$client->writedata .= json_encode($result2, JSON_UNESCAPED_SLASHES) . "\n";

				$gs->UpdateClientState($id);
			}
		}

		// Do something with removed clients.
		foreach ($result["removed"] as $id => $result2)
		{
			if (isset($result2["client"]->appdata))
			{
				echo "Client " . $id . " disconnected.\n";

//				echo "Client " . $id . " disconnected.  " . $result2["client"]->recvsize . " bytes received, " . $result2["client"]->sendsize . " bytes sent.  Disconnect reason:\n";
//				echo json_encode($result2["result"], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
//				echo "\n";

				unset($schedulemonitors[$id]);
				unset($futureattachmap[$id]);

				// Cleanup process monitor references.
				foreach ($result2["client"]->appdata["procs"] as $xcronid => $val)
				{
					$pminfo = &$procmap[$xcronid];
					$pinfo = &$procs[$pminfo["schedulekey"]][$pminfo["name"]][$pminfo["pid"]];

					unset($pinfo["monitors"][$id]);

					unset($pinfo);
					unset($pminfo);
				}

				// Cleanup file cache references.
				foreach ($result2["client"]->appdata["files"] as $fid => $finfo)
				{
					$fpcache[$finfo["key"]]["refs"]--;

					if ($fpcache[$finfo["key"]]["refs"] < 1)
					{
						fclose($fpcache[$finfo["key"]]["fp"]);

						unset($fpcache[$finfo["key"]]);
					}
				}

				XCronHelper::$em->Fire("client_disconnected", array($result2["client"]));
			}
		}

		// Start queued processes.
		if ($procready && count($procmap) < $maxprocs)
		{
			do
			{
				$started = false;

				foreach ($startqueue as $sqid => &$sqinfo)
				{
					$schedulekey = $sqinfo["schedulekey"];
					$name = $sqinfo["name"];

					if (!isset($cachedata["schedules"][$schedulekey][$name]))
					{
						// Adjust the start queue count.
						$startqueuenums[$schedulekey][$name]--;

						if ($startqueuenums[$schedulekey][$name] < 1)
						{
							unset($startqueuenums[$schedulekey][$name]);

							if (!count($startqueuenums[$schedulekey]))  unset($startqueuenums[$schedulekey]);
						}
					}
					else
					{
						$sinfo = &$cachedata["schedules"][$schedulekey][$name];

						// Limit the number of running processes per schedule.
						if ($sinfo["schedule"]["max_running"] > 0 && isset($procs[$schedulekey][$name]) && count($procs[$schedulekey][$name]) >= min($sinfo["schedule"]["max_running"], $maxprocs - 1))  continue;

						unset($startqueue[$sqid]);

						// Adjust the start queue count.  This needs to be done before watcher events trigger but after limit checks.
						$startqueuenums[$schedulekey][$name]--;

						if ($startqueuenums[$schedulekey][$name] < 1)
						{
							unset($startqueuenums[$schedulekey][$name]);

							if (!count($startqueuenums[$schedulekey]))  unset($startqueuenums[$schedulekey]);
						}

						$result = XCronHelper::StartScheduleProcess($procs, $procmap, $fpcache, $schedules, $cachedata, $schedulekey, $name, $sqid, $ts, 0, $sqinfo["triggered"], $sqinfo["data"]);

						if (!$result["success"])
						{
							$sinfo["last_result"] = $result;

							XCronHelper::AddStatsResult($cachedata, $schedulekey, $name, array("errors" => 1), array());

							XCronHelper::NotifyScheduleResult($notifiers, $cachedata, $schedules, $schedulekey, $name, $result);

							$result2 = array(
								"success" => false,
								"error" => $result["error"],
								"errorcode" => $result["errorcode"],
								"monitor" => "schedule",
								"type" => "proc_start_error",
								"schedule" => $schedulekey,
								"schedule_disp" => XCronHelper::GetUserDisplayName($schedules, $schedulekey),
								"name" => $name,
								"id" => $sqid,
								"ts" => $ts,
								"cmd" => 1,
								"triggered" => $sqinfo["triggered"]
							);

							$pinfo = false;

							XCronHelper::$em->Fire("schedule_proc_start_error", array(&$sinfo, &$result, &$pinfo, $result2));

							// Reschedule the process if not triggered.
							if (!$sqinfo["triggered"])
							{
								if (!isset($cachedata["triggers"][$schedulekey]))  $cachedata["triggers"][$schedulekey] = array();

								$cachedata["triggers"][$schedulekey][$name] = true;

								if (isset($sinfo["schedule"]["retry_freq"]) && (!isset($sinfo["start_retries"]) || $sinfo["start_retries"] < count($sinfo["schedule"]["retry_freq"])))
								{
									$retries = (!isset($sinfo["start_retries"]) ? 0 : $sinfo["start_retries"]);

									$sinfo["ts"] = $ts + $sinfo["schedule"]["retry_freq"][$retries];
									$sinfo["start_retries"] = $retries + 1;
								}
								else
								{
									$sinfo["ts"] = false;
									unset($sinfo["start_retries"]);

									unset($cachedata["triggers"][$schedulekey][$name]);

									if (!count($cachedata["triggers"][$schedulekey]))  unset($cachedata["triggers"][$schedulekey]);
								}

								XCronHelper::$em->Fire("reloaded_schedule", array($schedulekey, $name));

								$nextts = true;
							}
							else if ($sqinfo["watch"] !== false)
							{
								// Alert the watch client to the failed job start.
								$client = $gs->GetClient($sqinfo["watch"]);

								if ($client !== false)
								{
									$client->writedata .= json_encode($result2, JSON_UNESCAPED_SLASHES) . "\n";

									$gs->UpdateClientState($client->id);
								}
							}
						}
						else
						{
							$pinfo = &$procs[$schedulekey][$name][$result["pid"]];

							$result2 = array(
								"success" => true,
								"monitor" => "schedule",
								"type" => "proc_set",
								"schedule" => $schedulekey,
								"schedule_disp" => XCronHelper::GetUserDisplayName($schedules, $schedulekey),
								"name" => $name,
								"pid" => $result["pid"],
								"proc_info" => array(
									"id" => $pinfo["xcronid"],
									"cmd" => $pinfo["nextcmd"],
									"start_ts" => $pinfo["startts"],
									"true_start_ts" => $pinfo["truestartts"],
									"triggered" => $pinfo["triggered"]
								)
							);

							if (!$sqinfo["triggered"])  unset($sinfo["start_retries"]);
							else if ($sqinfo["watch"] !== false)
							{
								// Attach the watch client to the newly created job.
								$client = $gs->GetClient($sqinfo["watch"]);

								if ($client !== false)
								{
									if (!isset($pinfo["monitors"][$client->id]))  $pinfo["monitors"][$client->id] = array();
									$pinfo["monitors"][$client->id][$client->appdata["nextid"]] = true;

									$client->appdata["procs"][$pinfo["xcronid"]] = true;

									$client->appdata["nextid"]++;
								}
							}

							// Attach clients to the newly created job.
							foreach ($futureattachmap as $cid => &$cfinfo)
							{
								foreach ($cfinfo as $fanum => &$fainfo)
								{
									if ($fainfo["schedulekey"] === $schedulekey && $fainfo["name"] === $name && ($fainfo["type"] === "any" || ($fainfo["type"] === "schedule" && !$pinfo["triggered"]) || ($fainfo["type"] === "triggered" && $pinfo["triggered"])))
									{
										$client = $gs->GetClient($cid);

										if ($client !== false)
										{
											if (!isset($pinfo["monitors"][$client->id]))  $pinfo["monitors"][$client->id] = array();
											$pinfo["monitors"][$client->id][$client->appdata["nextid"]] = true;

											$client->appdata["procs"][$pinfo["xcronid"]] = true;

											if ($pinfo["outputfile"] !== false)
											{
												$client->appdata["files"][$client->appdata["nextid"]] = array("key" => $pinfo["outputfile"], "pos" => 0, "proc" => true);

												$fpcache[$pinfo["outputfile"]]["refs"]++;
											}

											$client->appdata["nextid"]++;
										}

										$fainfo["limit"]--;
										if ($fainfo["limit"] < 1)
										{
											unset($cfinfo[$fanum]);

											if (!count($cfinfo))  unset($futureattachmap[$cid]);
										}
									}

									unset($fainfo);
								}

								unset($cfinfo);
							}

							XCronHelper::AddStatsResult($cachedata, $schedulekey, $name, array("cmds" => 1), array());

							XCronHelper::$em->Fire("schedule_proc_set", array(&$sinfo, &$result, &$pinfo, $result2));

							unset($pinfo);

							$started = true;
						}

						unset($sinfo);

						$cachedataupdated = true;
					}

					// Cleanup.
					unset($startqueue[$sqid]);
					unset($sqinfo);

					if (count($procmap) >= $maxprocs)  break;
				}
			} while ($started && count($procmap) < $maxprocs);

			$procready = false;

			XCronHelper::$em->Fire("proc_ready_handled", array());
		}

		// Check the status of the two service file options.
		if ($lastservicecheck <= time() - 3)
		{
			if (file_exists($stopfilename))
			{
				// Initialize termination.
				echo "Stop requested.\n";

				$running = false;
			}
			else if (file_exists($reloadfilename))
			{
				// Reload configuration and then remove reload file.
				echo "Reload config requested.\n";

				@unlink($reloadfilename);
				$running = false;
			}

			if (!$running)
			{
				// Terminate running processes.
				foreach ($procs as $schedulekey => &$namemap)
				{
					foreach ($namemap as $name => &$pidmap)
					{
						foreach ($pidmap as $pid => &$pinfo)
						{
							XCronHelper::AddStatsResult($cachedata, $schedulekey, $name, array("terminations" => 1), array());

							ProcessHelper::TerminateProcess($pid);
						}
					}
				}

				$cacheupdated = true;
			}

			if ($cachedataupdated)
			{
				// Write the cache data to disk.
				if (!XCronHelper::$debug)
				{
					@touch($cachefile);
					@chmod($cachefile, 0600);

					file_put_contents($cachefile, json_encode($cachedata, JSON_UNESCAPED_SLASHES));
				}

				$cachedataupdated = false;
			}

			XCronHelper::$em->Fire("service_check", array($lastservicecheck, $running));

			$lastservicecheck = time();
		}
	} while ($running);

	XCronHelper::$em->Fire("cleanup", array());
?>