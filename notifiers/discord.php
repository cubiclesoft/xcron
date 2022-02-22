<?php
	// Discord notifier for xcron.
	// (C) 2022 CubicleSoft.  All Rights Reserved.

	class XCronNotifier_discord
	{
		public function __construct()
		{
			$rootpath = str_replace("\\", "/", dirname(__FILE__));

			require_once $rootpath . "/../support/sdk_discord.php";
			require_once $rootpath . "/../support/http.php";
		}

		public function CheckValid(&$notifyinfo)
		{
			if (!isset($notifyinfo["hook_url"]))  return array("success" => false, "error" => "Missing hook URL.", "errorcode" => "missing_hook_url");
			if (!is_string($notifyinfo["hook_url"]))  return array("success" => false, "error" => "Invalid hook URL.  Expected a string.", "errorcode" => "invalid_hook_url");
			if (!isset($notifyinfo["params"]))  return array("success" => false, "error" => "Missing params.", "errorcode" => "missing_params");
			if (!is_array($notifyinfo["params"]))  return array("success" => false, "error" => "Invalid params.  Expected an object.", "errorcode" => "invalid_params");
			if (isset($notifyinfo["error_limit"]) && !is_int($notifyinfo["error_limit"]))  return array("success" => false, "error" => "Invalid 'error_limit'.  Expected an integer.", "errorcode" => "invalid_error_limit");

			$url = HTTP::ExtractURL($notifyinfo["hook_url"]);
			if ($url["scheme"] !== "https")  return array("success" => false, "error" => "Invalid hook URL.  Expected HTTPS.", "errorcode" => "invalid_hook_url");

			if ($url["host"] !== "discord.com")  return array("success" => true, "warning" => "Possibly invalid hook URL domain.  Expected 'discord.com'.");
			if (substr($url["path"], 0, 14) !== "/api/webhooks/")  return array("success" => true, "warning" => "Possibly invalid hook URL path.  Expected '/api/webhooks/...'.");

			return array("success" => true);
		}

		public function Notify($notifykey, &$notifyinfo, $numerrors, &$sinfo, $schedulekey, $name, $userdisp, $data)
		{
			if (isset($notifyinfo["error_limit"]) && $numerrors > $notifyinfo["error_limit"])  return array("success" => false, "error" => "Notification not sent due to exceeding error limit.", "errorcode" => "limit_exceeded");

			$options = $notifyinfo["params"];
			$options["content"] = $userdisp . " | " . $name . "\n" . json_encode($data, JSON_UNESCAPED_SLASHES);

			$result = DiscordSDK::SendWebhookMessage($notifyinfo["hook_url"], $options);
			if (!$result["success"])  return $result;

			return array("success" => true);
		}
	}
?>