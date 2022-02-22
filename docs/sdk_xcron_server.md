XCronServer Class:  'support/sdk_xcron_server.php'
==================================================

This class communicates with the CubicleSoft xcron server to perform various tasks such as loading schedules, triggering jobs, retrieving statistics, and more using the xcron protocol.

[xcrontab](https://github.com/cubiclesoft/xcron/blob/master/xcrontab.php) uses the PHP SDK extensively.

The PHP SDK has no external dependencies.  Simply copy the `support/sdk_xcron_server.php` file to your project to communicate directly with the xcron server.  The SDK itself is only about 400 lines of code and fairly easy to follow.

Example usage:

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

The xcron Protocol
------------------

The xcron server binds to 127.0.0.1 at TCP/IP port 10829.  Any programming or scripting language that supports TCP/IP communications and [JSON](https://www.json.org/json-en.html) can communicate with xcron server using a very simple protocol.  That is, the client sends the server a single line of JSON terminated by a single newline (`\n`) and receives back JSON terminated by a single newline (`\n`).  Any encoding errors by the client will move the connection into a permanent error state and eventual termination.  This is done to prevent malicious websites from trying to deploy [drive by malware](https://en.wikipedia.org/wiki/Drive-by_download) via xcron.

Example client communication:

```
{"action":"get_server_info"}
```

Example server response:

```
{"success":true,"info":{"server":"xcron 1.0.0","ts":1645281717,"next_ts":false,"boot_ts":1644208707,"cache_today_ts":1645254000,"today_ts":1645254000,"tomorrow_ts":1645340400,"next_id":1,"num_start_queue":0,"num_procs":0,"max_procs":30,"num_open_files":0,"num_schedule_monitors":0,"num_future_attach":0,"num_clients":1}}
```

`xcrontab` in debug mode (-d) shows the JSON sent and received by the PHP SDK.

Some commands sent to the xcron server include "watch" options that will begin monitoring for specific events.  When monitoring, the xcron server will send JSON when the events being watched happen.  These JSON blobs contain a key called "monitor" and will be one of:

* schedule - A schedule monitoring update.
* output - Previously requested output from a running process being watched or a log file.  The output "data" sent is Base64 encoded to prevent encoding errors on the wire.

Monitoring packets are sent to the client as soon as there is capacity to send them, which means that responses to API calls may happen after one or more monitoring packets arrive.  In general, this won't be an issue for most applications.

The xcron server automatically terminates stagnant connections after 5 minutes.  The PHP SDK caps its internal timeout to 2 minutes and sends a single space ( ) to keep the connection alive since spaces are ignored by JSON.

Each SDK function that follows also has a "Direct API call options" section that covers each option associated with the underlying API that is called.

XCronServer::SetDebug($debug)
-----------------------------

Access:  public

Parameters:

* $debug - A boolean indicating whether or not to enable debugging output.

Returns:  Nothing.

This function enables/disables debugging mode.  The initial default is disabled.  When debugging mode is enabled, it may output sensitive information.

XCronServer::Connect($host = "127.0.0.1", $port = 10829)
--------------------------------------------------------

Access:  public

Parameters:

* $host - A string containing the host to connect to (Default is "127.0.0.1").
* $port - An integer containing the port to connect to (Default is 10829).

Returns:  A standard array of information.

This function establishes a TCP/IP connection to the xcron server.

XCronServer::Disconnect()
-------------------------

Access:  public

Parameters:  None.

Reutrns:  Nothing.

This function disconnects from the xcron server.

XCronServer::SetPasswordOnly()
------------------------------

Access:  public

Parameters:  None.

Returns:  A standard array of information.

This function enables password-only mode for the connection.  This is a voluntary function that denies access to certain functions and requires a password to be used for most operations.  Useful for application developers that want to add a little extra peace of mind as part of their [defense-in-depth](https://en.wikipedia.org/wiki/Defense_in_depth_(computing)) strategy.

Direct API call options:

* action - string - "set_password_only"

XCronServer::GetServerInfo()
----------------------------

Access:  public

Parameters:  None.

Returns:  A standard array of information.

This function returns the current internal xcron server information summary.

Example usage:

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

	$result = $xcron->GetServerInfo();

	var_dump($result);
?>
```

Direct API call options:

* action - string - "get_server_info"

XCronServer::GetSchedules($stats = true, $watch = false, $filtername = false, $filteruser = false, $filterelevated = null)
--------------------------------------------------------------------------------------------------------------------------

Access:  public

Parameters:

* $stats - A boolean that indicates whether or not to return stats for each matching schedule (Default is true).
* $watch - A boolean that indicates whether or not to actively monitor changes to matching schedules (Default is false).
* $filtername - A boolean of false or a string containing a schedule name to watch (Default is false).
* $filteruser - A boolean of false for no filter, a boolean of true to filter for the current user, or a string containing the username of the schedule to watch (Default is false).
* $filterelevated - A boolean that filters elevated vs. non-elevated schedules or `null` for no filtering.  Windows only.

Returns:  A standard array of information.

This function returns the current internal xcron server information summary, matching schedules, matching running processes, matching stats, and optionally starts monitoring for ongoing changes to the schedule that match the filters.

Example usage:

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

	$result = $xcron->GetSchedules();

	var_dump($result);
?>
```

Direct API call options:

* action - string - "get_schedules"
* stats - bool - Return stats.  Optional.
* watch - bool - Start monitoring for changes.  Optional.
* name - string - Filter schedules by name.  Optional.
* user - string or true - Filter schedules by user.  Optional.
* elevated - bool - Filter schedules by elevated vs. non-elevated.  Optional.

XCronServer::StopWatchingScheduleChanges()
------------------------------------------

Access:  public

Parameters:  None.

Returns:  A standard array of information.

This function stops watching/monitoring for all schedule changes that were started by `GetSchedules()`.

Direct API call options:

* action - string - "stop_watching_schedules"

XCronServer::GetXCrontab($user = false, $elevated = false)
----------------------------------------------------------

Access:  public

Parameters:

* $user - A boolean of false or a string containing the username to obtain an xcrontab for (Default is false).
* $elevated - A boolean that indicates whether or not to retrieve the elevated xcrontab (Default is false).  Windows only.

Returns:  A standard array of information.

This function retrieves the xcrontab for the current or specified user.  If no xcrontab exists, the default template is returned.

Example usage:

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

	$result = $xcron->GetXCrontab();

	var_dump($result);

	if ($result["success"])  echo base64_decode($result["data"]) . "\n";
?>
```

Direct API call options:

* action - string - "get_xcrontab"
* user - string or false - Username to retrieve xcrontab for.  Optional.
* elevated - bool - Retrieve elevated xcrontab if true.  Windows only.  Optional.

XCronServer::SetXCrontab($xcrontabdata, $user = false, $elevated = false)
-------------------------------------------------------------------------

Access:  public

Parameters:

* $data - A string containing the contents of the xcrontab.
* $user - A boolean of false or a string containing the username to obtain an xcrontab for (Default is false).
* $elevated - A boolean that indicates whether or not to retrieve the elevated xcrontab (Default is false).  Windows only.

Returns:  A standard array of information.

This function stores the xcrontab for the current or specified user.  Note that this only stores the xcrontab.  Upon success, call `Reload()` to reload the schedule from the xcrontab.

Direct API call options:

* action - string - "set_xcrontab"
* data - Base64-encoded string - The data to store as the updated xcrontab.  Must be Base64-encoded.
* user - string or false - Username to retrieve xcrontab for.  Optional.
* elevated - bool - Retrieve elevated xcrontab if true.  Windows only.  Optional.

XCronServer::Reload($user = false, $elevated = false)
-----------------------------------------------------

Access:  public

Parameters:

* $user - A boolean of false or a string containing the username to reload (Default is false).
* $elevated - A boolean that indicates whether or not to use the elevated xcrontab (Default is false).  Windows only.

Returns:  A standard array of information.

This function reloads the set/stored xcrontab for the current or specified user from a previously successful `SetXCrontab()` call.

Direct API call options:

* action - string - "reload"
* user - string or false - Username to reload the xcrontab for.  Optional.
* elevated - bool - Reload elevated xcrontab if true.  Windows only.  Optional.

XCronServer::TriggerRun($name, $options = array())
--------------------------------------------------

Access:  public

Parameters:

* $name - A string containing a job schedule name.
* $options - An array of additional options (Default is array()).

Returns:  A standard array of information.

This function triggers a job to start running as soon as possible.  The job is placed into the start queue for the job schedule.

The $options array accepts these options:

* data - A string containing a serialized JSON object to pass to the process.  Limited to 16KB.
* force - A boolean that forces the job to run even if the job schedule has been suspended.  Useful for verifying that a job schedule is working before removing the schedule suspension and restoring normal operations.
* user - A string containing a username.
* elevated - A boolean that indicates whether or not to use the elevated xcrontab.  Windows only.
* password - A string containing a password.  Generally required for job schedules running as another user.
* watch - A boolean indicating whether or not to also watch/monitor the output for the job when it starts running.

Example usage for this function can be found at the top of this documentation.

Direct API call options:

* action - string - "trigger_run"
* name - string - The name of the job schedule to run.
* data - string - Serialized JSON object containing data to pass via `XCRON_DATA`.  Optional.
* force - bool - Force the job to run even if the job schedule has been suspended.  Optional.
* user - string - A username.  Optional.
* elevated - bool - Use the elevated xcrontab if true.  Windows only.  Optional.
* password - string - Password for the job schedule.  Optional.
* watch - bool - Watch/monitor the output for the job when it starts running.  Optional.

XCronServer::SetNextRunTime($name, $ts, $minonly, $options = array())
---------------------------------------------------------------------

Access:  public

Parameters:

* $name - A string containing a job schedule name.
* $ts - An integer containing a UNIX timestamp.
* $minonly - A boolean that indicates whether or not to only move the next scheduled run time if it is the smallest value to date and the previous timestamp is not in the past.
* $options - An array of additional options (Default is array()).

Returns:  A standard array of information.

This function allows for an extra scheduled time to be efficiently injected into the existing schedule instead of using `TriggeredRun()` to run a process to update the schedule.  Note that only one timestamp can be injected and future calls overwrite the previous "next run" timestamp (if any).

The $options array accepts these options:

* user - A string containing a username.
* elevated - A boolean that indicates whether or not to use the elevated xcrontab.  Windows only.
* password - A string containing a password.  Generally required for job schedules running as another user.

Example usage:

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

	// Publish post 45 minutes from now.
	$ts = time() + 45 * 60;

	// Sets the schedule time to 45 minutes from now.
	$result = $xcron->SetNextRunTime("publish_blog_post", $ts, true);

	var_dump($result);


	// Publish another post 15 minutes from now.
	$ts = time() + 15 * 60;

	// Moves the schedule time to 15 minutes from now since it is earlier.
	$result = $xcron->SetNextRunTime("publish_blog_post", $ts, true);

	var_dump($result);


	// Publish another post 60 minutes from now.
	$ts = time() + 60 * 60;

	// Does NOT move the schedule time since it is later.
	$result = $xcron->SetNextRunTime("publish_blog_post", $ts, true);

	var_dump($result);
?>
```

The job schedule above will only run one time 15 minutes in the future.  It would be up to the script/program to return an updated schedule so xcron can run at 45 minutes and later at 60 minutes as intended.

Direct API call options:

* action - string - "set_next_run_time"
* name - string - The name of the job schedule to run.
* ts - integer - The UNIX timestamp to set.
* min_only - bool - Only move/set the timestamp in xcron if it is the smallest timestamp across all 'set_next_run_time' calls.
* user - string - A username.  Optional.
* elevated - bool - Use the elevated xcrontab if true.  Windows only.  Optional.
* password - string - Password for the job schedule.  Optional.

XCronServer::TestNotifications($name, $options = array())
---------------------------------------------------------

Access:  public

Parameters:

* $name - A string containing a job schedule name.
* $options - An array of additional options (Default is array()).

Returns:  A standard array of information.

This function sends test notifications for the specified job schedule and returns success/failure status for each notification target.

The $options array accepts these options:

* user - A string containing a username.
* elevated - A boolean that indicates whether or not to use the elevated xcrontab.  Windows only.
* password - A string containing a password.  Generally required for job schedules running as another user.

Example usage:

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

	$result = $xcron->TestNotifications("my_shiny_new_schedule");

	var_dump($result);
?>
```

Direct API call options:

* action - string - "test_notifications"
* name - string - The name of the job schedule.
* user - string - A username.  Optional.
* elevated - bool - Use the elevated xcrontab if true.  Windows only.  Optional.
* password - string - Password for the job schedule.  Optional.

XCronServer::SuspendScheduleUntil($name, $ts, $skipmissed, $options = array())
------------------------------------------------------------------------------

Access:  public

Parameters:

* $name - A string containing a job schedule name.
* $ts - An integer containing a UNIX timestamp.
* $skipmissed - A boolean indicating whether or not to skip running a missed schedule timestamp when resuming later.
* $options - An array of additional options (Default is array()).

Returns:  A standard array of information.

This function suspends the specified schedule until the specified timestamp.  A missed schedule time will run unless `$skipmissed` is true.  This function allows a job schedule to be temporarily suspended to work on the associated programs/scripts and then resumed later once the work has been completed.

Note that only `root`, `NT AUTHORITY\SYSTEM`, and the user with the job schedule may call this function.  The underlying xcron API intentionally does not have 'password' support.

Example usage:

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

	// Suspend for one hour.
	$result = $xcron->SuspendScheduleUntil("a_broken_schedule", time() + 60 * 60, false);

	var_dump($result);

	// Fixing the broken programs/scripts takes maybe 30 minutes.
	sleep(30 * 60);

	$xcron = new XCronServer();

	$result = $xcron->Connect();
	if (!$result["success"])
	{
		var_dump($result);
		exit();
	}

	// Resume the schedule.
	$result = $xcron->SuspendScheduleUntil("a_broken_schedule", time() - 1, false);

	var_dump($result);
?>
```

Direct API call options:

* action - string - "suspend_schedule"
* name - string - The name of the job schedule.
* ts - integer - The UNIX timestamp to set.
* skip_missed - bool - Whether or not to skip a missed job schedule run.  Optional.
* user - string - A username.  Optional.
* elevated - bool - Use the elevated xcrontab if true.  Windows only.  Optional.

XCronServer::GetRunOutput($name, $triggered, $errorlog, $stream, $options = array())
------------------------------------------------------------------------------------

Access:  public

Parameters:

* $name - A string containing a job schedule name.
* $triggered - A boolean indicating whether or not to retrieve the last triggered run log output.
* $errorlog - A boolean indicating whether or not to retrieve the last run error log output.
* $stream - A boolean indicating whether or not to retrieve the last log as a data stream.
* $options - An array of additional options (Default is array()).

Returns:  A standard array of information.

This function retrieves the run output, either normal log or error log, for the last scheduled or triggered run.  When $stream is false, only the last 32KB of output is returned.  When $stream is true and the scheduled process is currently running, the client attaches to the running process for ongoing monitoring of the output.

The $options array accepts these options:

* user - A string containing a username.
* elevated - A boolean that indicates whether or not to use the elevated xcrontab.  Windows only.
* password - A string containing a password.  Generally required for job schedules for another user.

Example usage:

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

	$result = $xcron->GetRunOutput("xcron_example", true, true, false);

	if (!$result["success"])  var_dump($result);
	else
	{
		echo $result["file"] . "\n";
		echo date("F j, Y, g:i:s a", $result["modified_ts"]) . "\n\n";

		echo base64_decode($result["data"]);
	}
?>
```

Direct API call options:

* action - string - "get_run_output"
* name - string - The name of the job schedule.
* triggered - bool - Return last triggered run.  Optional.
* error_log - bool - Return last error log.  Optional.
* stream - bool - Return log as an ongoing output stream.  Optional.
* user - string - A username.  Optional.
* elevated - bool - Use the elevated xcrontab if true.  Windows only.  Optional.
* password - string - Password for the job schedule.  Optional.

XCronServer::AttachFutureProcess($name, $type = "any", $options = array())
--------------------------------------------------------------------------

Access:  public

Parameters:

* $name - A string containing a job schedule name.
* $type - A string containing one of "any", "schedule", or "triggered" (Default is "any").
* $options - An array of additional options (Default is array()).

Returns:  A standard array of information.

This function registers the connected client to automatically attach to the next scheduled or triggered job and start receiving output.

The $options array accepts these options:

* limit - An integer containing the number of future processes to attach to (Default is 1).
* user - A string containing a username.
* elevated - A boolean that indicates whether or not to use the elevated xcrontab.  Windows only.
* password - A string containing a password.  Generally required for job schedules for another user.

Example usage:

```php
	require_once "support/sdk_xcron_server.php";

	function WaitForProcessStart($xcron)
	{
		// Wait until the process starts running.
		do
		{
			$result = $xcron->Wait(30);
			if (!$result["success"] && $result["errorcode"] !== "no_data")
			{
				var_dump($result);

				exit();
			}

			while ($result = $xcron->GetNextScheduleChange())
			{
				if (!$result["success"])
				{
					var_dump($result);

					exit();
				}

				if ($result["type"] !== "proc_set")
				{
					echo "Encountered an unexpected schedule change packet.\n";

					var_dump($result);

					exit();
				}

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
			if (!$result["success"] && $result["errorcode"] !== "no_data")
			{
				var_dump($result);

				exit();
			}

			while ($result = $xcron->GetNextOutputData())
			{
				if (isset($result["eof"]))  return;

				echo base64_decode($result["data"]);
			}
		} while (1);
	}

	// Connect to the xcron server.
	$xcron = new XCronServer();

	$result = $xcron->Connect();
	if (!$result["success"])
	{
		var_dump($result);
		exit();
	}

	$result = $xcron->AttachFutureProcess("xcron_example");

	if (!$result["success"])  var_dump($result);
	else
	{
		WaitForProcessStart($xcron);

		DumpProcessOutput($xcron);
	}
```

Direct API call options:

* action - string - "attach_process"
* name - string - The name of the job schedule.
* type - string - One of "any", "schedule", or "triggered".  Optional.
* limit - integer - The number of future processes to attach to.  Optional.
* user - string - A username.  Optional.
* elevated - bool - Use the elevated xcrontab if true.  Windows only.  Optional.
* password - string - Password for the job schedule.  Optional.

XCronServer::AttachProcessByID($id, $password = false)
------------------------------------------------------

Access:  public

Parameters:

* $id - An integer containing a xcron job ID.
* $password - A boolean of false or a string containing a password (Default is false).

Returns:  A standard array of information.

This function attaches the connected client to the running process via the xcron job ID.  A password is required for jobs in other user accounts unless the client is running as `root` or `NT AUTHORITY\SYSTEM`.

Note that this function is able to attach a single client to the same running job multiple times.  The returned client file ID differentiates data channels.

Usage is similar to the `AttachFutureProcess()` example above.

Direct API call options:

* action - string - "attach_process"
* id - integer - The xcron job ID of the running job.
* password - string - Password for the job schedule.  Optional.

XCronServer::AttachProcessByPID($id, $password = false)
-------------------------------------------------------

Access:  public

Parameters:

* $pid - An integer containing a standard process ID or PID.
* $password - A boolean of false or a string containing a password (Default is false).

Returns:  A standard array of information.

This function attaches the connected client to the running process via the system process ID.  A password is required for jobs in other user accounts unless the client is running as `root` or `NT AUTHORITY\SYSTEM`.

Note that this function is able to attach a single client to the same running job multiple times.  The returned client file ID differentiates data channels.

Usage is similar to the `AttachFutureProcess()` example above.

Direct API call options:

* action - string - "attach_process"
* pid - integer - The process ID of the running job.
* password - string - Password for the job schedule.  Optional.

XCronServer::DetachProcess($id, $fileid)
----------------------------------------

Access:  public

Parameters:

* $id - An integer containing a xcron job ID.
* $file_id - An integer containing the client file ID to detach.

Returns:  A standard array of information.

This function detaches the attached client file ID from the running job.

Direct API call options:

* action - string - "detach_process"
* id - integer - The xcron job ID of the running job.
* file_id - integer - The client file ID to detach.

XCronServer::Wait($timeout)
---------------------------

Access:  public

Parameters:

* $timeout - An integer containing the maximum number of seconds to wait for data.

Returns:  A standard array of information.

This function waits to receive data from the xcron server.  If an error is returned and the "errorcode" is "no_data" then the client either reached the timeout period OR a "monitor" packet was received and should be processed via the `GetNextScheduleChange()` and `GetNextOutputData()` functions.

The timeout is capped to 2 minutes since the xcron server will terminate idle connections after 5 minutes.  Upon timeout, a single space ( ) is sent to the xcron server.  Simply call `Wait()` again in a loop to keep the connection alive indefinitely.

Example usage can be seen in the earlier `AttachFutureProcess()` example.

XCronServer::GetNextScheduleChange()
------------------------------------

Access:  public

Parameters:  None.

Returns:  A standard array of information or a boolean of false if there is no more data.

This function is intended to be called in a loop after `Wait()` and should be processed for schedule information that arrived from the xcron server.

Example usage can be seen in the earlier `AttachFutureProcess()` example.

XCronServer::GetNextOutputData($id = false)
-------------------------------------------

Access:  public

Parameters:

* $id - A boolean of false or an integer containing a xcron job ID (Default is false).

Returns:  A standard array of information or a boolean of false if there is no more data.

This function is intended to be called in a loop after `Wait()` and should be processed for job output data that arrived from the xcron server.

Example usage can be seen in the earlier `AttachFutureProcess()` example.

XCronServer::RunAPI($data)
--------------------------

Access:  public

Parameters:

* $data - An array containing the data to JSON encode and send to the connected xcron server.

Returns:  A standard array of information.

This function makes a direct API call to the xcron server.  The function is public to allow the SDK to be used with xcron server extensions that add new "action" types.

XCronServer::XCSTranslate($format, ...)
---------------------------------------

Access:  _internal_ static

Parameters:

* $format - A string containing valid sprintf() format specifiers.

Returns:  A string containing a translation.

This internal static function takes input strings and translates them from English to some other language if CS_TRANSLATE_FUNC is defined to be a valid PHP function name.
