# Deployment Guide

## Prerequisites

- Docker & Docker Compose v2+
- Git
- (Production) VPS with 2+ GB RAM, Ubuntu 22.04+ recommended

---

## Development Setup

### Option 1: Docker (Recommended)

```bash
# Clone the repo
git clone <repository-url> masothue-scraper
cd masothue-scraper

# Run setup script
chmod +x scripts/setup-dev.sh
./scripts/setup-dev.sh
```

On Windows:
```cmd
scripts\setup-dev.bat
```

### Option 2: Manual

1. **Backend:**
```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

2. **Frontend:**
```bash
cd frontend
cp .env.local.example .env.local
npm install
npm run dev
```

3. **Queue worker:**
```bash
cd backend
php artisan queue:work database --queue=notifications,default
```

4. **Scheduler (manual trigger):**
```bash
php artisan scrape:companies --dry-run
```

### Services (Development)

| Service   | URL                   |
|-----------|-----------------------|
| Frontend  | http://localhost:3000  |
| API       | http://localhost:8000  |
| MySQL     | localhost:3306        |
| Redis     | localhost:6379        |

---

## Production Deployment

### Initial Server Setup

1. **Install Docker on VPS:**
```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
```

2. **Clone and configure:**
```bash
cd /var/www
git clone <repository-url> masothue-scraper
cd masothue-scraper

# Create production env files
cp .env.docker .env
cp backend/.env.production.example backend/.env
# Edit both .env files with production credentials
```

3. **Deploy:**
```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh
```

### SSL / HTTPS

Use Certbot with Nginx:
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com -d api.your-domain.com
```

### Monitoring

```bash
# Check all services
docker compose -f docker-compose.prod.yml ps

# View queue worker logs
docker compose -f docker-compose.prod.yml logs -f worker

# View scraper logs
docker compose -f docker-compose.prod.yml exec app cat storage/logs/scraper.log

# Restart workers after code change
docker compose -f docker-compose.prod.yml exec app php artisan queue:restart
```

### Scaling Queue Workers

In `supervisor/worker.conf`, increase `numprocs`:
```ini
[program:queue-notifications]
numprocs=5  ; Increase from 3 to 5 for higher throughput
```

---

## Proxy Configuration

### Supported Proxy Services

The system supports any HTTP proxy with the format:
```
http://username:password@proxy-host:port
```

Popular options:
- **SmartProxy** - Rotating residential proxies
- **BrightData** - Large proxy pool
- **Oxylabs** - Datacenter + residential
- **ScraperAPI** - Built-in anti-bot bypass

Configure in `backend/.env`:
```env
SCRAPER_PROXY_ENDPOINT=http://user:pass@gate.smartproxy.com:7000
```

---

## Latency Budget (< 4 minutes)

| Step                  | Target    | Notes                           |
|-----------------------|-----------|---------------------------------|
| Scheduler trigger     | 0-120s    | Runs every 2 minutes            |
| Scrape + Parse        | 5-15s     | Per company, with proxy         |
| Filter evaluation     | <1ms      | In-memory comparison            |
| Queue dispatch        | <100ms    | Redis LPUSH                     |
| PDF generation        | 2-5s      | DomPDF rendering                |
| Telegram API call     | 1-3s      | sendDocument upload              |
| **Total worst case**  | ~143s     | Well within 4-minute budget     |

---

## Troubleshooting

### Scraper getting blocked
- Verify proxy is working: Check `storage/logs/scraper.log`
- Increase delay: `SCRAPER_DELAY_MIN=500` and `SCRAPER_DELAY_MAX=1000`
- Rotate User-Agent strings more frequently

### Queue jobs failing
```bash
# Check failed jobs
docker compose exec app php artisan queue:failed

# Retry all failed
docker compose exec app php artisan queue:retry all
```

### PDF generation issues
- Ensure `storage/app/pdfs` directory exists and is writable
- Check DomPDF font cache: `storage/fonts/`
