@echo off
REM Internship Tracking System - database integrity check.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0db-check.ps1" %*

