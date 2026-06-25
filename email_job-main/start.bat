@echo off
title ReachOut — Starting...
color 0A

echo.
echo  ██████╗ ███████╗ █████╗  ██████╗██╗  ██╗ ██████╗ ██╗   ██╗████████╗
echo  ██╔══██╗██╔════╝██╔══██╗██╔════╝██║  ██║██╔═══██╗██║   ██║╚══██╔══╝
echo  ██████╔╝█████╗  ███████║██║     ███████║██║   ██║██║   ██║   ██║
echo  ██╔══██╗██╔══╝  ██╔══██║██║     ██╔══██║██║   ██║██║   ██║   ██║
echo  ██║  ██║███████╗██║  ██║╚██████╗██║  ██║╚██████╔╝╚██████╔╝   ██║
echo  ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝ ╚═════╝╚═╝  ╚═╝ ╚═════╝  ╚═════╝    ╚═╝
echo.
echo  Job Automation Platform
echo  ─────────────────────────────────────────────────
echo.

cd /d "%~dp0whatsapp-service"

echo  [1/2] Checking node_modules...
if not exist node_modules (
    echo  Installing dependencies...
    npm install
)

echo  [2/2] Starting server on http://localhost:3001
echo.
echo  Opening browser in 2 seconds...
timeout /t 2 /nobreak >nul
start "" "http://localhost:3001"

echo.
echo  ✅ App is running at http://localhost:3001
echo  ✅ Press Ctrl+C to stop
echo  ─────────────────────────────────────────────────
echo.

node server.js
