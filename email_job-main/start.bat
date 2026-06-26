@echo off
title ReachOut — WhatsApp Service
echo.
echo  Starting ReachOut WhatsApp Service...
echo  Open your browser at: http://localhost:3001
echo.
cd /d "%~dp0whatsapp-service"
node server.js
pause
