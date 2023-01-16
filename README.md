xcron/xcrontab
==============

xcron is the souped up, modernized cron/Task Scheduler for Windows, Mac OSX, Linux, and FreeBSD server and desktop operating systems.  MIT or LGPL.

Everything you have ever desired to have in cron/Task Scheduling/Job Scheduling system software.  And then some.

xcron is the reference implementation of the [Job Scheduler Feature/Attribute/Behavior Standard (JSFABS)](docs/job-scheduler-feature-attribute-behavior-standard-jsfabs.md) and is 94.2% JSFABS-compliant.

[![Donate](https://cubiclesoft.com/res/donate-shield.png)](https://cubiclesoft.com/donate/) [![Discord](https://img.shields.io/discord/777282089980526602?label=chat&logo=discord)](https://cubiclesoft.com/product-support/github/)

Features
--------

* Runs on Windows, Mac OSX, Linux, and FreeBSD server and desktop operating systems.
* Sane job scheduling queues.  No more runaway job schedules or data corruption due to job overlap that plagues cron-based systems.
* Use JSON to define named job schedules and named notification targets in a mostly familiar crontab-like format.
* Picks up and runs missed jobs.  Also auto-delays running missed jobs near system boot.
* Can run jobs at boot and also set a minimum system uptime per job schedule.
* Supports randomized configurable delay per job schedule.
* Can set the timezone and base weekday per job schedule.
* Supports complex date shifting.  For example, "Closest weekday (M-F) to December 25th."
* Supports seconds time resolution.
* Job schedule dependencies.  A job schedule can depend on other job schedules having completed successfully.
* Automatic logging of stdout/stderr to log files per job.
* Last line JSON handling.  This innovative feature is a massive game changer.  See "Writing Scripts to Leverage xcron" below for details.
* Retry support for failed jobs with custom per retry frequencies.
* Can configure, per job:  Send alerts for jobs taking too long to run, terminate jobs after a set amount of time, and terminate jobs after a set amount of output.
* Notification support via email, Slack, and Discord.  Quickly create new notification types.
* Easily test/verify that notifications are working properly per job.
* Extend the xcron server via the included plugin system.
* Tracks 11 common statistics as well as custom statistics per job over four useful time periods:  Total, since boot, last day, and today.
* Supports triggered jobs w/ sending custom data.  The per job 'password' option allows other users on the system to trigger the job to run if they have the password.
* Easy to use API and SDK for communicating with the localhost xcron server.
* Live monitoring support.  Watch internal server changes to schedules and job output in real time.
* Retrieve output of the last run that failed even if the job has successfully run since.
* Temporarily suspend jobs.  Useful for debugging problems.
* Has a liberal open source license.  MIT or LGPL, your choice.
* Designed for relatively painless integration into your environment.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.
* And more.  xcron is 94.2% [JSFABS-compliant](docs/job-scheduler-feature-attribute-behavior-standard-jsfabs.md).

Getting Started on Mac OSX/BSD/Linux
------------------------------------

Either 'git clone' this repository or use the green "Code" button and "Download ZIP" above to obtain the latest release of this software.

xcron/xcrontab are written in PHP and therefore depend on PHP being installed on the system.  Each OS has its own quirky method of installing PHP.  Consult your favorite search engine.  Note that PHP CLI (command-line PHP) is used by xcron, not PHP CGI or PHP FPM, which are for web applications (i.e. xcron is not a web app).

xcron only runs as `root` on Unix-style OSes.  xcrontab runs as any user on the system and behaves similarly to `crontab`.

Running xcron manually at first is recommended.  In a terminal or SSH session, run something equivalent to:

```
$ sudo php xcron.php
```

If all goes well, xcron will report that the server has successfully started and is "Ready."

In another terminal or SSH session, run:

```
$ ./xcrontab -e
```

To launch xcrontab and start editing your user's xcrontab in your preferred terminal editor.  When a xcrontab does not exist, the [default xcrontab template](support/xcrontab_template.txt) is loaded.

Once you save and exit, the xcrontab is sent to xcron for evaluation and inclusion into the schedule.

Next, retrieve all schedules, running processes, and stats as xcron sees them:

```
$ ./xcrontab get-schedules "" "" "" ""
```

xcrontab is question-answer enabled, simply running it provides an interactive interface:

```
$ ./xcrontab
```

When you are ready to install xcron as a system service and have it start at boot, run the following as `root`:

```
$ sudo php xcron.php install
```

Then use your system service starter to start the system service (e.g. `service xcron start` on Debian/Ubuntu).  Some OSes may require manual installation or other intervention to get it running properly.

Once xcron is installed system-wide, `xcrontab` may be run from anywhere on the system.  The script is copied to `/usr/local/bin`.

Getting Started on Windows
--------------------------

Either 'git clone' this repository or use the green "Code" button and "Download ZIP" above to obtain the latest release of this software.

xcron/xcrontab are written in PHP and therefore depend on PHP being installed on the system.  Installing PHP can be tricky on Windows.  Here's a [portable version of PHP](https://github.com/cubiclesoft/portable-apache-maria-db-php-for-windows).  Or consult your favorite search engine.  Note that PHP CLI (command-line PHP) is used by xcron, not PHP CGI or PHP FPM, which are for web applications (i.e. xcron is not a web app).

Note:  xcron works but does not currently perform as well on Windows as it does on other OSes due to how Windows functions.  The primary target system for xcron is Linux but was developed mostly on a Windows machine.

xcron only runs as `NT AUTHORITY\SYSTEM` on Windows.  xcrontab runs as any user on the system and behaves similarly to crontab on Unix-style OSes.

Running xcron manually at first is recommended.

On Windows, first run the included `system_cmd.bat` file to launch a Command Prompt (cmd.exe) as `NT AUTHORITY\SYSTEM`.  Note that this step requires elevation as an Administrator.

Then run:

```
C:\xcron>php xcron.php
```

If all goes well, xcron will report that the server has successfully started and is "Ready."

In another Command Prompt, run:

```
C:\xcron>php xcrontab.php -e
```

To launch xcrontab and start editing your user's xcrontab in Notepad.  When a xcrontab does not exist, the [default xcrontab template](support/xcrontab_template.txt) is loaded.

Once you save and exit, the xcrontab is sent to xcron for evaluation and inclusion into the schedule.

Windows can have separate xcrontab files for non-elevated vs. elevated users.  To edit the elevated xcrontab for a user, start an Administrator Command Prompt and run:

```
C:\xcron>php xcrontab.php -e -win_elevated
```

Processes run from the elevated xcrontab will run with a High Integrity Level (aka elevated).

Next, retrieve all schedules, running processes, and stats as xcron sees them:

```
C:\xcron>php xcrontab.php get-schedules "" "" "" ""
```

xcrontab is question-answer enabled, simply running it provides an interactive interface:

```
C:\xcron>php xcrontab.php
```

When you are ready to install xcron as a system service and have it start at boot, run the following from an Administrator Command Prompt (not `NT AUTHORITY\SYSTEM`):

```
C:\xcron>php xcron.php install
C:\xcron>php xcron.php start
```

The xcrontab File Format
------------------------

Each xcrontab file has a fairly simple INI-like definition with key-value pairs.  If a line starts with a semicolon (;) or a pound/hash symbol (#) as the first non-whitespace character, the entire line is treated as a comment.

Each valid entry in a xcrontab section is uniquely named.  The name can be any valid UTF-8 string.  Invalid characters and surrounding whitespace around the name are ignored.  The name is separated from the value by an equals (=) symbol.  Values are generally [JSON objects or arrays](https://www.json.org/json-en.html) with a few exceptions.

There are two main sections in each xcrontab file:  Notifiers and Schedules.

```
[Notifiers]
me = {"type": "email", "from": "info@addr.com", "to": "email@addr.com", "prefix": "[xcron] ", "options": {"usemail": true}, "error_limit": 1}

default = ["me"]
```

The Notifiers section allows for two types of notifiers:  Named notifiers and Notifier Groups.  Named notifiers define a single named target and are JSON objects.  Notifier Groups are JSON arrays of Named Notifiers and other Notifier Groups.  The Notifier Group called "default" is reserved as a default notification target if no target is defined for a job schedule.

Notifiers have optional error limiting available to limit the number of times sequential errors are sent.  This feature dramatically reduces notification flooding when error conditions arise.

By default, xcron comes with official support for three notification types:  Email, Slack, and Discord.  The [default xcrontab template](support/xcrontab_template.txt) examples contain the most commonly used options for each officially supported type.  The email notification type supports most but not all of the options from the [Ultimate Email Toolkit](https://github.com/cubiclesoft/ultimate-email).  See the section below called "Creating Custom Notifiers" to learn how to add custom notifiers.

```
[Schedules]
# Basic format:  [secs] mins hours days months weekday
# Expanded format:  secs mins hours days months weekday weekrows startdate[/dayskip[/weekskip]] enddate

#xcron_kitchen_sink = {"tz": "Asia/Tokyo", "base_weekday": "mon", "reload_at_start": true, "reload_at_boot": true, "schedule": true, "output_file": "/tmp/xcron_kitchen_sink.log", "alert_after": "30m", "term_after": "60m", "term_output": "10MB", "stderr_error": true, "notify" => ["me", "slack_alerts"], "user": "www-data", "win_elevated": false, "dir": "/var/www", "cmds": ["cmd1 args", "cmd2 args"], "env": {"APIKEY": "xyzxyzxyz"}, "random_delay": "3m", "min_uptime": "5m", "min_battery": 50, "max_cpu": 80, "max_ram": "5GB", "depends_on": ["xcron_test"], "retry_freq": "2m,5m,10m,15m,30m,60m,60m", "password": "changeme!", "max_queue": -1, "max_running": 1}

# Standard xcrontab definition.
xcron_example = {"schedule": "*/15 * * * *", "alert_after": "30m", "notify" => "me", "cmd": "cmd args"}

# Classic crontab definition.
xcron_legacy = */15 * * * * cmd args
```

The Schedules section defines the job schedules and what sequential command(s) to run.

These options are required for each job schedule:

* schedule - A string, integer, or boolean as described below.
* cmd - A string containing the command to run for the job.  One of 'cmd' or 'cmds' is required.
* cmds - An array of strings containing the sequential commands to run for the job.  One of 'cmd' or 'cmds' is required.

There are technically five different supported formats for a `schedule`:

* Basic format (string) - Classic crontab-like repeating scheduler.
* Expanded format (string) - Able to correctly address complex shifting schedules that the basic format can't handle.
* Unix timestamp (integer) - Wait until the specified Unix timestamp to run.  Mostly used for dynamic scheduling.
* true (boolean) - Run immediately with consideration for other options that affect it (e.g. `reload_at_boot`, `random_delay` and `min_uptime`).
* false (boolean) - Never run.  Mostly used for triggered and dynamic schedules.

The other options from the `xcron_kitchen_sink` above have a wide range of capabilities per schedule:

* tz - A string containing the PHP timezone to use.
* base_weekday - A string containing the first day of the week.  Useful for `weekrows` and `weekskip` schedule format options.
* reload_at_start - A boolean that indicates whether or not to reload the schedule every time xcron starts (Default is false).  Useful for schedules that do not need to run if they were missed.
* reload_at_boot - A boolean that indicates whether or not to reload the schedule every time the system boots (Default is false).  Useful for schedules that need to run some amount of time after the system boots up.
* output_file - A string containing a filename to write the output of the program to instead of the xcron default location.  Must be elevated/root to use this option.  Generally not necessary to be used.
* alert_after - A string containing a time or an integer containing the number of seconds to wait before sending an alert that the job is taking too long to run.
* term_after - A string containing a time or an integer containing the number of seconds to wait before forcefully terminating the job and marking it as failed.
* term_output - A string or an integer containing an output limit in Bytes, KB, MB, GB, etc. to handle before forcefully terminating the job and marking it as failed.
* stderr_error - A boolean indicating whether or not to flag output on stderr as an error for the job if the last line of non-empty output is not a JSON object (Default is true, JSON output overrides).
* notify - A string or an array containing the Notification target(s) in the `[Notifications]` section.
* user - A string or integer containing the user to run the job as.  Must be elevated/root to use this option.  Windows only supports strings.
* win_elevated - A boolean indicating whether or not to run the job elevated.  Must be elevated to use this option.  Windows only.
* dir - A string specifying the starting directory.
* env - An object containing key-value string pairs to pass to each command.
* random_delay - A string containing a time or an integer containing the number of seconds to randomly delay each schedule start time by.
* min_uptime - A string containing a time or an integer containing the number of seconds to wait for past the boot time before running the schedule.
* min_battery, max_cpu, max_ram - CURRENTLY UNSUPPORTED.  Cross-platform performance issues need to be handled first before these three features become viable.
* depends_on - A string or an array of strings containing one or more dependencies that this job depends on.  If a dependency fails, the job will also enter a failed state until all dependencies succeed.
* retry_freq - A string containing a comma-separated list of times to wait after each failed attempt to run the job before retrying the job.
* password - A string containing a password/secret that can be used to trigger the job from another user on the system.
* max_queue - An integer containing the maximum size of the start queue for the job (Default is -1).
* max_running - An integer containing the maximum number of the job that can be running simultaneously (Default is 1).  Only affects triggered jobs.

Writing Scripts to Leverage xcron
---------------------------------

xcron has several powerful features that make writing scripts for scheduled jobs more enjoyable and also enables more efficient use of system resources.

The first major feature in xcron is how it handles output from processes.  By default, xcron automatically captures output from each running job and stores it in a log file per job.  As a result, there is no longer a need to route stdout/stderr somewhere in the job schedule itself.

By default, xcron also treats output on stderr as an error condition because that is what the stderr pipe is supposed to be used for.  However, this behavior can be overridden.  When a script returns a valid, standardized JSON object as its last line of non-empty output, xcron parses that output and overrides the default behavior.

Here's an example PHP script that returns an error condition to xcron:

```
<?php
	echo "blah blah blah\n";

	// Outputs:  {"success": false, "error": "My custom error message.", "errorcode": "custom_code"}
	echo json_encode(array("success" => false, "error" => "My custom error message.", "errorcode" => "custom_code"), JSON_UNESCAPED_SLASHES);

	echo "\n\n\n";

	exit();
?>
```

Here's an example that replaces the schedule and sets the job to run again in "current time + 3000" seconds.  It also returns the memory usage of the script and how many rows were processed as custom tracked stats:

```
<?php
	echo "blah blah blah\n";

	$rowsprocessed = 15;

	// Outputs something like:  {"success": false, "update_schedule": 1893481200, "stats": {"ram": 2097152, "rows": 15}}
	echo json_encode(array("success" => true, "update_schedule" => time() + 3000, "stats" => array("ram" => memory_get_peak_usage(true), "rows" => $rowsprocessed)), JSON_UNESCAPED_SLASHES) . "\n";

	exit();
?>
```

The JSON object may contain all kinds of additional information but these options have special meaning/requirements:

* success - A boolean indicating success/failure.  Always required.
* error - A string containing a human readable error message.  Always required when `success` is false.
* errorcode - A string containing an error code associated with the message.  Always required when `success` is false.
* update_schedule - An object containing job schedule information to merge or a string, int, or boolean containing updated job schedule time information.  This key is removed from the object after processing it.
* stats - An object containing key-value pairs of custom stats to track over time.  Could be the CPU/RAM/disk I/O used, number of rows processed, or whatever else makes sense.  Each custom stat is tracked over four measured time periods:  Total, since last boot, the last day, and today.  Each custom stat is also tracked as an additive total and "most" in a single run.

Note that any last line JSON object that the process returns is not considered private information.  Do not store sensitive info in the object as the last result object can be retrieved by any user via xcrontab `get-schedules`:

```
MY-PC\Me (Elevated)

  [xcron_example]
  Queued:          0
  Next run:        February 4, 2022, 12:00:00 am
  Last run:        February 3, 2022, 11:59:23 pm
  Last success:    Never
  Schedule:        {"schedule":"* * * * *","alert_after":1800,"term_output":102400,"stderr_error":true,"notify":["all"],"retry_freq":[5,10],"max_queue":-1,"max_running":1}
  Last result:     {"success":false,"error":"My custom error message.","errorcode":"custom_code","stats":{"cpu":2.1,"ram":2097152,"returned_stats":1},"info":"whoops-here-is-my-super-secret-password!!!","exit_code":0}
  Retries:         2

                    Total    Boot     Last Day  Today
  runs              3        3        0         3
  triggered         0        0        0         0
  dates_run         1        1        0         1
  errors            3        3        0         3
  notify            4        4        0         4
  time_alerts       0        0        0         0
  terminations      0        0        0         0
  cmds              3        3        0         3
  runtime           12s      12s      0s        12s
  longest_runtime   4s       4s       0s        4s
  returned_stats    3        3        0         3
  cpu               6.3      6.3      0         6.3
  most_cpu          2.1      2.1      0         2.1
  ram               6291456  6291456  0         6291456
  most_ram          2097152  2097152  0         2097152
```

xcron passes several xcron-specific environment variables to each command for each job:

* XCRON_LAST_RESULT - A serialized JSON object containing the last result for the job.  This can be useful to pass non-private information to the next run of a job.
* XCRON_LAST_TS - An integer as a string containing the starting Unix timestamp of the last successful job run.
* XCRON_CURR_TS - An integer as a string containing the starting Unix timestamp of the current job run.  Combined with XCRON_LAST_TS, this enables efficient sequential timeline-oriented job processing without missing anything.
* XCRON_DATA - Serialized JSON.  For normal schedule job runs, this is a boolean of false.  For triggered job runs, this is a JSON object containing unsanitized user input.  XCRON_DATA is limited to 16KB in size.

Using The SDK
-------------

The included PHP SDK for communicating with the xcron service is used extensively by xcrontab.  The self-contained SDK class can be used to trigger jobs to run, adjust a future schedule, get statistics, watch process output, and much more.  The SDK can do everything that xcrontab can do but directly and slightly more efficiently than running xcrontab.

Here's an example of a xcrontab job schedule for another user (e.g. 'root'):

```
[Schedules]
run_analytics_report = {"schedule": false, "cmd": "/usr/bin/php /var/scripts/analytics_report/main.php", "password": "super-secret-password!"}
```

And a PHP script running as a different user on the system (e.g. 'www-data') that uses the xcron PHP SDK to run the triggered job above via the 'password' option:

```php
<?php
	require_once "support/sdk_xcron_server.php";

	// Connect to the xcron server.
	$xcron = new XCronServer();

	$result = $xcron->Connect();
	if (!$result["success"])
	{
		var_dump($result);
		exit();
	}

	$options = array(
		// Pass a serialized JSON encoded object to the job via the XCRON_DATA environment variable.
		// The triggered job should assume that XCRON_DATA is unsafe user input and sanitize inputs appropriately.
		"data" => json_encode(array(
			"id" => 402,
			"email" => "user@somedomain.com"
		), JSON_UNESCAPED_SLASHES),

		// The user's xcrontab.
		"user" => "root",

		// The job's password.
		"password" => "super-secret-password!"
	);

	// Trigger the job.  Note that this will only queue the job to run.
	$result = $xcron->TriggerRun("run_analytics_report", $options);

	var_dump($result);
?>
```

[View the PHP SDK documentation](docs/sdk_xcron_server.md) for more examples and details on the xcron protocol.

Creating Custom Notifiers
-------------------------

By default, xcron comes with official support for three notification types:  Email, Slack, and Discord.  It is fairly easy to add new notification types, typically only requiring around 50-100 lines of PHP code to be written.

The `notifiers` directory contains the code for each notifier.  The Slack notifier `notifiers/slack.php` is very simple in its design and therefore a good starting point when designing a new notifier.

Each notifier constructor should load all required dependencies up front rather than potentially fail later on.  Since notifiers are loaded into xcron itself, errors in custom notifier code may cause xcron to fail to load or crash the server.  Dependencies should be placed into a subdirectory in the `support` directory.

There are two required functions for each notifier:

* `CheckValid(&$notifyinfo)` - Passes in named notifier information from the xcrontab `[Notifiers]` section as an array.  This is the best opportunity to validate and adjust/normalize the notifier information.  The function must return a standard PHP array of information.  Returning an error causes the notifier to not be available.  Returning success but including a warning will bundle the warning with the list of warnings returned to xcrontab.  Note that since this is part of the main loop and there is a client waiting for a response, this code should execute quickly to avoid hanging the server.
* `Notify($notifykey, &$notifyinfo, $numerrors, &$sinfo, $schedulekey, $name, $userdisp, $data)` - This function is primarily called by `XCronHelper::NotifyScheduleResult()`.  The notifier sends the actual notification stored in the `$data` array.

The `Notify()` function parameters are:

* $notifykey - A string containing the name of the named notifier.
* $notifyinfo - The array of information for the named notifier.
* $numerrors - An integer containing the number of sequential errors that has occurred.  Most notifiers allow an `error_limit` option to limit the number of sequential errors that get sent.
* $sinfo - An array containing the original schedule information.  Rarely used.
* $schedulekey - A string containing the internal schedule key.
* $name - A string containing the job schedule name.
* $userdisp - A string containing the username for the schedule in a human-readable, OS-agnostic format.
* $data - An array containing the information to send with the notification.

Creating Custom Extensions
--------------------------

xcron comes with a powerful plugin/extension system.  Most of the behavior of xcron can be altered without modifying the core product via the use of extensions.  Extensions are not as easy to write as custom notifications but can offer laser-focused modifications.  An example of a custom extension could be sending out notifications whenever a xcrontab schedule is manually reloaded.

The `extensions` directory contains the code for each extension.  The `extensions/example.php` extension that xcron comes with is a good starting point for writing custom extensions.  Since extensions are loaded into xcron itself, errors in custom extension code may cause xcron to fail to load or crash the server.

The core design of an extension is mostly left up to each author but the example extension code makes several recommendations.  Also, xcron itself utilizes the plugin system to handle clients that are watching for schedule changes and process output for running processes.  Therefore, there are several examples available of how to handle various event callbacks.

Extensions generally will not have all of the information necessary to conduct their operations.  There are several main global variables that may be accessed but should generally be treated as read only variables:

* $schedules - An array containing the original ingested xcrontab schedule data.  There isn't a lot of reason to access this.
* $cachedata - A large array containing the processed and current schedule information, active triggers, and per-job stats.
* $procs - An array containing running process information broken down by schedule key, job name, and PID.
* $procmap - An array mapping xcron job IDs to schedule key, job name, and process ID.
* $startqueue - An array containing scheduled and triggered jobs that are ready to run in a mostly first-come, first-serve queue.
* $startqueuenums - An array containing tracking totals for items in the start queue.
* $fpcache - An array containing open file handles.  Note that the maximum number of open file handles that xcron can support varies greatly depending on OS, compile-time PHP settings, etc.  For this reason, `$fpcache` is used to assist in sharing open handles to files.

The [XCronHelper class](docs/xcron_helper.md) also has useful public static variables and static functions.

Here's an example extension to append the output of `ps` whenever a job fails on Linux hosts:

```php
<?php
	// Append the current process tree to failed job output.
	// (C) 2022 CubicleSoft.  All Rights Reserved.

	class CubicleSoft_ProcessTree
	{
		public static function ScheduleJobFailed($schedulekey, $name, &$pinfo, $errorfile)
		{
			$ps = ProcessHelper::FindExecutable("ps", "/bin");

			$cmd = escapeshellarg($ps) .  " aux --forest";

			$result = ProcessHelper::StartProcess($cmd);
			if ($result["success"])
			{
				if (XCronHelper::$debug)  echo $result["info"]["cmd"] . "\n";

				$result2 = ProcessHelper::Wait($result["proc"], $result["pipes"]);

				$pinfo["outdata"] .= "\n\n---Start of " . $ps . " output---\n" . $result2["stdout"] . $result2["stderr"] . "---End of " . $ps . " output---\n";
			}
		}
	}

	// Register to receive event notifications.
	XCronHelper::EventRegister("schedule_job_failed", "CubicleSoft_ProcessTree::ScheduleJobFailed");
?>
```

Debugging xcron/xcrontab
------------------------

When writing custom notifiers and extensions for xcron or to debug the server itself, running it in debug mode (-d) can be helpful.  The debug mode for xcron also includes setting the exact day and time to simulate, which enables seeing how a schedule will behave at unusual dates/times (e.g. DST).

For example:

```
$ sudo service xcron stop
$ sudo php xcron.php -d "2025-12-31 23:58"
```

Will start xcron in debug mode two minutes prior to the next year rolling over.  Note that xcron debug mode ignores seconds even if specified and sets the seconds to the current system clock's seconds, which enables simpler watching of the clock for when each minute rolls over.

Debug mode does not write any schedule reloads to disk (i.e. schedule changes are not permanent).  Combining debug mode with `-reset` will completely clear xcron's internal schedules to avoid weird time-based problems.

Debug mode is very verbose and displays all kinds of details including full command lines that are executed.  On Windows, debug mode can display some crazy command lines:

```
"D:\WEB\xcron\support\windows\createprocess-win.exe" /createtoken=S-1-5-21-1304824241-3403877634-2989090281-1001;S-1-5-21-1304824241-3403877634-2989090281-513:7,S-1-1-0:7,S-1-5-114:7,S-1-5-32-544:15,S-1-5-32-559:7,S-1-5-32-545:7,S-1-5-4:7,S-1-2-1:7,S-1-5-11:7,S-1-5-15:7,S-1-5-113:7,S-1-2-0:7,S-1-5-64-10:7,S-1-16-12288:96;SeIncreaseQuotaPrivilege:0,SeSecurityPrivilege:0,SeTakeOwnershipPrivilege:0,SeLoadDriverPrivilege:0,SeSystemProfilePrivilege:0,SeSystemtimePrivilege:0,SeProfileSingleProcessPrivilege:0,SeIncreaseBasePriorityPrivilege:0,SeCreatePagefilePrivilege:0,SeBackupPrivilege:0,SeRestorePrivilege:0,SeShutdownPrivilege:0,SeDebugPrivilege:0,SeSystemEnvironmentPrivilege:0,SeChangeNotifyPrivilege:3,SeRemoteShutdownPrivilege:0,SeUndockPrivilege:0,SeManageVolumePrivilege:0,SeImpersonatePrivilege:3,SeCreateGlobalPrivilege:3,SeIncreaseWorkingSetPrivilege:0,SeTimeZonePrivilege:0,SeCreateSymbolicLinkPrivilege:0,SeDelegateSessionUserImpersonatePrivilege:0;S-1-5-32-544;S-1-5-21-1304824241-3403877634-2989090281-513;D:(A;;GA;;;BA)(A;;GA;;;SY)(A;;GA;;;S-1-5-21-1304824241-3403877634-2989090281-1001);5573657233322000:0 /mergeenv /f=SW_HIDE /f=DETACHED_PROCESS /w /socketip=127.0.0.1 /socketport=59223 /sockettoken=30ccac354bbfdb2979aa24df4a7b44f314fccef1a89c1a49aa1ce1c87746a78e91b52785f629a61af7f4ff5ffec7dd044ef9833c94e3305fe6e70f0389c364fc /stdout=socket /stderr=socket D:\WEB\server2\php\php.exe D:\WEB\test.php
```

xcrontab also has a debug mode that shows the raw bytes sent to and received from the xcron server:

```
$ xcrontab -d server-info
Command:  server-info
------- RAW SEND START -------
{"action":"get_server_info"}
------- RAW SEND END -------

------- RAW RECEIVE START (321 bytes) -------
{"success":true,"info":{"server":"xcron 1.0.0","ts":1645200848,"next_ts":false,"boot_ts":1644208706,"cache_today_ts":1645167600,"today_ts":1645167600,"tomorrow_ts":1645254000,"next_id":1,"num_start_queue":0,"num_procs":0,"max_procs":30,"num_open_files":0,"num_schedule_monitors":0,"num_future_attach":0,"num_clients":1}}
------- RAW RECEIVE END -------

{
    "success": true,
    "info": {
        "server": "xcron 1.0.0",
        "ts": 1645200848,
        "next_ts": false,
        "boot_ts": 1644208706,
        "cache_today_ts": 1645167600,
        "today_ts": 1645167600,
        "tomorrow_ts": 1645254000,
        "next_id": 1,
        "num_start_queue": 0,
        "num_procs": 0,
        "max_procs": 30,
        "num_open_files": 0,
        "num_schedule_monitors": 0,
        "num_future_attach": 0,
        "num_clients": 1
    }
}
```
