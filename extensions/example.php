<?php
	// Example xcron server extension.
	// (C) 2022 CubicleSoft.  All Rights Reserved.

	// This approach to extension design is recommended to avoid naming conflicts with both xcron and custom xcron server extensions.
	class YourCompany_ExtName
	{
		public static $instance;

		public static function Init()
		{
			// Do some initialization here.
		}
	}

	// Register to receive various event notifications (aka callbacks).
	XCronHelper::EventRegister("init", "YourCompany_ExtName::Init");


	// OR if you prefer a more object-oriented approach.
	YourCompany_ExtName::$instance = new YourCompany_ExtName();

	XCronHelper::EventRegister("init", YourCompany_ExtName::$instance, "Init");
?>