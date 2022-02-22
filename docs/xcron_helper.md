XCronHelper Class:  'support/xcron_functions.php'
=================================================

The XCronHelper class provides a common set of global static variables and functions to normalize OS-specific code into a common interface.  Used by xcron server, xcrontab, and xcron server extensions.

XCronHelper::$user2uinfomap
---------------------------

Access:  protected static

This internal static array maps user strings to platform-specific user information.  Reset each day or if the array gets too full.

XCronHelper::$scheduleparams
----------------------------

Access:  protected static

This internal static array tracks the possible, allowed keys in a schedule.  Used by `ValidateSchedule()` to remove invalid keys.

XCronHelper::$sensitiveparams
-----------------------------

Access:  protected static

This internal static array is used by `GetSafeSchedule()` to remove sensitive information from a schedule before sending to a connected client.

XCronHelper::$debug
-------------------

Access:  public static

This static boolean decides whether or not to dump verbose debug information within the class.

XCronHelper::$os
----------------

Access:  public static

This static string contains the result of `php_uname("s")`.

XCronHelper::$windows
---------------------

Access:  public static

This static boolean differentiates between Windows and non-Windows OSes.  Windows requires a bunch of helper applications to function properly.

XCronHelper::$rootpath
----------------------

Access:  public static

This static string contains the current path of the XCronHelper class.

XCronHelper::$env
-----------------

Access:  public static

This static array contains the base environment to use for merging when starting processes.

XCronHelper::$logpath
---------------------

Access:  public static

This static string contains the base path to the xcron log files.

XCronHelper::$em
----------------

Access:  public static

This static variable is a instance of EventManager, which implements the base plugin system.

XCronHelper::Init()
-------------------

Access:  public static

Parameters:  None.

Returns:  Nothing.

This static function initializes the XCronHelper class.

XCronHelper::EventRegister($eventname, $objorfuncname, $funcname = false)
-------------------------------------------------------------------------

Access:  public static

Parameters:

* $eventname - A string containing an event name to register the callback for.
* $objorfuncname - An instantiated object, a boolean of false, or a string containing a function name.
* $funcname - A string containing the function name to call in an instantiated object (Default is false).

Returns:  An integer containing the ID of the registered event.  Can be used to unregister the callback later.

This convenience static function calls `self::$em->Register()` to register to listen for an event.  For simple plugins, static functions may be all that is required.  For complex plugins, one or more objects may be instantiated to enable better tracking of information across multiple calls.

When the event name is the empty string (""), any fired event will call the function.  This can be useful for implementing generic plugins that handle application performance analysis or track feature usage.

There are no priorities in EventManager other than first-come, first-served.

XCronHelper::DisplayMessageAndLog($loglevel, $msg, $result = false, $exit = false)
----------------------------------------------------------------------------------

Access:  public static

Parameters:

* $loglevel - An integer containing one of the PHP LOG_ constants.  LOG_NOTICE, LOG_WARNING, and LOG_ERR are common.
* $msg - A string containing the message to display and log.
* $result - A boolean of false or a standard array of information (Default is false).
* $exit - A boolean that indicates whether or not to immediately exit xcron (Default is false).

Returns:  Nothing.

This static function displays a message and also logs the message to the system log.  On Windows, the system log type is always LOG_NOTICE due to issues with displaying LOG_WARNING and LOG_ERR messages.

XCronHelper::GetCurrentUserInfo()
---------------------------------

Access:  public static

Parameters:  None.

Returns:  A standard array of information.

This static function retrieves the current user information for the process.

XCronHelper::ResetUserInfoCache()
---------------------------------

Access:  public static

Parameters:  None.

Returns:  Nothing.

This static function resets/clears $user2uinfomap.

XCronHelper::GetUserInfo($user)
-------------------------------

Access:  public static

Parameters:

* $user - A string containing a username or an integer containing a UID to lookup.

Returns:  A standard array of information.

This static function looks up cached information and, if cached info doesn't exist, runs the necessary queries to look up the requested user.

XCronHelper::GetXCrontabPathFile($user, $elevated)
--------------------------------------------------

Access:  public static

Parameters:

* $user - A string containing a username or an integer containing a UID to lookup.
* $elevated - A boolean indicating that the elevated xcrontab path and file should be returned.  Windows only.

Returns:  A standard array of information.

This static function looks up and returns system-specific information.  On Windows, the user profile is used for xcrontab file storage in `...\AppData\Local\xcron\`.  On other OSes, the storage location is `/var/spool/cron/xcrontabs/[username]`.

XCronHelper::GetBootTimestamp()
-------------------------------

Access:  public static

Parameters:  None.

Returns:  A standard array of information.

This static function calculates and returns the boot timestamp.  Note that on Windows and other OSes, this timestamp appears to shift over time.  Apparently it is difficult to figure out when the system booted and then stick to a specific value.

XCronHelper::ExtractUnixProcLineHeaders($str)
---------------------------------------------

Access:  public static

Parameters:

* $str - A string containing UNIX utility output.

Returns:  An array containing parsed headers and the possible start and end positions of each header.

This static function parses the input string for headers from UNIX utility output.  This is similar to how `awk` works.

XCronHelper::ExtractUnixProcLineValue(&$headermap, $key, $boundleft, $boundright, &$str)
----------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $headermap - A reference to an array containing the results from `ExtractUnixProcLineHeaders()`.
* $key - A string containing a header key.  Uppercase strings expected.
* $boundleft - A boolean indicating whether or not the column is left-bounded from the header.
* $boundright - A boolean indicating whether or not the column is right-bounded from the header.
* $str - A reference containing the line to extract content from based on the header information.

Returns:  A string containing the extracted, trimmed content on success or a boolean of false if the key does not exist.

This static function uses the specified header and bounding information to extract content from the input string.  Requires the content to be perfectly aligned in a tabular format.  This is similar to how `awk` works.

XCronHelper::GetUnixProcLineStartPos(&$headermap, $key, $boundleft)
-------------------------------------------------------------------

Access:  public static

Parameters:

* $headermap - A reference to an array containing the results from `ExtractUnixProcLineHeaders()`.
* $key - A string containing a header key.  Uppercase strings expected.
* $boundleft - A boolean indicating whether or not the column is left-bounded from the header.

Returns:  The starting position of the specified header or a boolean of false if the key does not exist.

This static function returns the starting position of the specified header.

XCronHelper::GetUnixProcLineEndPos(&$headermap, $key, $boundright)
------------------------------------------------------------------

Access:  public static

Parameters:

* $headermap - A reference to an array containing the results from `ExtractUnixProcLineHeaders()`.
* $key - A string containing a header key.  Uppercase strings expected.
* $boundright - A boolean indicating whether or not the column is right-bounded from the header.

Returns:  The ending position of the specified header or a boolean of false if the key does not exist.

This static function returns the ending position of the specified header.

XCronHelper::GetClientTCPUser($localipaddr, $localport, $remoteipaddr, $remoteport)
-----------------------------------------------------------------------------------

Access:  public static

Parameters:

* $localipaddr - A string containing the local IP address to look up.
* $localport - A string or integer containing the local port to look up.
* $remoteipaddr - A string containing the remote IP address to look up.
* $remoteport - A string or integer containing the remote port to look up.

Returns:  A standard array of information.

This static function looks up the user associated with an outbound TCP/IP connection.  Since xcron is a localhost server, all inbound TCP/IP connections have an associated user.

The method of determining the user is different per supported OS.  OSes with `procfs` (i.e. `/proc`) like Linux are the most performant since the lookup is done without calling an external executable.

XCronHelper::ConvertStrToSeconds($str)
--------------------------------------

Access:  public static

Parameters:

* $str - A string containing a time to parse into seconds.

Returns:  An integer containing the parsed value.

This static function returns a calculated number of seconds.  The parser accepts strings in the following format:  A colon separated set of numbers OR a number followed by an optional letter 'd', 'h', 'm', or 's' for days, hours, minutes, or seconds respectively.

Example usage:

```php
<?php
	// 1 hour, 30 minutes.
	echo XCronHelper::ConvertStrToSeconds("90m") . "\n";
	echo XCronHelper::ConvertStrToSeconds("1:30:00") . "\n";
?>
```

XCronHelper::MakeCalendarEvent($schedule, $ts)
----------------------------------------------

Access:  public static

Parameters:

* $schedule - An array containing a schedule to process into a CalendarEvent object.
* $ts - An integer containing a UNIX timestamp.

Returns:  A standard array of information.

This static function creates a CalendarEvent object with a base timestamp from the schedule and based on the set timezone and base weekday, if specified.  Generally used to determine the next trigger timestamp.

XCronHelper::ValidateSchedule(&$warnings, &$scheduleinfo, $name, &$schedule, $allusers)
---------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $warnings - A reference to an array to collect warnings from parsing the schedule even if validation fails.
* $scheduleinfo - A reference to an array of schedules for a user.
* $name - A string containing the name of a job schedule.
* $schedule - A reference to an array containing the schedule to validate.
* $allusers - A boolean indicating whether or not the schedule being validated can run jobs for all users on the system.

Returns:  A standard array of information.

This static function validates and cleans up a schedule and also gathers any validation warnings for the user to review.

XCronHelper::ReloadScheduleTrigger(&$cachedata, $schedulekey, $name, $schedule, $currts)
----------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $cachedata - A reference to the global $cachedata array.
* $schedulekey - A string containing the unique schedule key.
* $name - A string containing the name of a job schedule.
* $schedule - An array containing a schedule to reload from.
* $currts - An integer containing a UNIX timestamp.

Returns:  Nothing.

This static function reloads the next schedule trigger.  This can be an expensive operation as it may generate full calendars for many months to find the next run timestamp for a schedule.

XCronHelper::GetLogOutputFilenameBase($schedulekey, $name)
----------------------------------------------------------

Access:  public static

Parameters:

* $schedulekey - A string containing the unique schedule key.
* $name - A string containing the name of a job schedule.

Returns:  A string containing the base path and filename to the output/error logs for a specific schedule and job.

This static function should only ever be called by the xcron server.

XCronHelper::InitLogOutputFile($filename)
-----------------------------------------

Access:  public static

Parameters:

* $filename - A string containing a filename to create/chmod.

Returns:  The result of `chmod()`.

This static function is used to initialize and set the permissions on the target filename for use as a log file.

XCronHelper::InitScheduleStats(&$cachedata, $schedulekey, $name)
----------------------------------------------------------------

Access:  public static

Parameters:

* $cachedata - A reference to the global $cachedata array.
* $schedulekey - A string containing the unique schedule key.
* $name - A string containing the name of a job schedule.

Returns:  Nothing.

This static function initializes default schedule statistics for the given schedule and job.

By default, xcron calculates statistics for:  Runs, triggered runs, dates run, errors, notifications sent, time alerts, terminations, commands executed, runtime, longest runtime, and number of times custom stats have been returned.  Statistics are tracked over time for:  Total, Since system boot, Last day, and Today.

XCronHelper::AddStatsResult(&$cachedata, $schedulekey, $name, $keymap, $mostkeymap)
-----------------------------------------------------------------------------------

Access:  public static

Parameters:

* $cachedata - A reference to the global $cachedata array.
* $schedulekey - A string containing the unique schedule key.
* $name - A string containing the name of a job schedule.
* $keymap - An array containing key-value pairs where keys are strings to modify in statistics and values are the amount to increase by.
* $mostkeymap - An array containing key-value pairs where keys are keys in the $keymap and values are mapped keys to track the "most of" something.

Returns:  Nothing.

This static function adds to the totals gathered in the statistics for the given schedule and job.

XCronHelper::GetUserDisplayName(&$schedules, $schedulekey)
----------------------------------------------------------

Access:  public static

Parameters:

* $schedules - A reference to the global $schedules array.
* $schedulekey - A string containing the unique schedule key.

Returns:  A human-readable string suitable for display.  If the schedule does not exist, the schedule key is returned.

This static function returns a displayable username.  The schedule key may be an awkward display option depending on the OS.

XCronHelper::NotifyScheduleResult(&$notifiers, &$cachedata, &$schedules, $schedulekey, $name, $data, $force = false)
--------------------------------------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $notifiers - A reference to the global $notifiers array.
* $cachedata - A reference to the global $cachedata array.
* $schedules - A reference to the global $schedules array.
* $schedulekey - A string containing the unique schedule key.
* $name - A string containing the name of a job schedule.
* $data - An array containing the information to send to the notifiers for the job schedule.
* $force - A boolean indicating that error limits should be ignored.

Returns:  A standard array of information.

This static function attempts to send a notification to all notification targets for the given schedule and job.

Responses from this function are generally ignored except when sending test notifications.

XCronHelper::StartScheduleProcess(&$procs, &$procmap, &$fpcache, &$schedules, &$cachedata, $schedulekey, $name, $xcronid, $ts, $cmdnum, $triggered, $data, $overwritemap = array())
-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $procs - A reference to the global $procs array.
* $procmap - A reference to the global $procmap array.
* $fpcache - A reference to the global $fpcache array.
* $schedules - A reference to the global $schedules array.
* $cachedata - A reference to the global $cachedata array.
* $schedulekey - A string containing the unique schedule key.
* $name - A string containing the name of a job schedule.
* $xcronid - An integer containing the xcron job ID to use.
* $ts - An integer containing the job start UNIX timestamp.
* $cmdnum - An integer containing the command number to execute.
* $triggered - A boolean indicating whether or not this is a triggered process.
* $data - A string containing serialize JSON data to set for XCRON_DATA.
* $overwritemap - An array containing values to overwrite in the final process array.

Returns:  A standard array of information.

This static function prepares and starts the next command in the sequence for a job.  Supports both schedules and triggers.

XCronHelper::GetSafeSchedule(&$schedules, &$cachedata, &$startqueuenums, $schedulekey, $name)
---------------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $schedules - A reference to the global $schedules array.
* $cachedata - A reference to the global $cachedata array.
* $startqueuenums - A reference to the global $startqueuenums array.
* $schedulekey - A string containing the unique schedule key.
* $name - A string containing the name of a job schedule.

Returns:  An array containing a prepared, sanitized schedule.

This static function prepares a sanitized schedule suitable for sending to a connected client.
