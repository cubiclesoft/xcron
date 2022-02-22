@echo off

rem Elevate a Command Prompt to NT AUTHORITY\SYSTEM.
.\support\windows\createprocess-win.exe /systemtoken /mergeenv %SystemRoot%\System32\cmd.exe /K title SYSTEM Command Prompt
