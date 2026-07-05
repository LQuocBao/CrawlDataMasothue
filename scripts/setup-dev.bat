@echo off
REM ===========================================================================
REM Development Environment Setup Script (Windows)
REM Run once after cloning the repository.
REM
REM Usage: scripts\setup-dev.bat
REM ===========================================================================

echo [*] Setting up development environment...

REM --- Backend Setup --------------------------------------------------------
echo [*] Setting up Laravel backend...
cd backend

if not exist .env (
    copy .env.example .env
    echo     [OK] Created .env from .env.example
)

where composer >nul 2>nul
if %ERRORLEVEL% equ 0 (
    composer install
    echo     [OK] Composer dependencies installed
) else (
    echo     [!] Composer not found. Will install via Docker.
)

cd ..

REM --- Frontend Setup -------------------------------------------------------
echo [*] Setting up Next.js frontend...
cd frontend

if not exist .env.local (
    copy .env.local.example .env.local
    echo     [OK] Created .env.local from .env.local.example
)

where npm >nul 2>nul
if %ERRORLEVEL% equ 0 (
    npm install
    echo     [OK] npm dependencies installed
) else (
    echo     [!] npm not found. Will install via Docker.
)

cd ..

REM --- Docker Setup ---------------------------------------------------------
echo [*] Starting Docker services...

if not exist .env (
    copy .env.docker .env
    echo     [OK] Created root .env from .env.docker
)

docker compose up -d

echo.
echo [*] Waiting for MySQL to be ready...
timeout /t 10 /nobreak >nul

REM --- Database Setup -------------------------------------------------------
echo [*] Running migrations...
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

echo.
echo ===================================================
echo   [OK] Development environment ready!
echo.
echo   Frontend: http://localhost:3000
echo   API:      http://localhost:8000
echo   MySQL:    localhost:3306
echo   Redis:    localhost:6379
echo.
echo   Useful commands:
echo     docker compose logs -f app
echo     docker compose logs -f worker
echo     docker compose exec app php artisan scrape:companies --dry-run
echo ===================================================
