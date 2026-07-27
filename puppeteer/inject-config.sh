#!/bin/sh
# Inject environment variables vào config.js của extension
# Chạy trước khi start Puppeteer scraper

CONFIG_FILE="/app/extension/config.js"

# Thay thế placeholders bằng giá trị thật từ env
sed -i "s|__GROQ_API_KEY__|${GROQ_API_KEY:-}|g" "$CONFIG_FILE"
sed -i "s|__EXTENSION_SECRET__|${EXTENSION_SECRET:-}|g" "$CONFIG_FILE"
sed -i "s|__API_BASE_URL__|${API_BASE_URL:-http://mst-app:8000}|g" "$CONFIG_FILE"

echo "[inject-config] Đã inject config từ env vào extension"

# Chạy scraper
exec node /app/run-scraper.js
