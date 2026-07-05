#!/bin/bash
#=============================================================================
# Production Deployment Script
# Run on VPS after git pull or CI/CD pipeline push.
#
# Usage: chmod +x scripts/deploy.sh && ./scripts/deploy.sh
#=============================================================================

set -e

echo "🚀 Starting deployment..."

# ─── Configuration ─────────────────────────────────────────────────────────
APP_DIR="/www/wwwroot/masothue-scraper"
COMPOSE_FILE="docker-compose.prod.yml"

# ─── Pull latest code ──────────────────────────────────────────────────────
echo "📥 Pulling latest changes..."
cd "$APP_DIR"
git pull origin main

# ─── Build and restart containers ──────────────────────────────────────────
echo "🏗️  Building containers..."
docker compose -f "$COMPOSE_FILE" build --no-cache app worker scheduler frontend

echo "🔄 Restarting services..."
docker compose -f "$COMPOSE_FILE" up -d --remove-orphans

# ─── Run migrations ───────────────────────────────────────────────────────
echo "📦 Running migrations..."
docker compose -f "$COMPOSE_FILE" exec app php artisan migrate --force

# ─── Clear and rebuild caches ──────────────────────────────────────────────
echo "🧹 Clearing caches..."
docker compose -f "$COMPOSE_FILE" exec app php artisan config:cache
docker compose -f "$COMPOSE_FILE" exec app php artisan route:cache
docker compose -f "$COMPOSE_FILE" exec app php artisan view:cache

# ─── Restart queue workers to pick up new code ─────────────────────────────
echo "♻️  Restarting queue workers..."
docker compose -f "$COMPOSE_FILE" exec app php artisan queue:restart

# ─── Health check ──────────────────────────────────────────────────────────
echo "🏥 Running health check..."
sleep 5

HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/v1/companies/stats)
if [ "$HTTP_STATUS" -eq 200 ]; then
    echo "✅ API is healthy (HTTP $HTTP_STATUS)"
else
    echo "❌ API health check failed (HTTP $HTTP_STATUS)"
    docker compose -f "$COMPOSE_FILE" logs --tail=50 app
    exit 1
fi

echo ""
echo "═══════════════════════════════════════════════════"
echo "  ✅ Deployment complete!"
echo "  📊 Dashboard: https://your-domain.com"
echo "  🔌 API: https://api.your-domain.com"
echo "═══════════════════════════════════════════════════"
