@echo off
REM Internship Tracking System - manual/automatic database backup.
REM Double-click or let Task Scheduler run this file.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0db-backup.ps1" %*
