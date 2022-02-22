<?php
	// Client SDK for xcron server.
	// (C) 2022 CubicleSoft.  All Rights Reserved.

	class XCronServer
	{
		protected $fp, $debug, $outputmap, $schedulechanges;

		public function __construct()
		{
			$this->fp = false;
			$this->debug = false;
			$this->outputmap = array();
			$this->schedulechanges = array();
		}

		public function __destruct()
		{
			$this->Disconnect();
		}

		public function SetDebug($debug)
		{
			$this->debug = (bool)$debug;
		}

		public function Connect($host = "127.0.0.1", $port = 10829)
		{
			$context = stream_context_create();

			$this->fp = @stream_socket_client("tcp://" . $host . ":" . $port, $errornum, $errorstr, 3, STREAM_CLIENT_CONNECT, $context);
			if ($this->fp === false)  return array("success" => false, "error" => self::XCSTranslate("Unable to connect to the xcron server.  Try again later."), "errorcode" => "connect_failed");

			return array("success" => true);
		}

		public function Disconnect()
		{
			if ($this->fp !== false)
			{
				fclose($this->fp);

				$this->fp = false;
			}
		}

		// Useful for applications that need to specifically disable user-based actions.
		public function SetPasswordOnly()
		{
			$data = array(
				"action" => "set_password_only"
			);

			return $this->RunAPI($data);
		}

		public function GetServerInfo()
		{
			$data = array(
				"action" => "get_server_info"
			);

			return $this->RunAPI($data);
		}

		public function GetSchedules($stats = true, $watch = false, $filtername = false, $filteruser = false, $filterelevated = null)
		{
			$data = array(
				"action" => "get_schedules",
				"stats" => (bool)$stats,
				"watch" => (bool)$watch
			);

			if (is_string($filtername))  $data["name"] = $filtername;
			if ($filteruser === true || is_string($filteruser))  $data["user"] = $filteruser;
			if ($filterelevated !== null)  $data["elevated"] = (bool)$filterelevated;

			return $this->RunAPI($data);
		}

		public function StopWatchingScheduleChanges()
		{
			$data = array(
				"action" => "stop_watching_schedules"
			);

			return $this->RunAPI($data);
		}

		public function GetXCrontab($user = false, $elevated = false)
		{
			$data = array(
				"action" => "get_xcrontab"
			);

			if (is_string($user))  $data["user"] = $user;
			if ($elevated !== false)  $data["elevated"] = true;

			return $this->RunAPI($data);
		}

		public function SetXCrontab($xcrontabdata, $user = false, $elevated = false)
		{
			$data = array(
				"action" => "set_xcrontab",
				"data" => base64_encode((string)$xcrontabdata)
			);

			if (is_string($user))  $data["user"] = $user;
			if ($elevated !== false)  $data["elevated"] = true;

			return $this->RunAPI($data);
		}

		public function Reload($user = false, $elevated = false)
		{
			$data = array(
				"action" => "reload"
			);

			if (is_string($user))  $data["user"] = $user;
			if ($elevated !== false)  $data["elevated"] = true;

			return $this->RunAPI($data);
		}

		public function TriggerRun($name, $options = array())
		{
			$data = array(
				"action" => "trigger_run",
				"name" => (string)$name
			);

			if (isset($options["data"]))  $data["data"] = $options["data"];
			if (isset($options["force"]) && $options["force"] != false)  $data["force"] = true;
			if (isset($options["user"]) && is_string($options["user"]))  $data["user"] = $options["user"];
			if (isset($options["elevated"]) && $options["elevated"] != false)  $data["elevated"] = true;
			if (isset($options["password"]) && is_string($options["password"]))  $data["password"] = $options["password"];
			if (isset($options["watch"]) && $options["watch"] != false)  $data["watch"] = true;

			return $this->RunAPI($data);
		}

		public function SetNextRunTime($name, $ts, $minonly, $options = array())
		{
			$data = array(
				"action" => "set_next_run_time",
				"name" => (string)$name,
				"ts" => (int)$ts,
				"min_only" => (bool)$minonly
			);

			if (isset($options["user"]) && is_string($options["user"]))  $data["user"] = $options["user"];
			if (isset($options["elevated"]) && $options["elevated"] != false)  $data["elevated"] = true;
			if (isset($options["password"]) && is_string($options["password"]))  $data["password"] = $options["password"];

			return $this->RunAPI($data);
		}

		public function TestNotifications($name, $options = array())
		{
			$data = array(
				"action" => "test_notifications",
				"name" => (string)$name
			);

			if (isset($options["user"]) && is_string($options["user"]))  $data["user"] = $options["user"];
			if (isset($options["elevated"]) && $options["elevated"] != false)  $data["elevated"] = true;
			if (isset($options["password"]) && is_string($options["password"]))  $data["password"] = $options["password"];

			return $this->RunAPI($data);
		}

		public function SuspendScheduleUntil($name, $ts, $skipmissed, $options = array())
		{
			$data = array(
				"action" => "suspend_schedule",
				"name" => (string)$name,
				"ts" => (int)$ts,
				"skip_missed" => (bool)$skipmissed
			);

			if (isset($options["user"]) && is_string($options["user"]))  $data["user"] = $options["user"];
			if (isset($options["elevated"]) && $options["elevated"] != false)  $data["elevated"] = true;

			return $this->RunAPI($data);
		}

		public function GetRunOutput($name, $triggered, $errorlog, $stream, $options = array())
		{
			$data = array(
				"action" => "get_run_output",
				"name" => (string)$name,
				"triggered" => (bool)$triggered,
				"error_log" => (bool)$errorlog,
				"stream" => (bool)$stream
			);

			if (isset($options["user"]) && is_string($options["user"]))  $data["user"] = $options["user"];
			if (isset($options["elevated"]) && $options["elevated"] != false)  $data["elevated"] = true;
			if (isset($options["password"]) && is_string($options["password"]))  $data["password"] = $options["password"];

			return $this->RunAPI($data);
		}

		public function AttachFutureProcess($name, $type = "any", $options = array())
		{
			$type = strtolower($type);

			$data = array(
				"action" => "attach_process",
				"name" => (string)$name,
				"type" => ($type === "schedule" || $type === "triggered" ? $type : "any")
			);

			if (isset($options["limit"]) && is_int($options["limit"]) && $options["limit"] > 0)  $data["limit"] = $options["limit"];
			if (isset($options["user"]) && is_string($options["user"]))  $data["user"] = $options["user"];
			if (isset($options["elevated"]) && $options["elevated"] != false)  $data["elevated"] = true;
			if (isset($options["password"]) && is_string($options["password"]))  $data["password"] = $options["password"];

			return $this->RunAPI($data);
		}

		public function AttachProcessByID($id, $password = false)
		{
			$data = array(
				"action" => "attach_process",
				"id" => (int)$id
			);

			if (is_string($password))  $data["password"] = $password;

			return $this->RunAPI($data);
		}

		public function AttachProcessByPID($pid, $password = false)
		{
			$data = array(
				"action" => "attach_process",
				"pid" => (int)$pid
			);

			if (is_string($password))  $data["password"] = $password;

			return $this->RunAPI($data);
		}

		public function DetachProcess($id, $fileid)
		{
			$data = array(
				"action" => "detach_process",
				"id" => (int)$id,
				"file_id" => (int)$fileid
			);

			return $this->RunAPI($data);
		}

		public function Wait($timeout)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::XCSTranslate("Not connected to the xcron server."), "errorcode" => "not_connected");

			stream_set_blocking($this->fp, 0);

			if ($timeout > 120)  $timeout = 120;

			$readfps = array($this->fp);
			$writefps = array();
			$exceptfps = array();

			$result = @stream_select($readfps, $writefps, $exceptfps, $timeout);
			if ($result === false)  return array("success" => false, "error" => self::XCSTranslate("Stream encountered an error.  Connection dropped."), "errorcode" => "stream_error");

			$data = @fgets($this->fp);

			if ($data == "")
			{
				if (feof($this->fp))  return array("success" => false, "error" => self::XCSTranslate("Read error.  Connection dropped."), "errorcode" => "stream_error");

				fwrite($this->fp, " ");
			}
			else
			{
				if ($this->debug)
				{
					echo "------- RAW RECEIVE START (" . strlen($data) . " bytes) -------\n";
					echo $data;
					echo "------- RAW RECEIVE END -------\n\n";
				}

				$data = @json_decode($data, true);
				if (!is_array($data))  return array("success" => false, "error" => self::XCSTranslate("Unable to decode the response from the xcron server."), "errorcode" => "decoding_failed");

				// Handle monitoring data internally.
				if (!isset($data["monitor"]))  return $data;

				if ($data["monitor"] === "schedule")  $this->schedulechanges[] = $data;

				if ($data["monitor"] === "output" && isset($data["id"]))
				{
					if (!isset($this->outputmap[$data["id"]]))  $this->outputmap[$data["id"]] = array();

					$this->outputmap[$data["id"]][] = $data;
				}
			}

			return array("success" => false, "error" => self::XCSTranslate("No data returned."), "errorcode" => "no_data");
		}

		public function GetNextScheduleChange()
		{
			if (!count($this->schedulechanges))  return false;

			return array_shift($this->schedulechanges);
		}

		public function GetNextOutputData($id = false)
		{
			if ($id === false)
			{
				foreach ($this->outputmap as $id => &$items)
				{
					break;
				}
			}

			if ($id === false || !isset($this->outputmap[$id]))  return false;

			$result = array_shift($this->outputmap[$id]);

			if (!count($this->outputmap[$id]))  unset($this->outputmap[$id]);

			return $result;
		}

		public function RunAPI($data)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::XCSTranslate("Not connected to the xcron server."), "errorcode" => "not_connected");

			stream_set_blocking($this->fp, 1);

			// Send the request.
			$data = json_encode($data, JSON_UNESCAPED_SLASHES) . "\n";
			$result = @fwrite($this->fp, $data);
			if ($this->debug)
			{
				echo "------- RAW SEND START -------\n";
				echo substr($data, 0, $result);
				echo "------- RAW SEND END -------\n\n";
			}
			if ($result < strlen($data))  return array("success" => false, "error" => self::XCSTranslate("Failed to complete sending request to the xcron server."), "errorcode" => "service_request_failed");

			// Wait for the response.
			do
			{
				$result = $this->Wait(30);
			} while (!$result["success"] && $result["errorcode"] === "no_data");

			return $result;
		}

		protected static function XCSTranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>