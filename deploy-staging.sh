#!/bin/bash
# Deploy script - Run this on the staging VPS (14.225.205.98)
# Login: ssh root@14.225.205.98 

set -e

echo "=== [1/6] Installing Docker ==="
apt-get update -qq
apt-get install -y ca-certificates curl gnupg lsb-release git
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg --yes
chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
apt-get update -qq
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
docker --version
docker compose version
echo "=== Docker installed ==="

echo "=== [2/6] Cloning repository ==="
mkdir -p /var/www
cd /var/www
if [ -d "masothue-scraper" ]; then
    cd masothue-scraper
    git fetch --all
    git checkout refactor/bus-chain
    git pull origin refactor/bus-chain
else
    git clone -b refactor/bus-chain https://github.com/LQuocBao/CrawlDataMasothue.git masothue-scraper
    cd masothue-scraper
fi
echo "=== Repo cloned ==="

echo "=== [3/6] Configuring .env ==="
cp backend/.env.example backend/.env 2>/dev/null || true

# Override .env for staging
cat > backend/.env << 'ENVEOF'
APP_NAME="MaSoThue Scraper"
APP_ENV=staging
APP_KEY=
APP_DEBUG=true
APP_URL=http://14.225.205.98:8000

LOG_CHANNEL=daily
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=masothue_scraper
DB_USERNAME=scraper
DB_PASSWORD=scraper_pass

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Proxy (TMProxy)
TMPROXY_API_KEY=
PROXY_ENABLED=false

# Telegram
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=

# Google Sheet
GOOGLE_SHEET_WEBHOOK_URL=https://script.google.com/macros/s/AKfycbyIH3Ad1O6NbItD7C_hav21vNkc9dhl63j8AHTVFTqzBAoExtpwaYxzFh1cvl2ypop0/exec

# Scraper config
SCRAPER_BASE_URL=https://masothue.com
SCRAPER_MAX_PER_RUN=50
SCRAPER_VERIFY_SSL=true
ENVEOF

echo "=== .env configured ==="

echo "=== [4/6] Building & starting Docker containers ==="
docker compose down 2>/dev/null || true
docker compose up -d --build
echo "=== Containers started ==="

echo "=== [5/6] Running migrations ==="
sleep 15  # Wait for MySQL to be healthy
docker compose exec -T app php artisan key:generate --force
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan config:cache
echo "=== Migrations done ==="

echo "=== [6/6] Verifying system ==="
docker compose ps
docker compose logs worker-scraping --tail=5
echo ""
echo "=============================="
echo "  STAGING DEPLOY COMPLETE!"
echo "  URL: http://14.225.205.98:8000"
echo "  Frontend: http://14.225.205.98:3000"
echo "=============================="
