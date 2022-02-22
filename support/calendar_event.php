<?php
	// Calendar event class.  Supports all types of schedules using a cron-like format.
	// (C) 2022 CubicleSoft.  All Rights Reserved.

	class CalendarEvent
	{
		private $data, $data2, $now;

		public static $allmonths = array("jan" => 1, "feb" => 2, "mar" => 3, "apr" => 4, "may" => 5, "jun" => 6, "jul" => 7, "aug" => 8, "sep" => 9, "oct" => 10, "nov" => 11, "dec" => 12);
		public static $allweekdays = array("sun" => 0, "mon" => 1, "tue" => 2, "wed" => 3, "thu" => 4, "fri" => 5, "sat" => 6);

		public function __construct($data = array())
		{
			$this->data2 = array();
			$this->SetData($data);
		}

		private function Init()
		{
			$tz = (function_exists("date_default_timezone_get") ? @date_default_timezone_get() : @ini_get("date.timezone"));
			if ($tz == "")  $tz = "UTC";

			if (!isset($this->data["tz"]))  $this->data["tz"] = $tz;
			if (!isset($this->data["schedules"]))  $this->data["schedules"] = array();
			if (!isset($this->data["exceptions"]))  $this->data["exceptions"] = array();
			if (!isset($this->data["nexttrigger"]))  $this->data["nexttrigger"] = false;
			if (!isset($this->data["startweekday"]))  $this->SetStartWeekday("sun");
			else  $this->SetStartWeekday($this->data["startweekday"]);

			$this->data2["cachedschedules"] = array();
			$this->data2["cachedexceptions"] = array();
			$this->data2["cachedcalendar"] = array();

			$this->SetTime();
		}

		// Should only be used for loading data retrieved with GetData().  Do not modify directly.
		public function SetData($data = array())
		{
			if (!is_array($data))  $data = array();
			$this->data = $data;
			$this->Init();
		}

		public function GetData()
		{
			return $this->data;
		}

		public function SetTime($ts = false)
		{
			if ($ts === false)  $ts = time();
			$this->now = $ts;
		}

		// Set the timezone before adding schedules and exceptions.
		public function SetTimezone($tz)
		{
			$this->data["tz"] = $tz;
		}

		public function GetTimezone()
		{
			return $this->data["tz"];
		}

		// Sets the first day of the week (default = "Sun").
		public function SetStartWeekday($weekday)
		{
			$startweekday = strtolower(substr($weekday, 0, 3));
			if (!isset(self::$allweekdays[$startweekday]))  return false;

			// Generate a weekday to zero-based map.
			$this->data["startweekday"] = $startweekday;
			$basenum = self::$allweekdays[$startweekday];
			$this->data2["weekdaymap"] = array();
			foreach (self::$allweekdays as $weekday => $num)
			{
				$num = $num - $basenum;
				if ($num < 0)  $num = 7 + $num;
				$this->data2["weekdaymap"][$weekday] = $num + 1;
			}

			return true;
		}

		// Crontab-like format:  months weekrows weekday days hours mins secs startdate[/dayskip[/weekskip]] enddate [duration]
		// Also accepts an array of key-value pairs where the keys are named identically.
		// For the 'skip' options, '*', '0', and '1' are synonymous.  '2' means every other.  '3' means every third.  Etc.
		// The 'skip' options are based at 'startdate'.  Prefixes for 'days' and 'weekrows' do not affect these values.
		// The 'enddate' option is either a date (exclusive) or '*' to indicate no expiration date.
		// The first seven options can be formatted 'X/Y' where 'X' is a base and 'Y' is an increment value.
		//   This allows 0,5,10,15,20,25,30,35,40,45,50,55 to be represented simply with '0/5'.
		// The 'hours' option also allows for 12-hour clocks by appending 'am' or 'pm' to each value.
		// English only months and weekdays.  First three letters only ('Jan', 'Feb', 'Sun', 'Mon', etc).  Case-insensitive.
		//   NOTE:  Numeric values for months and weekdays start at '1'.  Numeric values for 'weekday' are subject to the value of the starting weekday (default is "Sun").
		// Prefix 'R' to 'days' to "reverse" the day order of the months.  Useful for firing events 'x' days from the end of the month.
		// Prefix 'R' to 'weekrows' to "reverse" the order of the rows of the weeks.
		// Prefix 'F' to 'weekrows' to only count complete weeks of the month.  Call SetStartWeekday() to set the first day of the week to something other than "Sun".
		// Prefix 'N' to 'weekday' to select the nearest weekday to the selected weekdays within the current month.  Useful for selecting the nearest weekday to a specific date.  In the event of a tie, the earlier weekday is selected - unless the 'R' prefix for 'days' is specified, then the direction is inverted.
		//   Prefix 'N-' to 'weekday' to look for a match first to earlier days.  Useful for preferring Friday instead of Monday if the day is Sunday.  When the 'R' prefix for 'days' is specified, the direction is inverted.
		//   Prefix 'N+' to look ahead for a match first.  Useful for preferring the following Monday instead of Friday if the day is Saturday.  When the 'R' prefix for 'days' is specified, the direction is inverted.
		// The 'duration' option specifies how long each instance of the schedule lasts (in seconds).  Could be used for identifying scheduling conflicts or maximum execution time.
		// Prepend 'cron' to the string to get an old-school crontab format:  cron [secs] mins hours days months weekday
		// Prepend 'cron' to the string to get a modernized crontab format:  cron secs mins hours days months weekday weekrows startdate[/dayskip[/weekskip]] enddate [duration]
		//
		// Example:  Jan,7 * * 1,15-17 0 0 0 2010-01-01 *
		//           (Every January and July 1st, and 15th-17th at midnight starting at Jan 1, 2010.)
		// Example:  * 2,4 Tue-Thu * 15 30 0/5 2010-01-01 *
		//           * * Tue-Thu 8-14,22-28 15 30 0/5 2010-01-01 *
		//           (Every 2nd and 4th Tue, Wed, and Thu of each month and every 5 seconds during 3:30 p.m. starting at Jan 1, 2010.
		//            Both examples are similar but they depend on the perspective.)
		// Example:  * * Sat,Sun * 0 0 0 2010-01-01/*/2 *
		//           (Every other Sat and Sun at midnight starting at Jan 1, 2010.)
		// Example:  * * * * * * 0 2010-01-01 *
		//           (Every minute of every day starting at Jan 1, 2010.)
		// Example:  * * * * 3pm 30 0 2010-01-01 2010-01-01
		//           (One-time at 3:30:00 p.m. on Jan 1, 2010.)
		// Example:  * * Fri R1-7 12am 0 0 2010-01-01 *
		//           (Every last Friday of every month at midnight starting at Jan 1, 2010.)
		// Example:  Jul * N-Mon-Fri 4 0 0 0 2010-01-01 *
		//           (The nearest weekday in July to every July 4 at midnight starting at Jan 1, 2010 with a preference for Friday.)
		public function AddSchedule($options, $replaceid = false)
		{
			$temptz = new CalendarEvent_TZSwitch($this->data["tz"]);

			$schedule = array(
				"origopts" => $options
			);

			if (is_string($options))
			{
				$opts = explode(" ", preg_replace('/\s+/', " ", trim($options)));

				// Rewrite the array to the internal format from a crontab format.
				if (count($opts) && strtolower($opts[0]) == "cron")
				{
					if (count($opts) > 9)
					{
						$opts2 = array($opts[5], $opts[7], $opts[6], $opts[4], $opts[3], $opts[2], $opts[1], $opts[8], $opts[9]);

						if (count($opts) > 10)  $opts2[] = $opts[10];

						$opts = $opts2;
					}
					else if (count($opts) == 7)
					{
						$opts = array($opts[5], "*", $opts[6], $opts[4], $opts[3], $opts[2], $opts[1], date("Y-m-d", $this->now), "*");
					}
					else if (count($opts) == 6)
					{
						$opts = array($opts[4], "*", $opts[5], $opts[3], $opts[2], $opts[1], "0", date("Y-m-d", $this->now), "*");
					}
				}

				if (count($opts) < 9 || count($opts) > 10)  return array("success" => false, "error" => "Invalid number of options.");

				if ($opts[0] != "*")  $schedule["months"] = $opts[0];
				if ($opts[1] != "*")  $schedule["weekrows"] = $opts[1];
				if ($opts[2] != "*")  $schedule["weekday"] = $opts[2];
				if ($opts[3] != "*")  $schedule["days"] = $opts[3];
				if ($opts[4] != "*")  $schedule["hours"] = $opts[4];
				if ($opts[5] != "*")  $schedule["mins"] = $opts[5];
				if ($opts[6] != "*")  $schedule["secs"] = $opts[6];
				$schedule["startdate"] = $opts[7];
				if ($opts[8] != "*")  $schedule["enddate"] = $opts[8];
				if (count($opts) > 9)  $schedule["duration"] = $opts[9];
			}
			else if (is_array($options))
			{
				if (isset($options["months"]) && $options["months"] != "*")  $schedule["months"] = $options["months"];
				if (isset($options["weekrows"]) && $options["weekrows"] != "*")  $schedule["weekrows"] = $options["weekrows"];
				if (isset($options["weekday"]) && $options["weekday"] != "*")  $schedule["weekday"] = $options["weekday"];
				if (isset($options["days"]) && $options["days"] != "*")  $schedule["days"] = $options["days"];
				if (isset($options["hours"]) && $options["hours"] != "*")  $schedule["hours"] = $options["hours"];
				if (isset($options["mins"]) && $options["mins"] != "*")  $schedule["mins"] = $options["mins"];
				if (isset($options["secs"]) && $options["secs"] != "*")  $schedule["secs"] = $options["secs"];
				$schedule["startdate"] = (isset($options["startdate"]) ? $options["startdate"] : date("Y-m-d", $this->now));
				if (isset($options["enddate"]) && $options["enddate"] != "*")  $schedule["enddate"] = $options["enddate"];
				if (isset($options["duration"]) && $options["duration"] != "")  $schedule["duration"] = $options["duration"];
			}
			else  return array("success" => false, "error" => "The 'options' parameter type is invalid.  Must be a string or an array.");

			// Validate each option.
			if (isset($schedule["months"]) && !$this->IsValidExpr($result, $schedule["months"], 1, 12, self::$allmonths))  return array("success" => false, "error" => "Invalid 'months' specified.  Reason:  " . $result["error"]);
			if (isset($schedule["weekrows"]) && !$this->IsValidExpr($result, $schedule["weekrows"], 1, 6, array(), array("r", "f")))  return array("success" => false, "error" => "Invalid 'weekrows' specified.  Reason:  " . $result["error"]);
			if (isset($schedule["weekday"]) && !$this->IsValidExpr($result, $schedule["weekday"], 1, 7, $this->data2["weekdaymap"], array("n-", "n+", "n")))  return array("success" => false, "error" => "Invalid 'weekday' specified.  Reason:  " . $result["error"]);
			if (isset($schedule["days"]) && !$this->IsValidExpr($result, $schedule["days"], 1, 31, array(), array("r")))  return array("success" => false, "error" => "Invalid 'days' specified.  Reason:  " . $result["error"]);
			if (isset($schedule["hours"]) && !$this->IsValidExpr($result, $schedule["hours"], 0, 23, array(), array(), true))  return array("success" => false, "error" => "Invalid 'hours' specified.  Reason:  " . $result["error"]);
			if (isset($schedule["mins"]) && !$this->IsValidExpr($result, $schedule["mins"], 0, 59))  return array("success" => false, "error" => "Invalid 'mins' specified.  Reason:  " . $result["error"]);
			if (isset($schedule["secs"]) && !$this->IsValidExpr($result, $schedule["secs"], 0, 59))  return array("success" => false, "error" => "Invalid 'secs' specified.  Reason:  " . $result["error"]);

			if (substr($schedule["startdate"], 0, 1) == "*")  return array("success" => false, "error" => "The 'startdate' option must be specified.  Invalid '*' encountered.");
			if (!$this->IsValidDate($result, $schedule["startdate"], true))  return array("success" => false, "error" => "Invalid 'startdate' specified.  Reason:  " . $result["error"]);
			if (isset($schedule["enddate"]) && !$this->IsValidDate($result, $schedule["enddate"]))  return array("success" => false, "error" => "Invalid 'enddate' specified.  Reason:  " . $result["error"]);
			if (isset($schedule["duration"]) && ($schedule["duration"] == "*" || $schedule["duration"] == ""))  unset($schedule["duration"]);
			if (isset($schedule["duration"]) && ($schedule["duration"] < 0 || $schedule["duration"] > 24 * 60 * 60))  return array("success" => false, "error" => "Invalid 'duration' specified.");

			if ($replaceid === false || !isset($this->data["schedules"][$replaceid]))
			{
				$this->data["schedules"][] = $schedule;
				$keys = array_keys($this->data["schedules"]);
				$id = end($keys);
			}
			else
			{
				$this->data["schedules"][$replaceid] = $schedule;
				$id = $replaceid;
			}

			return array("success" => true, "id" => $id);
		}

		public function GetSchedules()
		{
			return $this->data["schedules"];
		}

		public function GetCachedSchedules()
		{
			if (!isset($this->data2["cachedexceptions"]))  return false;

			return $this->data2["cachedschedules"];
		}

		public function RemoveSchedule($id)
		{
			if (!isset($this->data["schedules"][$id]))  return false;

			unset($this->data["schedules"][$id]);

			return true;
		}

		public function SetScheduleDuration($id, $duration)
		{
			if (!isset($this->data["schedules"][$id]))  return false;

			$this->data["schedules"][$id]["duration"] = (int)$duration;

			return true;
		}

		// Overrides the default schedule for a specific day.  Normally scheduled items will not run on exception days.
		// Crontab-like format:  srcdate destdate hours mins secs [duration]
		// Schedules occurring on 'srcdate' are not triggered and the exception triggers on 'destdate'.  This allows an event to trigger on a different day than normal.
		public function AddScheduleException($options)
		{
			$temptz = new CalendarEvent_TZSwitch($this->data["tz"]);

			$exception = array(
				"origopts" => $options
			);

			if (is_string($options))
			{
				$opts = explode(" ", preg_replace('/\s+/', " ", $options));

				if (count($opts) < 5 || count($opts) > 6)  return array("success" => false, "error" => "Invalid number of options.");

				$exception["srcdate"] = $opts[0];
				$exception["destdate"] = $opts[1];
				if ($opts[2] != "*")  $exception["hours"] = $opts[2];
				if ($opts[3] != "*")  $exception["mins"] = $opts[3];
				if ($opts[4] != "*")  $exception["secs"] = $opts[4];
				if (count($opts) > 5)  $exception["duration"] = $opts[5];
			}
			else if (is_array($options))
			{
				$exception["srcdate"] = (isset($options["srcdate"]) ? $options["srcdate"] : date("Y-m-d", $this->now));
				$exception["destdate"] = (isset($options["destdate"]) ? $options["destdate"] : date("Y-m-d", $this->now));
				if (isset($options["hours"]) && $options["hours"] != "*")  $exception["hours"] = $options["hours"];
				if (isset($options["mins"]) && $options["mins"] != "*")  $exception["mins"] = $options["mins"];
				if (isset($options["secs"]) && $options["secs"] != "*")  $exception["secs"] = $options["secs"];
				if (isset($options["duration"]) && $options["duration"] != "")  $exception["duration"] = $options["duration"];
			}
			else  return array("success" => false, "error" => "The 'options' parameter type is invalid.  Must be a string or an array.");

			// Validate each option.
			if (substr($exception["srcdate"], 0, 1) == "*")  return array("success" => false, "error" => "The 'srcdate' option must be specified.  Invalid '*' encountered.");
			if (!$this->IsValidDate($result, $exception["srcdate"]))  return array("success" => false, "error" => "Invalid 'srcdate' specified.  Reason:  " . $result["error"]);

			if (substr($exception["destdate"], 0, 1) == "*")  return array("success" => false, "error" => "The 'destdate' option must be specified.  Invalid '*' encountered.");
			if (!$this->IsValidDate($result, $exception["destdate"]))  return array("success" => false, "error" => "Invalid 'destdate' specified.  Reason:  " . $result["error"]);

			if (isset($exception["hours"]) && !$this->IsValidExpr($result, $exception["hours"], 0, 23, array(), array(), true))  return array("success" => false, "error" => "Invalid 'hours' specified.  Reason:  " . $result["error"]);
			if (isset($exception["mins"]) && !$this->IsValidExpr($result, $exception["mins"], 0, 59))  return array("success" => false, "error" => "Invalid 'mins' specified.  Reason:  " . $result["error"]);
			if (isset($exception["secs"]) && !$this->IsValidExpr($result, $exception["secs"], 0, 59))  return array("success" => false, "error" => "Invalid 'secs' specified.  Reason:  " . $result["error"]);

			if ($exception["duration"] == "*" || $exception["duration"] == "")  unset($exception["duration"]);
			if (isset($exception["duration"]) && ($exception["duration"] < 0 || $exception["duration"] > 24 * 60 * 60))  return array("success" => false, "error" => "Invalid 'duration' specified.");

			$this->data["exceptions"][$exception["srcdate"]] = $exception;

			return array("success" => true, "id" => $exception["srcdate"]);
		}

		public function GetScheduleExceptions()
		{
			return $this->data["exceptions"];
		}

		public function GetCachedScheduleExceptions()
		{
			if (!isset($this->data2["cachedexceptions"]))  return false;

			return $this->data2["cachedexceptions"];
		}

		public function RemoveScheduleException($id)
		{
			if (!isset($this->data["exceptions"][$id]))  return false;

			unset($this->data["exceptions"][$id]);

			return true;
		}

		public function SetScheduleExceptionDuration($id, $duration)
		{
			if (!isset($this->data["exceptions"][$id]))  return false;

			$this->data["exceptions"][$id]["duration"] = (int)$duration;

			return true;
		}

		// Generates a calendar of days for a given month and IDs of schedules and exceptions that run on those days.
		// Enough information is returned to easily generate a HTML calendar.
		public function GetCalendar($year, $month, $sparse = false)
		{
			$temptz = new CalendarEvent_TZSwitch($this->data["tz"]);

			$result = array("success" => true, "year" => $year, "month" => $month);

			$year = (int)$year;
			$month = (int)$month;

			// Basic setup.
			$startts = mktime(0, 0, 0, $month, 1, $year);
			$endts = mktime(0, 0, 0, $month + 1, 1, $year);
			if ($startts === false || $startts == -1 || $endts === false || $endts == -1)  return array("success" => false, "error" => "Specified year and month are out of range (OS limitations).");

			$result["prevmonthnumdays"] = date("t", mktime(0, 0, 0, $month - 1, 1, $year));
			$result["weekdays"] = $this->data2["weekdaymap"];
			$result["firstweekdaypos"] = $this->data2["weekdaymap"][strtolower(date("D", $startts))];
			$result["numdays"] = date("t", $startts);
			$result["numweeks"] = (int)(($result["firstweekdaypos"] - 1 + $result["numdays"] + 6) / 7);
			$result["nextmonthweekdaypos"] = $this->data2["weekdaymap"][strtolower(date("D", $endts))];

			if ($sparse && isset($this->data2["cachedcalendar"][$year . "-" . $month]))  $result["calendar"] = $this->data2["cachedcalendar"][$year . "-" . $month];
			else
			{
				// Expand and cache each relevant option of each schedule.
				foreach ($this->data["schedules"] as $id => $schedule)
				{
					if (!isset($this->data2["cachedschedules"][$id]))  $this->data2["cachedschedules"][$id] = array();

					if (!isset($this->data2["cachedschedules"][$id]["days"]))
					{
						$schedule2 = $this->data2["cachedschedules"][$id];

						if (!isset($schedule["months"]))  $schedule["months"] = "*";
						if (!isset($schedule["weekrows"]))  $schedule["weekrows"] = "*";
						if (!isset($schedule["weekday"]))  $schedule["weekday"] = "*";
						if (!isset($schedule["days"]))  $schedule["days"] = "*";

						if (!$this->IsValidExpr($schedule2["months"], $schedule["months"], 1, 12, self::$allmonths))  return array("success" => false, "error" => "Invalid 'months' specified.  Reason:  " . $schedule2["months"]["error"]);
						if (!$this->IsValidExpr($schedule2["weekrows"], $schedule["weekrows"], 1, 6, array(), array("r", "f")))  return array("success" => false, "error" => "Invalid 'weekrows' specified.  Reason:  " . $schedule2["weekrows"]["error"]);
						if (!$this->IsValidExpr($schedule2["weekday"], $schedule["weekday"], 1, 7, $this->data2["weekdaymap"], array("n-", "n+", "n")))  return array("success" => false, "error" => "Invalid 'weekday' specified.  Reason:  " . $schedule2["weekday"]["error"]);
						if (!$this->IsValidExpr($schedule2["days"], $schedule["days"], 1, 31, array(), array("r")))  return array("success" => false, "error" => "Invalid 'days' specified.  Reason:  " . $schedule2["days"]["error"]);

						if (!$this->IsValidDate($schedule2["startdate"], $schedule["startdate"], true))  return array("success" => false, "error" => "Invalid 'startdate' specified.  Reason:  " . $schedule2["startdate"]["error"]);
						if (isset($schedule["enddate"]) && !$this->IsValidDate($schedule2["enddate"], $schedule["enddate"]))  return array("success" => false, "error" => "Invalid 'enddate' specified.  Reason:  " . $schedule2["enddate"]["error"]);

						$this->data2["cachedschedules"][$id] = $schedule2;
					}
				}

				// Expand and cache relevant options of each exception.
				$destdatemap = array();
				foreach ($this->data["exceptions"] as $srcdate => $exception)
				{
					if (!isset($this->data2["cachedexceptions"][$srcdate]))  $this->data2["cachedexceptions"][$srcdate] = array();
					if (!isset($this->data2["cachedexceptions"][$srcdate]["destdate"]))
					{
						$exception2 = $this->data2["cachedexceptions"][$srcdate];

						if (!$this->IsValidDate($exception2["destdate"], $exception["destdate"]))  return array("success" => false, "error" => "Invalid exception 'destdate' specified.  Reason:  " . $exception2["destdate"]["error"]);

						$this->data2["cachedexceptions"][$srcdate] = $exception2;
					}

					$destdate = $this->data2["cachedexceptions"][$srcdate]["destdate"];
					if ($destdate["year"] == $year && $destdate["month"] == $month)
					{
						if (!isset($destdatemap[$destdate["day"]]))  $destdatemap[$destdate["day"]] = array();
						$destdatemap[$destdate["day"]][] = $srcdate;
					}
				}

				// Fill in the calendar.
				$result["calendar"] = array();
				$firstfullweek = ($result["firstweekdaypos"] == 1 ? 1 : 2);
				$lastfullweek = ($result["nextmonthweekdaypos"] == 1 ? $result["numweeks"] : $result["numweeks"] - 1);
				$weekday = $result["firstweekdaypos"];
				$weekrow = 1;
				for ($day = 1; $day <= $result["numdays"]; $day++)
				{
					$ts = mktime(0, 0, 0, $month, $day, $year);
					if (isset($this->data["exceptions"][date("Y-m-d", $ts)]))
					{
						if (isset($result["calendar"][$day]))  unset($result["calendar"][$day]);

						if (isset($destdatemap[$day]))  $result["calendar"][$day] = array("type" => "exception", "exceptions" => $destdatemap[$day]);
						else if (!$sparse)  $result["calendar"][$day] = array("type" => "exception");
					}
					else
					{
						if (!isset($result["calendar"][$day]))  $ids = array();
						else
						{
							// This only executes when 'nearest' (N) prefixes are used.
							$ids = $result["calendar"][$day];
							unset($result["calendar"][$day]);
						}
						foreach ($this->data2["cachedschedules"] as $id => $schedule)
						{
							// Check start and end dates.
							if ($ts >= $schedule["startdate"]["ts"] && (!isset($schedule["enddate"]) || $ts <= $schedule["enddate"]["ts"]))
							{
								// Check 'months'.
								if (!isset($schedule["months"]["expanded"][$month]))  continue;

								// Check 'skipdays' and 'skipweeks'.
								$diff = (int)(($ts - $schedule["startdate"]["ts"]) / (24 * 60 * 60));
								if (isset($schedule["startdate"]["skipdays"]) && ($diff % $schedule["startdate"]["skipdays"]) > 0)  continue;
								if (isset($schedule["startdate"]["skipweeks"]) && (((int)($diff / 7)) % $schedule["startdate"]["skipweeks"]) > 0)  continue;

								// Check 'weekrows'.
								if (isset($schedule["weekrows"]["prefixes"]["f"]))
								{
									if (isset($schedule["weekrows"]["prefixes"]["r"]))
									{
										$weekrow2 = $result["numweeks"] - $weekrow + 1;
										if ($weekrow2 < $firstfullweek || $weekrow2 > $lastfullweek)  continue;
										if (!isset($schedule["weekrows"]["expanded"][$lastfullweek - $weekrow2 + 1]))  continue;
									}
									else
									{
										if ($weekrow < $firstfullweek || $weekrow > $lastfullweek)  continue;
										if (!isset($schedule["weekrows"]["expanded"][$weekrow - $firstfullweek + 1]))  continue;
									}
								}
								else
								{
									if (!isset($schedule["weekrows"]["expanded"][isset($schedule["weekrows"]["prefixes"]["r"]) ? $result["numweeks"] - $weekrow + 1 : $weekrow]))  continue;
								}

								// Check 'days'.
								if (!isset($schedule["days"]["expanded"][isset($schedule["days"]["prefixes"]["r"]) ? $result["numdays"] - $day + 1 : $day]))  continue;

								// Check 'weekday'.
								if (!isset($schedule["weekday"]["expanded"][$weekday]))
								{
									// Check to see if one of the "nearest" prefixes is available.
									if (count($schedule["weekday"]["prefixes"]))
									{
										if (isset($schedule["weekday"]["prefixes"]["n"]))
										{
											$day2 = $day3 = $day;
											$weekday2 = $weekday3 = $weekday;
											do
											{
												if ($day2 > 1)
												{
													$day2--;
													$weekday2--;
													if (!$weekday2)  $weekday2 = 7;
												}

												if ($day3 < $result["numdays"])
												{
													$day3++;
													$weekday3++;
													if ($weekday3 == 8)  $weekday3 = 0;
												}
											} while (!isset($schedule["weekday"]["expanded"][$weekday2]) && !isset($schedule["weekday"]["expanded"][$weekday3]));

											if (!isset($schedule["weekday"]["expanded"][$weekday2]) || (isset($schedule["weekday"]["expanded"][$weekday3]) && isset($schedule["days"]["prefixes"]["r"])))  $day2 = $day3;
										}
										else if ((!isset($schedule["days"]["prefixes"]["r"]) && isset($schedule["weekday"]["prefixes"]["n-"])) || (isset($schedule["days"]["prefixes"]["r"]) && isset($schedule["weekday"]["prefixes"]["n+"])))
										{
											// Move left first.
											$day2 = $day;
											$weekday2 = $weekday;
											while (!isset($schedule["weekday"]["expanded"][$weekday2]) && $day2 > 1)
											{
												$day2--;
												$weekday2--;
												if (!$weekday2)  $weekday2 = 7;
											}
											if (!isset($schedule["weekday"]["expanded"][$weekday2]))
											{
												$day2 = $day;
												$weekday2 = $weekday;
												while (!isset($schedule["weekday"]["expanded"][$weekday2]) && $day2 < $result["numdays"])
												{
													$day2++;
													$weekday2++;
													if ($weekday2 == 8)  $weekday2 = 0;
												}
											}
										}
										else
										{
											// Move right first.
											$day2 = $day;
											$weekday2 = $weekday;
											while (!isset($schedule["weekday"]["expanded"][$weekday2]) && $day2 < $result["numdays"])
											{
												$day2++;
												$weekday2++;
												if ($weekday2 == 8)  $weekday2 = 0;
											}
											if (!isset($schedule["weekday"]["expanded"][$weekday2]))
											{
												$day2 = $day;
												$weekday2 = $weekday;
												while (!isset($schedule["weekday"]["expanded"][$weekday2]) && $day2 > 1)
												{
													$day2--;
													$weekday2--;
													if (!$weekday2)  $weekday2 = 7;
												}
											}
										}

										// Set the day and ID.
										if ($day2 < $day)
										{
											if (!isset($result["calendar"][$day2]))  $result["calendar"][$day2] = array("type" => "normal", "ids" => array());
											if ($result["calendar"][$day2]["type"] != "exception")
											{
												if ($result["calendar"][$day2]["type"] == "destexception")  $result["calendar"][$day2]["type"] = "normal";
												if (!isset($result["calendar"][$day2]["ids"]))  $result["calendar"][$day2]["ids"] = array();
												$result["calendar"][$day2]["ids"][$id] = $id;
											}
										}
										else
										{
											if (!isset($result["calendar"][$day2]))  $result["calendar"][$day2] = array();
											$result["calendar"][$day2][$id] = $id;
										}
									}

									continue;
								}

								$ids[$id] = $id;
							}
						}

						if (count($ids))
						{
							$result["calendar"][$day] = array("type" => "normal", "ids" => $ids);
							if (isset($destdatemap[$day]))  $result["calendar"][$day]["exceptions"] = $destdatemap[$day];
						}
						else if (isset($destdatemap[$day]))  $result["calendar"][$day] = array("type" => "destexception", "exceptions" => $destdatemap[$day]);
						else if (!$sparse)  $result["calendar"][$day] = array("type" => "none");
					}

					$weekday++;
					if ($weekday == 8)
					{
						$weekday = 1;
						$weekrow++;
					}
				}
			}

			return $result;
		}

		// Returns the expanded hours, minutes, and seconds for the specified schedule ID.
		public function GetTimes($id)
		{
			if (!isset($this->data["schedules"][$id]))  return array("success" => false, "error" => "Invalid schedule ID specified.");

			if (!isset($this->data2["cachedschedules"][$id]))  $this->data2["cachedschedules"][$id] = array();
			if (!isset($this->data2["cachedschedules"][$id]["secs"]))
			{
				$schedule = $this->data["schedules"][$id];
				$schedule2 = $this->data2["cachedschedules"][$id];
				if (!isset($schedule["hours"]))  $schedule["hours"] = "*";
				if (!isset($schedule["mins"]))  $schedule["mins"] = "*";
				if (!isset($schedule["secs"]))  $schedule["secs"] = "*";

				if (!$this->IsValidExpr($schedule2["hours"], $schedule["hours"], 0, 23, array(), array(), true))  return array("success" => false, "error" => "Invalid 'hours' specified.  Reason:  " . $schedule2["hours"]["error"]);
				if (!$this->IsValidExpr($schedule2["mins"], $schedule["mins"], 0, 59))  return array("success" => false, "error" => "Invalid 'mins' specified.  Reason:  " . $schedule2["mins"]["error"]);
				if (!$this->IsValidExpr($schedule2["secs"], $schedule["secs"], 0, 59))  return array("success" => false, "error" => "Invalid 'secs' specified.  Reason:  " . $schedule2["secs"]["error"]);

				$this->data2["cachedschedules"][$id] = $schedule2;
			}

			return array("success" => true, "hours" => $this->data2["cachedschedules"][$id]["hours"]["expanded"], "mins" => $this->data2["cachedschedules"][$id]["mins"]["expanded"], "secs" => $this->data2["cachedschedules"][$id]["secs"]["expanded"]);
		}

		// Returns the expanded hours, minutes, and seconds for the specified exception 'srcdate'.
		public function GetExceptionTimes($srcdate)
		{
			if (!isset($this->data["exceptions"][$srcdate]))  return array("success" => false, "error" => "Invalid exception 'srcdate' specified.");

			if (!isset($this->data2["cachedexceptions"][$srcdate]))  $this->data2["cachedexceptions"][$srcdate] = array();
			if (!isset($this->data2["cachedexceptions"][$srcdate]["secs"]))
			{
				$exception = $this->data["exceptions"][$srcdate];
				$exception2 = $this->data2["cachedexceptions"][$srcdate];
				if (!isset($exception["hours"]))  $exception["hours"] = "*";
				if (!isset($exception["mins"]))  $exception["mins"] = "*";
				if (!isset($exception["secs"]))  $exception["secs"] = "*";

				if (!$this->IsValidExpr($exception2["hours"], $exception["hours"], 0, 23, array(), array(), true))  return array("success" => false, "error" => "Invalid 'hours' specified.  Reason:  " . $exception2["hours"]["error"]);
				if (!$this->IsValidExpr($exception2["mins"], $exception["mins"], 0, 59))  return array("success" => false, "error" => "Invalid 'mins' specified.  Reason:  " . $exception2["mins"]["error"]);
				if (!$this->IsValidExpr($exception2["secs"], $exception["secs"], 0, 59))  return array("success" => false, "error" => "Invalid 'secs' specified.  Reason:  " . $exception2["secs"]["error"]);

				$this->data2["cachedexceptions"][$srcdate] = $exception2;
			}

			return array("success" => true, "hours" => $this->data2["cachedexceptions"][$srcdate]["hours"]["expanded"], "mins" => $this->data2["cachedexceptions"][$srcdate]["mins"]["expanded"], "secs" => $this->data2["cachedexceptions"][$srcdate]["secs"]["expanded"]);
		}

		// Returns the ID and timestamp of the next trigger for the event.
		// Moves the internal pointer to the next ID and timestamp if the current trigger is this moment in time or in the past.
		// Save the results of this function if you want to reuse them.
		public function NextTrigger()
		{
			$result = $this->data["nexttrigger"];

			if ($this->data["nexttrigger"] === false || $this->data["nexttrigger"]["ts"] <= $this->now)
			{
				if (!count($this->data2["cachedcalendar"]))  $this->RebuildCalendar();
				else
				{
					$temptz = new CalendarEvent_TZSwitch($this->data["tz"]);

					// Shifts the calendar to the next month if applicable.
					$year = (int)date("Y", $this->now);
					$month = (int)date("m", $this->now);
					if (($this->data2["firstcalyear"] + 1 == $year && $month == 1 && $this->data2["firstcalmonth"] == 12) || ($this->data2["firstcalyear"] == $year && $this->data2["firstcalmonth"] + 1 == $month))
					{
						unset($this->data2["cachedcalendar"][$this->data2["firstcalyear"] . "-" . $this->data2["firstcalmonth"]]);
						$this->data2["firstcalyear"] = $year;
						$this->data2["firstcalmonth"] = $month;

						$this->AddNextMonthToCalendar();
						$this->FindNextTrigger();
					}
					else if ($this->data2["firstcalyear"] != $year || $this->data2["firstcalmonth"] != $month)
					{
						$this->RebuildCalendar();
					}
				}
			}

			return $result;
		}

		// Builds at least a two-month calendar of days that have schedules and which schedules fire on those days.
		// Also removes any schedules and exceptions that have expired and recalculates the next scheduled triggers.
		// More months may be generated depending on notification triggers (if any).
		public function RebuildCalendar()
		{
			$this->data2["cachedschedules"] = array();
			$this->data2["cachedexceptions"] = array();
			$this->data2["cachedcalendar"] = array();
			$this->data["nexttrigger"] = false;

			// Don't generate anything for empty schedules.
			if (!count($this->data["schedules"]) && !count($this->data["exceptions"]))  return;

			$temptz = new CalendarEvent_TZSwitch($this->data["tz"]);

			// Generate a couple of months and then find the next trigger point.
			$this->data2["firstcalyear"] = $this->data2["nextcalyear"] = (int)date("Y", $this->now);
			$this->data2["firstcalmonth"] = $this->data2["nextcalmonth"] = (int)date("m", $this->now);
			$this->AddNextMonthToCalendar();
			$this->AddNextMonthToCalendar();
			$this->FindNextTrigger();
		}

		// Adds the next month to the cached calendar.
		private function AddNextMonthToCalendar()
		{
			$info = $this->GetCalendar($this->data2["nextcalyear"], $this->data2["nextcalmonth"], true);
			$this->data2["cachedcalendar"][$this->data2["nextcalyear"] . "-" . $this->data2["nextcalmonth"]] = $info["calendar"];

			$this->data2["lastcalmonth"] = $this->data2["nextcalmonth"];
			$this->data2["lastcalyear"] = $this->data2["nextcalyear"];

			$this->data2["nextcalmonth"]++;
			if ($this->data2["nextcalmonth"] > 12)
			{
				$this->data2["nextcalyear"]++;
				$this->data2["nextcalmonth"] = 1;
			}
		}

		// Finds the next trigger point.
		private function FindNextTrigger()
		{
			$this->data["nexttrigger"] = false;

			// Check today's date for a future trigger today.
			$year = $this->data2["firstcalyear"];
			$month = $this->data2["firstcalmonth"];
			$day = (int)date("d", $this->now);
			if (isset($this->data2["cachedcalendar"][$year . "-" . $month][$day]))
			{
				$info = $this->data2["cachedcalendar"][$year . "-" . $month][$day];
				$ts = (int)date("H", $this->now) * 3600 + (int)date("i", $this->now) * 60 + (int)date("s", $this->now);
				$newts = false;
				if (isset($info["ids"]))
				{
					foreach ($info["ids"] as $id)
					{
						$info2 = $this->GetTimes($id);
						$newts = $this->FindNextTriggerToday($ts, $newts, $info2, "id", $id);
					}
				}
				if (isset($info["exceptions"]))
				{
					foreach ($info["exceptions"] as $srcdate)
					{
						$info2 = $this->GetExceptionTimes($srcdate);
						$newts = $this->FindNextTriggerToday($ts, $newts, $info2, "exception", $srcdate);
					}
				}

				if ($newts !== false)
				{
					$this->data["nexttrigger"] = $this->ExpandNewTS($newts, $year, $month, $day);

					return;
				}
			}

			// Calculate the first timestamp for each schedule to improve performance.
			$idmap = array();
			foreach ($this->data["schedules"] as $id => $schedule)
			{
				$info = $this->GetTimes($id);
				$hour = array_keys($info["hours"]);
				$hour = $hour[0];
				$min = array_keys($info["mins"]);
				$min = $min[0];
				$sec = array_keys($info["secs"]);
				$sec = $sec[0];
				$idmap[$id] = $hour * 3600 + $min * 60 + $sec;
			}

			// Check remaining days of the current month.
			foreach ($this->data2["cachedcalendar"][$year . "-" . $month] as $day2 => $info)
			{
				if ($day2 > $day)
				{
					$newts = false;
					if (isset($info["ids"]))
					{
						foreach ($info["ids"] as $id)
						{
							if ($newts === false || $newts["ts"] > $idmap[$id])  $newts = array("type" => "id", "id" => $id, "ts" => $idmap[$id]);
						}
					}

					if (isset($info["exceptions"]))
					{
						foreach ($info["exceptions"] as $srcdate)
						{
							$info2 = $this->GetExceptionTimes($srcdate);
							$hour = array_keys($info2["hours"]);
							$hour = $hour[0];
							$min = array_keys($info2["mins"]);
							$min = $min[0];
							$sec = array_keys($info2["secs"]);
							$sec = $sec[0];
							$ts2 = $hour * 3600 + $min * 60 + $sec;
							if ($newts === false || $newts["ts"] > $ts2)  $newts = array("type" => "exception", "id" => $srcdate, "ts" => $ts2);
						}
					}

					$this->data["nexttrigger"] = $this->ExpandNewTS($newts, $year, $month, $day2);

					return;
				}
			}

			// Look ahead to up to 12 more months.
			while (count($this->data2["cachedcalendar"]) < 13 && !count($this->data2["cachedcalendar"][$this->data2["lastcalyear"] . "-" . $this->data2["lastcalmonth"]]))  $this->AddNextMonthToCalendar();
			if (count($this->data2["cachedcalendar"][$this->data2["lastcalyear"] . "-" . $this->data2["lastcalmonth"]]))
			{
				// Last month calculated is guaranteed to have an ID and timestamp.
				$year = $this->data2["lastcalyear"];
				$month = $this->data2["lastcalmonth"];
				$info = $this->data2["cachedcalendar"][$year . "-" . $month];
				$day = array_keys($info);
				$day = $day[0];
				$info = $info[$day];

				$newts = false;
				if (isset($info["ids"]))
				{
					foreach ($info["ids"] as $id)
					{
						if ($newts === false || $newts["ts"] > $idmap[$id])  $newts = array("type" => "id", "id" => $id, "ts" => $idmap[$id]);
					}
				}

				if (isset($info["exceptions"]))
				{
					foreach ($info["exceptions"] as $srcdate)
					{
						$info2 = $this->GetExceptionTimes($srcdate);
						$hour = array_keys($info2["hours"]);
						$hour = $hour[0];
						$min = array_keys($info2["mins"]);
						$min = $min[0];
						$sec = array_keys($info2["secs"]);
						$sec = $sec[0];
						$ts2 = $hour * 3600 + $min * 60 + $sec;
						if ($newts === false || $newts["ts"] > $ts2)  $newts = array("type" => "exception", "id" => $srcdate, "ts" => $ts2);
					}
				}

				$this->data["nexttrigger"] = $this->ExpandNewTS($newts, $year, $month, $day);
			}
		}

		public static function FindNextTriggerToday($ts, $newts, &$info, $type, $id)
		{
			foreach ($info["hours"] as $hour => $val)
			{
				$tsh = $hour * 3600;
				if ($ts < $tsh)
				{
					// Future hour located.
					$min = array_keys($info["mins"]);
					$min = $min[0];
					$sec = array_keys($info["secs"]);
					$sec = $sec[0];
					$ts2 = $tsh + $min * 60 + $sec;
					if ($newts === false || $ts2 < $newts["ts"])  $newts = array("type" => $type, "id" => $id, "ts" => $ts2);

					break;
				}
				else if ($ts < $tsh + 3600)
				{
					// Valid hour found.  Locate minute.
					foreach ($info["mins"] as $min => $val)
					{
						$tsm = $tsh + $min * 60;
						if ($ts < $tsm)
						{
							// Future minute located.
							$sec = array_keys($info["secs"]);
							$sec = $sec[0];
							$ts2 = $tsm + $sec;
							if ($newts === false || $ts2 < $newts["ts"])  $newts = array("type" => $type, "id" => $id, "ts" => $ts2);

							break;
						}
						else if ($ts < $tsm + 60)
						{
							// Valid minute found.  Locate second.
							foreach ($info["secs"] as $sec => $val)
							{
								$tss = $tsm + $sec;
								if ($ts < $tss)
								{
									// Future second located.
									if ($newts === false || $tss < $newts["ts"])  $newts = array("type" => $type, "id" => $id, "ts" => $tss);

									break;
								}
							}
						}
					}
				}
			}

			return $newts;
		}

		// Internal function to expand a timestamp to its correct value (auto-adjusts for DST).
		public static function ExpandNewTS($newts, $year, $month, $day)
		{
			if ($newts === false)  return false;

			$secs = $newts["ts"] % 60;
			$newts["ts"] = (int)($newts["ts"] / 60);
			$mins = $newts["ts"] % 60;
			$hours = (int)($newts["ts"] / 60);
			$newts["ts"] = mktime($hours, $mins, $secs, $month, $day, $year);
			if ($newts["ts"] === false || $newts["ts"] === -1)  return false;

			return $newts;
		}

		private function IsValidExpr(&$result, $expr, $minnum, $maxnum, $namemap = array(), $exprprefix = array(), $hours = false)
		{
			$result = $this->ParseExpr($expr, $minnum, $maxnum, $namemap, $exprprefix, $hours);

			return $result["success"];
		}

		private function ParseExpr($expr, $minnum, $maxnum, $namemap = array(), $exprprefix = array(), $hours = false)
		{
			$result = array("success" => true, "orig" => $expr, "prefixes" => array());

			$expr = strtolower($expr);

			// Process prefixes.
			do
			{
				$found = false;
				foreach ($exprprefix as $prefix)
				{
					if (strtolower(substr($expr, 0, strlen($prefix))) == $prefix)
					{
						$expr = substr($expr, strlen($prefix));
						$result["prefixes"][$prefix] = true;
						$found = true;
					}
				}
			} while ($found);

			$result["orignoprefix"] = $expr;

			// Process each comma-separated entry.
			$expr = explode(",", $expr);
			$expanded = array();
			foreach ($expr as $expr2)
			{
				$pos = strrpos($expr2, "/");
				if ($pos === false)
				{
					$expr2 = explode("-", $expr2);
					if (count($expr2) == 1 && $expr2[0] == "*")
					{
						// All values.
						for ($x = $minnum; $x <= $maxnum; $x++)  $expanded[$x] = true;
					}
					else if (count($expr2) == 1 && $expr2[0] != "")
					{
						// Single value.
						$expr2 = $expr2[0];
						if (isset($namemap[$expr2]))  $expr2 = $namemap[$expr2];
						if ($hours)  $expr2 = $this->ParseHour($expr2);
						$expr2 = (int)$expr2;
						if ($expr2 < $minnum)  return array("success" => false, "error" => "'" . $expr2 . "' is out of range (less than " . $minnum . ").");
						if ($expr2 > $maxnum)  return array("success" => false, "error" => "'" . $expr2 . "' is out of range (greater than " . $maxnum . ").");

						$expanded[$expr2] = true;
					}
					else if (count($expr2) > 2 || $expr2[0] == "")  return array("success" => false, "error" => "Invalid '-' found.");
					else
					{
						// Single range.
						$expr3 = $expr2[1];
						$expr2 = $expr2[0];
						if (isset($namemap[$expr2]))  $expr2 = $namemap[$expr2];
						if ($hours)  $expr2 = $this->ParseHour($expr2);
						$expr2 = (int)$expr2;
						if ($expr2 < $minnum)  return array("success" => false, "error" => "'" . $expr2 . "' is out of range (less than " . $minnum . ").");
						if ($expr2 > $maxnum)  return array("success" => false, "error" => "'" . $expr2 . "' is out of range (greater than " . $maxnum . ").");

						if (isset($namemap[$expr3]))  $expr3 = $namemap[$expr3];
						if ($hours)  $expr3 = $this->ParseHour($expr3);
						$expr3 = (int)$expr3;
						if ($expr3 < $minnum)  return array("success" => false, "error" => "'" . $expr3 . "' is out of range (less than " . $minnum . ").");
						if ($expr3 > $maxnum)  return array("success" => false, "error" => "'" . $expr3 . "' is out of range (greater than " . $maxnum . ").");

						$expanded[$expr3] = true;
						while ($expr2 != $expr3)
						{
							$expanded[$expr2] = true;
							$expr2++;
							if ($expr2 > $maxnum)  $expr2 = $minnum;
						}
					}
				}
				else if (strpos($expr2, "-") !== false)  return array("success" => false, "error" => "Invalid '-' found in combination with '/'.");
				else
				{
					// Multiple single values.
					$skipnum = (int)substr($expr2, $pos + 1);
					if ($skipnum < 1)  return array("success" => false, "error" => "Invalid numerical skip amount '" . $skipnum . "' after '/'.");

					$expr2 = substr($expr2, 0, $pos);
					if (isset($namemap[$expr2]))  $expr2 = $namemap[$expr2];
					if ($hours)  $expr2 = $this->ParseHour($expr2);
					$expr2 = (int)$expr2;
					if ($expr2 < $minnum)  return array("success" => false, "error" => "'" . $expr2 . "' is out of range (less than " . $minnum . ").");
					if ($expr2 > $maxnum)  return array("success" => false, "error" => "'" . $expr2 . "' is out of range (greater than " . $maxnum . ").");

					$expanded[$expr2] = true;
					$expr2 += $skipnum;

					while ($expr2 <= $maxnum)
					{
						$expanded[$expr2] = true;
						$expr2 += $skipnum;
					}
				}
			}

			ksort($expanded);

			if (!count($expanded))  return array("success" => false, "error" => "Expression does not expand to any values.");

			$result["expanded"] = $expanded;

			return $result;
		}

		private function ParseHour($hour)
		{
			if (stripos($hour, "a") !== false)  $ampm = "a";
			else if (stripos($hour, "p") !== false)  $ampm = "p";
			else  $ampm = false;

			$hour = (int)trim(preg_replace('/[^0-9]/', " ", $hour));

			if ($ampm === "a")
			{
				if ($hour < 1 || $hour > 12)  return -1;
				else if ($hour == 12)  $hour = 0;
			}
			else if ($ampm === "p")
			{
				if ($hour < 1 || $hour > 12)  return -1;
				else if ($hour != 12)  $hour += 12;
			}

			return (int)$hour;
		}

		private function IsValidDate(&$result, $date, $start = false)
		{
			$result = $this->ParseDate($date, $start);

			return $result["success"];
		}

		private function ParseDate($date, $start = false)
		{
			$result = array("success" => true, "orig" => $date);

			$pos = strpos($date, "-");
			if ($pos === false)  return array("success" => false, "error" => "Invalid date format.  Must be YYYY-MM-DD.");
			$year = (int)substr($date, 0, $pos);
			$result["year"] = $year;
			if (!$year)  return array("success" => false, "error" => "Invalid date format.  Invalid 'year'.  Must be YYYY-MM-DD.");
			$date = substr($date, $pos + 1);

			$pos = strpos($date, "-");
			if ($pos === false)  return array("success" => false, "error" => "Invalid date format.  Must be YYYY-MM-DD.");
			$month = (int)substr($date, 0, $pos);
			$result["month"] = $month;
			if (!$month || $month > 12)  return array("success" => false, "error" => "Invalid date format.  Invalid 'month' or out of range (1-12).  Must be YYYY-MM-DD.");
			$date = substr($date, $pos + 1);

			$pos = ($start ? strpos($date, "/") : false);
			if ($pos === false)  $day = (int)$date;
			else
			{
				$day = (int)substr($date, 0, $pos);
				$date = substr($date, $pos + 1);
				$pos = strpos($date, "/");
				if ($pos === false)
				{
					$skipdays = (int)$date;
					if ($skipdays < 1)  $skipdays = 1;

					$result["skipdays"] = $skipdays;
				}
				else
				{
					$skipdays = (int)substr($date, 0, $pos);
					if ($skipdays < 1)  $skipdays = 1;
					$date = substr($date, $pos + 1);
					$skipweeks = (int)$date;
					if ($skipweeks < 1)  $skipweeks = 1;

					$result["skipdays"] = $skipdays;
					$result["skipweeks"] = $skipweeks;
				}
			}
			$result["day"] = $day;

			$result["firstofmonth"] = mktime(0, 0, 0, $month, 1, $year);
			if ($result["firstofmonth"] === false || $result["firstofmonth"] == -1)  return array("success" => false, "error" => "Invalid date format.  Specified year and month are out of range (OS limitations).");
			$result["daysinmonth"] = date("t", $result["firstofmonth"]);
			if (!$day || $day > $result["daysinmonth"])  return array("success" => false, "error" => "Invalid date format.  Invalid 'day' or out of range (1 through 28-31).  Must be YYYY-MM-DD.");

			$result["ts"] = mktime(0, 0, 0, $month, $day, $year);
			if ($result["ts"] === false || $result["ts"] == -1)  return array("success" => false, "error" => "Invalid date format.  Specified date is out of range (OS limitations).");

			return $result;
		}
	}

	class CalendarEvent_TZSwitch
	{
		private $origtz, $newtz;

		public function __construct($new)
		{
			$tz = (function_exists("date_default_timezone_get") ? @date_default_timezone_get() : @ini_get("date.timezone"));
			if ($tz == "")  $tz = "UTC";

			$this->origtz = $tz;
			$this->newtz = $new;

			if ($this->origtz != $this->newtz)
			{
				if (function_exists("date_default_timezone_set"))  @date_default_timezone_set($this->newtz);
				else  @ini_set($this->newtz);
			}
		}

		public function __destruct()
		{
			if ($this->origtz != $this->newtz)
			{
				if (function_exists("date_default_timezone_set"))  @date_default_timezone_set($this->origtz);
				else  @ini_set($this->origtz);
			}
		}
	}
?>