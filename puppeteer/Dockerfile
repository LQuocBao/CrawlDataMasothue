FROM node:20-slim

# Cài đặt các thư viện lõi của Linux để chạy Chrome/Puppeteer
RUN apt-get update \
    && apt-get install -y wget gnupg \
    && wget -q -O - https://dl-ssl.google.com/linux/linux_signing_key.pub | apt-key add - \
    && sh -c 'echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google.list' \
    && apt-get update \
    && apt-get install -y google-chrome-stable fonts-ipafont-gothic fonts-wqy-zenhei fonts-thai-tlwg fonts-kacst fonts-freefont-ttf libxss1 \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy file cấu hình và cài đặt package
COPY package*.json ./
RUN npm install

# Copy source code và extension
COPY . .

# Đảm bảo script inject config có quyền chạy + strip Windows CRLF
RUN sed -i 's/\r$//' /app/inject-config.sh && chmod +x /app/inject-config.sh

# Khởi chạy qua inject-config.sh (inject env vars → start scraper)
CMD ["/bin/sh", "/app/inject-config.sh"]