<?php
	// xcrontab.
	// (C) 2022 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/support/cli.php";
	require_once $rootpath . "/support/str_basics.php";
	require_once $rootpath . "/support/process_helper.php";
	require_once $rootpath . "/support/xcron_functions.php";
	require_once $rootpath . "/support/sdk_xcron_server.php";

	// Process the command-line options.
	$options = array(
		"shortmap" => array(
			"d" => "debug",
			"e" => "edit",
			"l" => "list",
			"u" => "user",
			"s" => "suppressoutput",
			"?" => "help"
		),
		"rules" => array(
			"debug" => array("arg" => false),
			"edit" => array("arg" => false),
			"list" => array("arg" => false),
			"user" => array("arg" => true),
			"win_elevated" => array("arg" => false),
			"suppressoutput" => array("arg" => false),
			"help" => array("arg" => false)
		),
		"allow_opts_after_param" => false
	);
	$args = CLI::ParseCommandLine($options);

	if (isset($args["opts"]["help"]))
	{
		echo "xcrontab\n";
		echo "Purpose:  Edit, list, and reload schedules.  Interface with xcron.\n";
		echo "\n";
		echo "This tool is question/answer enabled.  Just running it will provide a guided interface.  It can also be run entirely from the command-line if you know all the answers.\n";
		echo "\n";
		echo "Syntax:  " . $args["file"] . " [options] [cmd [cmdoptions]]\n";
		echo "Options:\n";
		echo "\t-d              Enable debug mode.  Dumps TCP/IP communications.\n";
		echo "\t-e (-edit)      Edit the xcrontab file.\n";
		echo "\t-l (-list)      List the xcrontab file contents.\n";
		echo "\t-u (-user)      The user account to use.\n";
		echo "\t-s              Suppress most output.  Useful for capturing JSON output.\n";
		echo "\t-win_elevated   Edit/list/reference an elevated xcrontab.  Windows only.\n";
		echo "\n";
		echo "Commands:\n";
		echo "\tedit            Edit the xcrontab file.\n";
		echo "\tlist            List the xcrontab file contents.\n";
		echo "\tload            Load xcrontab file using the contents of a file.\n";
		echo "\treload          Reload schedule.\n";
		echo "\tserver-info     Dump current server info as JSON.\n";
		echo "\tget-schedules   Dump current schedules and stats as JSON.\n";
		echo "\trun             Run a schedule now.\n";
		echo "\tnext-run        Set the next run time for a schedule.\n";
		echo "\ttest-notify     Send test failure notifications for a schedule.\n";
		echo "\tsuspend         Suspend the specified schedule until a future time.\n";
		echo "\tresume          Resume the specified schedule.\n";
		echo "\tget-output      Get the last output for the specified schedule.\n";
		echo "\tget-errors      Get the last error output for the specified schedule.\n";
		echo "\tattach          Attach to a process and watch output.\n";
		echo "\n";
		echo "Examples:\n";
		echo "\tphp " . $args["file"] . "\n";
		echo "\tphp " . $args["file"] . " -e\n";
		echo "\tphp " . $args["file"] . " -l\n";
		echo "\tphp " . $args["file"] . " reload\n";

		exit();
	}

	$origargs = $args;
	$suppressoutput = (isset($args["opts"]["suppressoutput"]) && $args["opts"]["suppressoutput"]);

	XCronHelper::Init();

	if (isset($args["opts"]["debug"]) && $args["opts"]["debug"])  XCronHelper::$debug = true;

	// Determine access level.
	$result = XCronHelper::GetCurrentUserInfo();
	if (!$result["success"])  CLI::DisplayError($result["error"] . " (" . $result["errorcode"] . ")", $result["info"]);

	$windows = $result["windows"];
	$uid = $result["uid"];
	$allusers = $result["allusers"];
	$dispname = $result["name"];

	if ($windows)  $currtoken = $result["currtoken"];
	else  $gid = $result["gid"];

	if (!$windows && isset($origargs["opts"]["win_elevated"]))  unset($origargs["opts"]["win_elevated"]);

	// Convert crontab-style options to xcrontab-style commands.
	if (isset($args["opts"]["edit"]))  $args["params"] = array("edit");
	else if (isset($args["opts"]["list"]))  $args["params"] = array("list");

	// Get the command.
	$cmds = array(
		"edit" => "Edit the xcrontab file",
		"list" => "List the xcrontab file contents",
		"load" => "Load xcrontab file using the contents of a file",
		"reload" => "Reload schedule",
		"server-info" => "Dump current server info as JSON",
		"get-schedules" => "Dump current schedules and stats as JSON",
		"run" => "Run a schedule now",
		"next-run" => "Set the next run time for a schedule",
		"test-notify" => "Send test failure notifications for a schedule",
		"suspend" => "Suspend the specified schedule until a future time",
		"resume" => "Resume the specified schedule",
		"get-output" => "Get the last output for the specified schedule",
		"get-errors" => "Get the last error output for the specified schedule",
		"attach" => "Attach to a process and watch output",
	);

	$cmd = CLI::GetLimitedUserInputWithArgs($args, false, "Command", false, "Available commands:", $cmds, true, $suppressoutput);

	function GetPassword()
	{
		global $allusers, $origargs;

		if ($allusers || (!isset($origargs["opts"]["user"]) && !isset($origargs["opts"]["win_elevated"])))  return false;

		$result = getenv("XCRON_PASS");
		if ($result === false)  $result = CLI::GetUserInputWithArgs($args, "password", "Password", "", "", $suppressoutput);

		return $result;
	}

	function InitXCronSDK()
	{
		global $origargs;

		$xcron = new XCronServer();

		if (isset($origargs["opts"]["debug"]))  $xcron->SetDebug(true);

		$result = $xcron->Connect();
		if (!$result["success"])  CLI::DisplayError("Unable to connect to xcron server.", $result);

		return $xcron;
	}

	function WaitForProcessStart($xcron)
	{
		// Wait until the process starts running.
		do
		{
			$result = $xcron->Wait(30);
			if (!$result["success"] && $result["errorcode"] !== "no_data")  CLI::DisplayError("An error occurred.", $result);

			while ($result = $xcron->GetNextScheduleChange())
			{
				if (!$result["success"])  CLI::DisplayError("An error occurred.", $result);

				if ($result["type"] !== "proc_set")  CLI::DisplayError("Encountered an unexpected schedule change packet.", $result);

				echo "Started '" . $result["name"] . "' as " . $result["schedule_disp"] . "\n";
				echo "  " . date("F j, Y, g:i:s a", $result["proc_info"]["start_ts"]) . "\n";
				echo "  PID " . $result["pid"] . ", ID " . $result["proc_info"]["id"] . ", File ID " . $result["file_id"] . "\n\n";

				return;
			}
		} while (1);
	}

	function DumpProcessOutput($xcron)
	{
		// Dump output.
		do
		{
			$result = $xcron->Wait(30);
			if (!$result["success"] && $result["errorcode"] !== "no_data")  CLI::DisplayError("An error occurred.", $result);

			while ($result = $xcron->GetNextOutputData())
			{
				if (isset($result["eof"]))  return;

				echo base64_decode($result["data"]);
			}
		} while (1);
	}

	if ($cmd === "edit" || $cmd === "list" || $cmd === "load")
	{
		// Select the target user.
		if (isset($origargs["opts"]["user"]))
		{
			if (!$allusers)  CLI::DisplayError("Must be elevated to use the -u (-user) option with -e (-edit) or -l (-list).");
			else
			{
				// Determine the user ID and path to the crontab file.
				if ($windows)  $cmduser = $origargs["opts"]["user"];
				else
				{
					$uinfo = posix_getpwnam($origargs["opts"]["user"]);
					if ($uinfo === false)  $uinfo = posix_getpwuid($origargs["opts"]["user"]);
					if ($uinfo === false)  CLI::DisplayError("Unable to retrieve user information.");
				}
			}
		}
		else
		{
			if ($windows)
			{
				if (!$allusers && isset($origargs["opts"]["win_elevated"]))  CLI::DisplayError("Must be elevated to use the -win_elevated option with -e (-edit) or -l (-list).");
				else  $cmduser = $uid;
			}
			else
			{
				$uinfo = posix_getpwuid($uid);
			}
		}

		if ($cmd === "list")
		{
			// List the contents of the xcrontab file.
			$xcron = InitXCronSDK();

			$result = $xcron->GetXCrontab(($allusers && isset($origargs["opts"]["user"]) ? $origargs["opts"]["user"] : false), ($windows && $allusers && isset($origargs["opts"]["win_elevated"])));
			if (!$result["success"])  CLI::DisplayError("GetXCrontab() failed.", $result);

			echo base64_decode($result["data"]);
		}
		else if ($cmd === "load")
		{
			// Load xcrontab file using the contents of a file.
			CLI::ReinitArgs($args, array("file"));

			do
			{
				$filename = CLI::GetUserInputWithArgs($args, "file", "Filename", "", "The file to load as the xcrontab.  Will overwrite any existing xcrontab for the target user.", $suppressoutput);

				if (!file_exists($filename))  CLI::DisplayError("The filename does not exist.", false, false);
				else if (($data = @file_get_contents($filename)) === false)  CLI::DisplayError("Unable to read file contents.", false, false);
				else  break;
			} while (1);

			$xcron = InitXCronSDK();

			$result = $xcron->SetXCrontab($data, ($allusers && isset($origargs["opts"]["user"]) ? $origargs["opts"]["user"] : false), ($windows && $allusers && isset($origargs["opts"]["win_elevated"])));
			if (!$result["success"])  CLI::DisplayError("SetXCrontab() failed.", $result);

			$result = $xcron->Reload(($allusers && isset($origargs["opts"]["user"]) ? $origargs["opts"]["user"] : false), ($windows && $allusers && isset($origargs["opts"]["win_elevated"])));
			if (!$result["success"])  CLI::DisplayError("Reload() failed.", $result);

			CLI::DisplayResult($result);
		}
		else
		{
			// Edit the xcrontab file.
			$xcron = InitXCronSDK();

			$result = $xcron->GetXCrontab(($allusers && isset($origargs["opts"]["user"]) ? $origargs["opts"]["user"] : false), ($windows && $allusers && isset($origargs["opts"]["win_elevated"])));
			if (!$result["success"])  CLI::DisplayError("GetXCrontab() failed.", $result);

			$path = $result["path"];
			$filename = $result["filename"];

			$data = base64_decode($result["data"]);

			if (!$suppressoutput)  echo $filename . "\n";

			// Start editor.
			if ($windows)
			{
				// Create the file directly on Windows.
				@mkdir($path, 0770, true);

				if (!file_exists($filename))  file_put_contents($filename, $data);
				if (!file_exists($filename))  CLI::DisplayError("The xcrontab '" . $filename . "' does not exist or is inaccessible.");

				clearstatcache();
				$ts = filemtime($filename);

				// Not exactly the best option.  Some people will have a preferred editor.
				// However, there is no default CLI editor and Notepad is pretty much guaranteed to be used for one file at a time.
				$result = ProcessHelper::FindExecutable("notepad.exe");
				if ($result === false)  CLI::DisplayError("Unable to find 'notepad.exe' on the PATH.");

				$cmd = escapeshellarg($result) . " " . escapeshellarg(str_replace("/", "\\", $filename));

				$options = array(
					"createprocess_exe_opts" => ""
				);

				$result = ProcessHelper::StartProcess($cmd, $options);
				if (!$result["success"])  CLI::DisplayError("Unable to start process.", $result);

				echo "Waiting for Notepad to exit...\n";

				$result2 = ProcessHelper::Wait($result["proc"], $result["pipes"]);
			}
			else
			{
				// Attempt to find a suitable editor.
				$cmd = getenv("XCRON_EDITOR");
				if ($cmd === false || !file_exists($cmd))  $cmd = getenv("EDITOR");
				if ($cmd === false || !file_exists($cmd))  $cmd = getenv("VISUAL");
				if ($cmd === false || !file_exists($cmd))  $cmd = ProcessHelper::FindExecutable("sensible-editor", "/usr/bin");
				if ($cmd === false || !file_exists($cmd))  $cmd = ProcessHelper::FindExecutable("editor", "/usr/bin");
				if ($cmd === false || !file_exists($cmd))  $cmd = ProcessHelper::FindExecutable("nano", "/usr/bin");
				if ($cmd === false || !file_exists($cmd))  $cmd = ProcessHelper::FindExecutable("vim", "/usr/bin");
				if ($cmd === false || !file_exists($cmd))  $cmd = ProcessHelper::FindExecutable("vi", "/usr/bin");

				if ($cmd === false || !file_exists($cmd))  CLI::DisplayError("Unable to find a suitable editor.");

				// Write the data to a temporary file.
				$tempdir = str_replace("\\", "/", sys_get_temp_dir());
				if (substr($tempdir, -1) !== "/")  $tempdir .= "/";

				$filename = $tempdir . "xcrontab_temp_" . $result["useralt"] . "_" . microtime(true);
				@touch($filename);
				@chmod($filename, 0600);

				if (@file_put_contents($filename, $data) === false)  CLI::DisplayError("Unable to write to temporary file '" . $filename . "'.");

				clearstatcache();
				$ts = filemtime($filename);

				// Start the editor, piping output directly to the correct terminal.
				$ttyname = posix_ttyname(STDOUT);

				$cmd = escapeshellarg($cmd) . " " . escapeshellarg($filename) . ($ttyname !== false ? " >" . $ttyname : "");

				passthru($cmd);
			}

			$data2 = file_get_contents($filename);
			if ($data2 === false)  CLI::DisplayError("Unable to read '" . $filename . "'.");

			clearstatcache();
			$ts2 = filemtime($filename);

			// Remove temporary file.
			if (!$windows)  @unlink($filename);

			if ($data === $data2 && $ts === $ts2)
			{
				CLI::LogMessage("[Info] File was not modified.");

				exit();
			}

			$xcron = InitXCronSDK();

			if (!$windows)
			{
				$result = $xcron->SetXCrontab($data2, ($allusers && isset($origargs["opts"]["user"]) ? $origargs["opts"]["user"] : false), ($windows && $allusers && isset($origargs["opts"]["win_elevated"])));
				if (!$result["success"])  CLI::DisplayError("SetXCrontab() failed.", $result);
			}

			$result = $xcron->Reload(($allusers && isset($origargs["opts"]["user"]) ? $origargs["opts"]["user"] : false), ($windows && $allusers && isset($origargs["opts"]["win_elevated"])));
			if (!$result["success"])  CLI::DisplayError("Reload() failed.", $result);

			CLI::DisplayResult($result);
		}
	}
	else if ($cmd === "reload")
	{
		// Reload.
		$xcron = InitXCronSDK();

		$result = $xcron->Reload(($allusers && isset($origargs["opts"]["user"]) ? $origargs["opts"]["user"] : false), ($windows && $allusers && isset($origargs["opts"]["win_elevated"])));
		if (!$result["success"])  CLI::DisplayError("Reload() failed.", $result);

		CLI::DisplayResult($result);
	}
	else if ($cmd === "server-info")
	{
		// Get server info.
		$xcron = InitXCronSDK();

		$result = $xcron->GetServerInfo();
		if (!$result["success"])  CLI::DisplayError("Get server info failed.", $result);

		CLI::DisplayResult($result);
	}
	else if ($cmd === "get-schedules")
	{
		// Get schedules.
		CLI::ReinitArgs($args, array("stats", "watch", "name", "user", "elevated"));

		$stats = CLI::GetYesNoUserInputWithArgs($args, "stats", "Return stats", "Y", "", $suppressoutput);

		$watch = CLI::GetYesNoUserInputWithArgs($args, "watch", "Watch schedules", "N", "", $suppressoutput);

		$name = CLI::GetUserInputWithArgs($args, "name", "Schedule name filter", "", "", $suppressoutput);
		if ($name === "")  $name = false;

		$user = CLI::GetUserInputWithArgs($args, "user", "User filter", (isset($origargs["opts"]["user"]) ? $origargs["opts"]["user"] : ""), "Use a hyphen (-) to filter schedules for the current user (" . $dispname . ").", $suppressoutput);

		if ($user === "-")  $user = true;
		else if ($user === "")  $user = false;

		if (!$windows)  $elevated = null;
		else
		{
			$options = array(
				"Any" => "Don't filter",
				"Yes" => "Filter by elevated schedules",
				"No" => "Filter by non-elevated schedules"
			);

			$elevated = CLI::GetLimitedUserInputWithArgs($args, "elevated", "Elevated filter", "Any", "Available elevated filter options:", $options, true, $suppressoutput);

			if ($elevated === "Yes")  $elevated = true;
			else if ($elevated === "No")  $elevated = false;
			else  $elevated = null;
		}

		$xcron = InitXCronSDK();

		$result = $xcron->GetSchedules($stats, $watch, $name, $user, $elevated);
		if (!$result["success"])  CLI::DisplayError("GetSchedules() failed.", $result);

		if ($suppressoutput && !$watch)  CLI::DisplayResult($result);
		else
		{
			function DisplayServerInfo($info)
			{
				if (isset($info["server"]))                 echo "Server:              " . $info["server"] . "\n";
				if (isset($info["ts"]))                     echo "Server time:         " . date("F j, Y, g:i:s a", $info["ts"]) . "\n";
				if (isset($info["next_ts"]))                echo "Next run today:      " . (is_bool($info["next_ts"]) ? ($info["next_ts"] ? "Server recalculating" : "None") : date("F j, Y, g:i:s a", $info["next_ts"])) . "\n";
				if (isset($info["boot_ts"]))                echo "Boot:                " . date("F j, Y, g:i:s a", $info["boot_ts"]) . "\n";
				if (isset($info["cache_today_ts"]))         echo "Today (cached):      " . date("F j, Y, g:i:s a", $info["cache_today_ts"]) . "\n";
				if (isset($info["today_ts"]))               echo "Today:               " . date("F j, Y, g:i:s a", $info["today_ts"]) . "\n";
				if (isset($info["tomorrow_ts"]))            echo "Tomorrow:            " . date("F j, Y, g:i:s a", $info["tomorrow_ts"]) . "\n";
				if (isset($info["next_id"]))                echo "Next ID:             " . $info["next_id"] . "\n";
				if (isset($info["num_start_queue"]))        echo "Start queue:         " . $info["num_start_queue"] . "\n";
				if (isset($info["num_procs"]))              echo "Running processes:   " . $info["num_procs"] . "\n";
				if (isset($info["max_procs"]))              echo "Max processes:       " . $info["max_procs"] . "\n";
				if (isset($info["num_open_files"]))         echo "Open files:          " . $info["num_open_files"] . "\n";
				if (isset($info["num_schedule_monitors"]))  echo "Schedule monitors:   " . $info["num_schedule_monitors"] . "\n";
				if (isset($info["num_future_attach"]))      echo "Future attach:       " . $info["num_future_attach"] . "\n";
				if (isset($info["num_clients"]))            echo "Clients:             " . $info["num_clients"] . "\n";
			}

			DisplayServerInfo($result["info"]);

			function GetDisplayTime($secs)
			{
				$secs = (int)$secs;

				$days = (int)($secs / 86400);
				$secs %= 86400;
				$result = ($days ? $days . "d " : "");

				$hours = (int)($secs / 3600);
				$secs %= 3600;
				$result .= ($hours || $result !== "" ? $hours . "h " : "");

				$mins = (int)($secs / 60);
				$secs %= 60;
				$result .= ($mins || $result !== "" ? $mins . "m " : "");

				$result .= $secs . "s";

				return $result;
			}

			function DisplayTable($prefix, &$rows)
			{
				// Calculate column width.
				$widths = array_fill(0, count($rows[0]), 0);

				foreach ($rows as &$row)
				{
					foreach ($row as $num => $col)
					{
						$col = (string)$col;

						$y = strlen($col);
						if (!$num)  $y++;
						if ($widths[$num] < $y)  $widths[$num] = $y;
					}
				}

				foreach ($rows as &$row)
				{
					echo $prefix;

					foreach ($row as $num => $col)
					{
						$col = (string)$col;

						echo ($num ? "  " : "") . str_pad($col, $widths[$num]);
					}

					echo "\n";
				}
			}

			function DisplayScheduleHeader($name, $sinfo)
			{
				echo "  [" . $name . "]\n";
				echo "  Queued:          " . $sinfo["queued"] . "\n";
				echo "  Next run:        " . (is_bool($sinfo["next_ts"]) ? "Never" : date("F j, Y, g:i:s a", $sinfo["next_ts"])) . "\n";
				if (isset($sinfo["suspend_until_ts"]))  echo "  Suspend until:   " . date("F j, Y, g:i:s a", $sinfo["suspend_until_ts"]) . "\n";
				echo "  Last run:        " . (!isset($sinfo["last_run_ts"]) ? "Never" : date("F j, Y, g:i:s a", $sinfo["last_run_ts"])) . "\n";
				echo "  Last success:    " . (!isset($sinfo["last_success_ts"]) ? "Never" : date("F j, Y, g:i:s a", $sinfo["last_success_ts"])) . "\n";
				echo "  Schedule:        " . json_encode($sinfo["schedule"], JSON_UNESCAPED_SLASHES) . "\n";
				echo "  Last result:     " . (isset($sinfo["last_result"]) ? json_encode($sinfo["last_result"], JSON_UNESCAPED_SLASHES) : "NOT AVAILABLE") . "\n";
				if (isset($sinfo["retries"]) || isset($sinfo["start_retries"]))
				{
					echo "  Retries:         " . (isset($sinfo["retries"]) ? $sinfo["retries"] : $sinfo["start_retries"]) . "\n";
				}
			}

			function DisplayScheduleStats(&$sinfo)
			{
				$table = array(array("", "Total", "Boot", "Last Day", "Today"));
				foreach ($sinfo["keys"] as $num => $key)
				{
					if ($key === "runtime" || $key === "longest_runtime")  $table[] = array($key, GetDisplayTime($sinfo["total"][$num]), GetDisplayTime($sinfo["boot"][$num]), GetDisplayTime($sinfo["lastday"][$num]), GetDisplayTime($sinfo["today"][$num]));
					else  $table[] = array($key, $sinfo["total"][$num], $sinfo["boot"][$num], $sinfo["lastday"][$num], $sinfo["today"][$num]);
				}

				DisplayTable("  ", $table);
			}

			foreach ($result["schedules"] as $schedulekey => $namemap)
			{
				echo "\n";
				echo $namemap[""][($windows ? "useralt" : "user")] . " (" . $namemap[""][($windows ? "user" : "useralt")] . ")" . ($windows && $namemap[""]["elevated"] ? " (Elevated)" : "") . "\n";
				unset($namemap[""]);

				foreach ($namemap as $name => $sinfo)
				{
					echo "\n";
					DisplayScheduleHeader($name, $sinfo);

					if (isset($result["procs"][$schedulekey][$name]))
					{
						echo "\n";
						$table = array(array("PID", "ID", "Type", "Command", "Time"));
						foreach ($result["procs"][$schedulekey][$name] as $pid => $pinfo)
						{
							$table[] = array($pid, $pinfo["id"], ($pinfo["triggered"] ? "Trigger" : "Schedule"), $pinfo["cmd"], GetDisplayTime(microtime(true) - $pinfo["true_start_ts"]));
						}

						DisplayTable("  ", $table);
					}

					if (isset($result["stats"][$schedulekey][$name]))
					{
						echo "\n";
						DisplayScheduleStats($result["stats"][$schedulekey][$name]);
					}

					echo "\n";
				}
			}

			if ($watch)
			{
				$serverinfo = $result["info"];

				// Wait until the process starts running.
				do
				{
					$result = $xcron->Wait(30);
					if (!$result["success"] && $result["errorcode"] !== "no_data")  CLI::DisplayError("An error occurred.", $result);

					while ($result = $xcron->GetNextScheduleChange())
					{
						$output = array();

						// Most types include server info.
						ob_start();
						if (isset($result["serv_info"]))
						{
							$info = array();
							foreach ($result["serv_info"] as $key => $val)
							{
								if ($serverinfo[$key] !== $val)  $info[$key] = $val;
							}

							if (count($info) && (!$suppressoutput || $result["type"] !== "server_info"))
							{
								DisplayServerInfo($info);

								$serverinfo = $result["serv_info"];
							}
						}
						$str = ob_get_contents();
						ob_end_clean();

						if ($str !== "")  $output[] = $str;

						ob_start();
						if ($result["type"] === "proc_start_error")
						{
							echo $result["schedule_disp"] . " | " . $result["name"] . "\n\n";

							echo "Job ID " . $result["id"] . ", " . ($result["triggered"] ? "Trigger" : "Schedule") . "\n";
							echo "  Command " . $result["cmd"] . " failed to start\n";
							echo "  [Error] " . $result["error"] . " (" . $result["errorcode"] . ")\n";
						}
						else if ($result["type"] === "proc_set")
						{
							echo $result["schedule_disp"] . " | " . $result["name"] . "\n\n";

							echo "Job ID " . $result["proc_info"]["id"] . ", " . ($result["proc_info"]["triggered"] ? "Trigger" : "Schedule") . ", " . GetDisplayTime(microtime(true) - $result["proc_info"]["true_start_ts"]) . "\n";
							if ($result["proc_info"]["cmd"] == 1)  echo "  Job started\n";
							echo "  Started command " . $result["proc_info"]["cmd"] . ", PID " . $result["pid"] . "\n";
						}
						else if ($result["type"] === "proc_done")
						{
							echo $result["schedule_disp"] . " | " . $result["name"] . "\n\n";

							echo "Job ID " . $result["proc_info"]["id"] . ", " . ($result["proc_info"]["triggered"] ? "Trigger" : "Schedule") . ", " . GetDisplayTime(microtime(true) - $result["proc_info"]["true_start_ts"]) . "\n";
							echo "  Finished command " . $result["proc_info"]["cmd"] . ", PID " . $result["pid"] . "\n";
							echo "  Exit code " . $result["exit_code"] . ", " . ($result["success"] ? "SUCCESS" : "ERROR - " . $result["error"] . " (" . $result["errorcode"] . ")") . "\n";
						}
						else if ($result["type"] === "job_done")
						{
							echo $result["schedule_disp"] . " | " . $result["name"] . "\n\n";

							echo "Job ID " . $result["proc_info"]["id"] . ", " . ($result["proc_info"]["triggered"] ? "Trigger" : "Schedule") . ", " . GetDisplayTime(microtime(true) - $result["proc_info"]["true_start_ts"]) . "\n";
							echo "  Job finished\n";
							echo "  Exit code " . $result["exit_code"] . ", " . ($result["success"] ? "SUCCESS" : "ERROR - " . $result["error"] . " (" . $result["errorcode"] . ")") . "\n";
						}
						else if ($result["type"] === "reloaded_schedule")
						{
							echo $result["schedule_disp"] . "\n";

							echo "\n";
							DisplayScheduleHeader($result["name"], $result["schedule_info"]);

							if (isset($result["stats"]))
							{
								echo "\n";
								DisplayScheduleStats($result["stats"]);
							}
						}
						else if ($result["type"] === "removed_schedule")
						{
							echo $result["schedule_disp"] . " | " . $result["name"] . "\n";

							echo "Schedule removed.\n";
						}
						else if ($result["type"] === "server_info")
						{
							// This has been handled already.
						}
						else
						{
							echo "Unknown type '" . $result["type"] . "'.\n";

							echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
						}
						$str = ob_get_contents();
						ob_end_clean();

						if ($str !== "")  $output[] = $str;

						if (count($output))
						{
							echo "---\n";
							echo implode("\n", $output);
						}
					}
				} while (1);
			}
		}
	}
	else if ($cmd === "run")
	{
		// Run a schedule now.
		CLI::ReinitArgs($args, array("name", "extra", "watch", "password"));

		$name = CLI::GetUserInputWithArgs($args, "name", "Schedule name", false, "", $suppressoutput);

		do
		{
			$extra = trim(CLI::GetUserInputWithArgs($args, "extra", "Extra data", "{}", "Extra data is a JSON object that is passed to the target process as the XCRON_DATA environment variable.", $suppressoutput));

			$data = json_decode($extra, true);

			if (substr($extra, 0, 1) !== "{" || !is_array($data))  CLI::DisplayError("Invalid JSON entered.  Expected object.", false, false);
			else if (strlen($extra) > 16384)  CLI::DisplayError("JSON object is too large.  Must be less than or equal to 16,384 bytes.", false, false);
			else  break;
		} while (1);

		$watch = CLI::GetYesNoUserInputWithArgs($args, "watch", "Watch output", false, "", $suppressoutput);

		$password = GetPassword();

		$xcron = InitXCronSDK();

		$options = array();
		$options["data"] = $extra;

		// Use the 'force' to bypass suspended schedule restrictions.
		$options["force"] = true;

		if (isset($origargs["opts"]["user"]))  $options["user"] = $origargs["opts"]["user"];
		if (isset($origargs["opts"]["win_elevated"]))  $options["elevated"] = true;
		if ($password !== false)  $options["password"] = $password;
		if ($watch !== false)  $options["watch"] = true;

		$result = $xcron->TriggerRun($name, $options);
		if (!$result["success"])  CLI::DisplayError("TriggerRun() failed.", $result);

		if (!$watch)  CLI::DisplayResult($result);
		else
		{
			WaitForProcessStart($xcron);
			DumpProcessOutput($xcron);
		}
	}
	else if ($cmd === "next-run")
	{
		// Set the next run time.
		CLI::ReinitArgs($args, array("name", "ts", "min_only", "password"));

		$name = CLI::GetUserInputWithArgs($args, "name", "Schedule name", false, "", $suppressoutput);

		do
		{
			$ts = CLI::GetUserInputWithArgs($args, "ts", "Future date/time", false, "Specify a UNIX timestamp or a strtotime()-compatible string.", $suppressoutput);

			if (is_numeric($ts))  $ts = (int)$ts;
			else  $ts = strtotime($ts);

			if ($ts < 1)  CLI::DisplayError("Invalid date/time entered.", false, false);
		} while ($ts < 1);

		$minonly = CLI::GetYesNoUserInputWithArgs($args, "min_only", "Minimum timestamp only", false, "Only adjust the timestamp of the next run if it is less than a previously specified timestamp?", $suppressoutput);

		$password = GetPassword();

		$xcron = InitXCronSDK();

		$options = array();
		if (isset($origargs["opts"]["user"]))  $options["user"] = $origargs["opts"]["user"];
		if (isset($origargs["opts"]["win_elevated"]))  $options["elevated"] = true;
		if ($password !== false)  $options["password"] = $password;

		$result = $xcron->SetNextRunTime($name, $ts, $minonly, $options);
		if (!$result["success"])  CLI::DisplayError("SetNextRunTime() failed.", $result);

		CLI::DisplayResult($result);
	}
	else if ($cmd === "test-notify")
	{
		// Send test failure notifications.
		CLI::ReinitArgs($args, array("name", "password"));

		$name = CLI::GetUserInputWithArgs($args, "name", "Schedule name", false, "", $suppressoutput);

		$password = GetPassword();

		$xcron = InitXCronSDK();

		$options = array();
		if (isset($origargs["opts"]["user"]))  $options["user"] = $origargs["opts"]["user"];
		if (isset($origargs["opts"]["win_elevated"]))  $options["elevated"] = true;
		if ($password !== false)  $options["password"] = $password;

		$result = $xcron->TestNotifications($name, $options);
		if (!$result["success"])  CLI::DisplayError("TestNotifications() failed.", $result);

		CLI::DisplayResult($result);
	}
	else if ($cmd === "suspend")
	{
		// Suspend schedule temporarily.
		CLI::ReinitArgs($args, array("name", "ts", "skip_missed"));

		$name = CLI::GetUserInputWithArgs($args, "name", "Schedule name", false, "", $suppressoutput);

		do
		{
			$ts = CLI::GetUserInputWithArgs($args, "ts", "Future date/time", false, "Specify a UNIX timestamp or a strtotime()-compatible string.", $suppressoutput);

			if (is_numeric($ts))  $ts = (int)$ts;
			else  $ts = strtotime($ts);

			if ($ts < 1)  CLI::DisplayError("Invalid date/time entered.", false, false);
		} while ($ts < 1);

		$skipmissed = CLI::GetYesNoUserInputWithArgs($args, "skip_missed", "Skip missed times", "N", "Skip running missed schedule times when resuming later?", $suppressoutput);

		$xcron = InitXCronSDK();

		$options = array();
		if (isset($origargs["opts"]["user"]))  $options["user"] = $origargs["opts"]["user"];
		if (isset($origargs["opts"]["win_elevated"]))  $options["elevated"] = true;

		$result = $xcron->SuspendScheduleUntil($name, $ts, $skipmissed, $options);
		if (!$result["success"])  CLI::DisplayError("SuspendScheduleUntil() failed.", $result);

		CLI::DisplayResult($result);
	}
	else if ($cmd === "resume")
	{
		// Resume schedule.
		CLI::ReinitArgs($args, array("name"));

		$name = CLI::GetUserInputWithArgs($args, "name", "Schedule name", false, "", $suppressoutput);

		$xcron = InitXCronSDK();

		$options = array();
		if (isset($origargs["opts"]["user"]))  $options["user"] = $origargs["opts"]["user"];
		if (isset($origargs["opts"]["win_elevated"]))  $options["elevated"] = true;

		$result = $xcron->SuspendScheduleUntil($name, time() - 1, false, $options);
		if (!$result["success"])  CLI::DisplayError("SuspendScheduleUntil() failed.", $result);

		CLI::DisplayResult($result);
	}
	else if ($cmd === "get-output" || $cmd === "get-errors")
	{
		// Get last output.
		CLI::ReinitArgs($args, array("name", "triggered", "stream", "password"));

		$name = CLI::GetUserInputWithArgs($args, "name", "Schedule name", false, "", $suppressoutput);

		$triggered = CLI::GetYesNoUserInputWithArgs($args, "triggered", "Triggered run output", false, "Retrieve the output from the most recent triggered run?", $suppressoutput);
		$stream = CLI::GetYesNoUserInputWithArgs($args, "stream", "Stream/Retrieve all output", false, "When not streaming the output file, only the last 32KB of the output is returned.", $suppressoutput);

		$password = GetPassword();

		$xcron = InitXCronSDK();

		$options = array();
		if (isset($origargs["opts"]["user"]))  $options["user"] = $origargs["opts"]["user"];
		if (isset($origargs["opts"]["win_elevated"]))  $options["elevated"] = true;
		if ($password !== false)  $options["password"] = $password;

		$result = $xcron->GetRunOutput($name, $triggered, ($cmd === "get-errors"), $stream, $options);
		if (!$result["success"])  CLI::DisplayError("GetRunOutput() failed.", $result);

		echo $result["file"] . "\n";
		echo date("F j, Y, g:i:s a", $result["modified_ts"]) . "\n\n";

		if ($stream)
		{
			DumpProcessOutput($xcron);
		}
		else
		{
			echo base64_decode($result["data"]);
		}
	}
	else if ($cmd === "attach")
	{
		// Attach to a process and watch its output.
		$modes = array(
			"future" => "Attach to a future running process",
			"pid" => "Attach to a running job by process ID",
			"id" => "Attach to a running job by xcron job ID"
		);

		$mode = CLI::GetLimitedUserInputWithArgs($args, false, "Attach mode", false, "Available attach modes:", $modes, true, $suppressoutput);

		if ($mode === "future")
		{
			CLI::ReinitArgs($args, array("name", "type", "password"));

			$name = CLI::GetUserInputWithArgs($args, "name", "Schedule name", false, "", $suppressoutput);

			$types = array(
				"Any" => "Attach to the next schedule or triggered run",
				"Schedule" => "Attach to the next schedule run",
				"Triggered" => "Attach to the next triggered run"
			);

			$type = CLI::GetLimitedUserInputWithArgs($args, "type", "Type", "Any", "Available types:", $types, true, $suppressoutput);
			$type = strtolower($type);

			$password = GetPassword();

			$xcron = InitXCronSDK();

			$options = array();
			if (isset($origargs["opts"]["user"]))  $options["user"] = $origargs["opts"]["user"];
			if (isset($origargs["opts"]["win_elevated"]))  $options["elevated"] = true;
			if ($password !== false)  $options["password"] = $password;

			$result = $xcron->AttachFutureProcess($name, $type, $options);
			if (!$result["success"])  CLI::DisplayError("AttachFutureProcess() failed.", $result);

			WaitForProcessStart($xcron);
		}
		else if ($mode === "pid")
		{
			CLI::ReinitArgs($args, array("pid", "password"));

			$pid = (int)CLI::GetUserInputWithArgs($args, "pid", "Process ID", false, "", $suppressoutput);

			$password = GetPassword();

			$xcron = InitXCronSDK();

			$result = $xcron->AttachProcessByPID($pid, $password);
			if (!$result["success"])  CLI::DisplayError("AttachProcessByPID() failed.", $result);

			if (isset($result["data"]))  echo base64_decode($result["data"]);
		}
		else if ($mode === "id")
		{
			CLI::ReinitArgs($args, array("id", "password"));

			$id = (int)CLI::GetUserInputWithArgs($args, "id", "Job ID", false, "", $suppressoutput);

			$password = GetPassword();

			$xcron = InitXCronSDK();

			$result = $xcron->AttachProcessByID($id, $password);
			if (!$result["success"])  CLI::DisplayError("AttachProcessByID() failed.", $result);

			if (isset($result["data"]))  echo base64_decode($result["data"]);
		}

		DumpProcessOutput($xcron);
	}
?>