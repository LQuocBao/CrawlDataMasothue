#!/bin/bash
#=============================================================================
# Development Environment Setup Script
# Run once after cloning the repository.
#
# Usage: chmod +x scripts/setup-dev.sh && ./scripts/setup-dev.sh
#=============================================================================

set -e

echo "🛠️  Setting up development environment..."

# ─── Backend Setup ─────────────────────────────────────────────────────────
echo "📦 Setting up Laravel backend..."
cd backend

if [ ! -f .env ]; then
    cp .env.example .env
    echo "  ✅ Created .env from .env.example"
fi

# Install PHP dependencies
if command -v composer &> /dev/null; then
    composer install
    echo "  ✅ Composer dependencies installed"
else
    echo "  ⚠️  Composer not found. Will install via Docker."
fi

cd ..

# ─── Frontend Setup ────────────────────────────────────────────────────────
echo "📦 Setting up Next.js frontend..."
cd frontend

if [ ! -f .env.local ]; then
    cp .env.local.example .env.local
    echo "  ✅ Created .env.local from .env.local.example"
fi

if command -v npm &> /dev/null; then
    npm install
    echo "  ✅ npm dependencies installed"
else
    echo "  ⚠️  npm not found. Will install via Docker."
fi

cd ..

# ─── Docker Setup ──────────────────────────────────────────────────────────
echo "🐳 Starting Docker services..."

if [ ! -f .env ]; then
    cp .env.docker .env
    echo "  ✅ Created root .env from .env.docker"
fi

docker compose up -d

echo ""
echo "⏳ Waiting for MySQL to be ready..."
sleep 10

# ─── Database Setup ────────────────────────────────────────────────────────
echo "🗄️  Running migrations..."
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

echo ""
echo "═══════════════════════════════════════════════════"
echo "  ✅ Development environment ready!"
echo ""
echo "  🌐 Frontend: http://localhost:3000"
echo "  🔌 API:      http://localhost:8000"
echo "  🗄️  MySQL:    localhost:3306"
echo "  📮 Redis:    localhost:6379"
echo ""
echo "  📝 Useful commands:"
echo "     docker compose logs -f app      # Backend logs"
echo "     docker compose logs -f worker   # Queue worker logs"
echo "     docker compose logs -f scheduler # Scheduler logs"
echo "     docker compose exec app php artisan scrape:companies --dry-run"
echo "═══════════════════════════════════════════════════"
