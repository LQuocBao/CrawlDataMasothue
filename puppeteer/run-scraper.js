const puppeteer = require('puppeteer');
const path = require('path');
const http = require('http');

// ─── Config ──────────────────────────────────────────────────────────────────
const API_BASE_URL = process.env.API_BASE_URL || 'http://mst-app:8000';
const EXTENSION_SECRET = process.env.EXTENSION_SECRET || '';
const CHECK_INTERVAL = 30000; // 30 giây
const PROCESSED_TTL_MS = 2 * 60 * 60 * 1000; // 2 giờ

// ─── Helper: POST data về backend API bằng Node.js (không qua Chrome) ────────
function postCompanyToAPI(data) {
    return new Promise((resolve, reject) => {
        const url = new URL(`${API_BASE_URL}/api/v1/companies`);
        const postData = JSON.stringify(data);

        const options = {
            hostname: url.hostname,
            port: url.port || 8000,
            path: url.pathname,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Extension-Secret': EXTENSION_SECRET,
                'Content-Length': Buffer.byteLength(postData),
            },
        };

        const req = http.request(options, (res) => {
            let body = '';
            res.on('data', (chunk) => body += chunk);
            res.on('end', () => {
                try {
                    const json = JSON.parse(body);
                    resolve({ status: res.statusCode, data: json });
                } catch (e) {
                    resolve({ status: res.statusCode, data: body });
                }
            });
        });

        req.on('error', (e) => reject(e));
        req.setTimeout(10000, () => {
            req.destroy();
            reject(new Error('Request timeout'));
        });
        req.write(postData);
        req.end();
    });
}

// ─── Helper: Extract data từ trang chi tiết bằng page.evaluate() ─────────────
async function extractCompanyData(page) {
    return await page.evaluate(() => {
        const html = document.documentElement.outerHTML;

        function extractXData(key) {
            let m = html.match(new RegExp(`x-data="\\{\\s*${key}:\\s*'([^']+)'`));
            if (m) return m[1].trim();
            m = html.match(new RegExp(`x-data='\\{\\s*${key}:\\s*"([^"]+)"`));
            if (m) return m[1].trim();
            return "";
        }

        function queryText(sel) {
            const el = document.querySelector(sel);
            return el ? el.innerText.trim() : "";
        }

        const mst = extractXData('mst') || queryText('div[x-data*="mst"] span');
        let name = extractXData('companyName');
        if (!name) {
            const h1 = document.querySelector('h1');
            if (h1) name = h1.innerText.trim();
        }

        const phone = extractXData('phone') || queryText('[x-data*="phone"]');
        const address = extractXData('taxAddress') || extractXData('address') || queryText('[x-data*="taxAddress"]');
        const representative = extractXData('representative') || extractXData('owner') || "";

        // Ngành nghề
        const industries = [];
        document.querySelectorAll('ul.space-y-3 > li').forEach(li => {
            const code = li.querySelector('span.w-12');
            const desc = li.querySelector('span.name-special');
            if (code && desc) {
                industries.push({ code: code.innerText.trim(), description: desc.innerText.trim() });
            }
        });

        return { mst, name, phone, address, representative, industries };
    });
}

// ─── Main ────────────────────────────────────────────────────────────────────
async function startScraper() {
    const extensionPath = path.resolve(__dirname, 'extension');

    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: '/usr/bin/google-chrome',
        args: [
            `--disable-extensions-except=${extensionPath}`,
            `--load-extension=${extensionPath}`,
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-web-security',
            '--allow-running-insecure-content'
        ]
    });

    const processedUrls = new Map();

    async function checkNewCompanies() {
        let page;
        try {
            page = await browser.newPage();
            await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
            await page.goto('https://tramasothue.com.vn/', { waitUntil: 'domcontentloaded', timeout: 30000 });
            await new Promise(r => setTimeout(r, 3000));

            const newUrls = await page.evaluate(() => {
                const items = document.querySelectorAll('div.bg-white.p-5.rounded-xl');
                const urls = [];
                items.forEach(item => {
                    let hasBadge = false;
                    item.querySelectorAll('span').forEach(span => {
                        if (span.textContent.trim().includes('Mới đăng ký')) hasBadge = true;
                    });
                    if (!hasBadge) return;
                    const link = item.querySelector('h3 a[href]') || item.querySelector('a[href]');
                    if (link && link.href && link.href.includes('tramasothue.com.vn')) {
                        urls.push(link.href);
                    }
                });
                return urls;
            });

            await page.close();
            page = null;

            // Dọn TTL
            const now = Date.now();
            for (const [url, ts] of processedUrls.entries()) {
                if (now - ts > PROCESSED_TTL_MS) processedUrls.delete(url);
            }

            const newCount = newUrls.filter(u => !processedUrls.has(u)).length;
            console.log(`[*] Tìm thấy ${newUrls.length} DN trên trang chủ, ${newCount} chưa xử lý`);

            for (const url of newUrls) {
                if (processedUrls.has(url)) continue;

                processedUrls.set(url, Date.now());
                console.log(`[+] Đang xử lý: ${url}`);

                let detailPage;
                try {
                    detailPage = await browser.newPage();
                    await detailPage.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
                    await detailPage.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });

                    // Đợi Alpine.js render
                    await new Promise(r => setTimeout(r, 3000));

                    // Extract data bằng page.evaluate() — KHÔNG phụ thuộc extension
                    const data = await extractCompanyData(detailPage);

                    if (!data.mst) {
                        console.log(`[!] Không tìm thấy MST tại ${url}, bỏ qua`);
                        await detailPage.close();
                        continue;
                    }

                    console.log(`[>] MST: ${data.mst} | Tên: ${data.name} | SĐT: ${data.phone || 'N/A'}`);

                    // POST trực tiếp từ Node.js — đảm bảo đến được backend
                    const payload = {
                        mst: data.mst,
                        name: data.name || 'Chưa có tên',
                        phone: data.phone || '',
                        address: data.address || '',
                        representative: data.representative || '',
                        industries: data.industries || [],
                    };

                    try {
                        const result = await postCompanyToAPI(payload);
                        console.log(`[V] API ${result.status}: ${result.data?.message || JSON.stringify(result.data).substring(0, 100)}`);
                    } catch (apiErr) {
                        console.log(`[!] Lỗi gọi API: ${apiErr.message}`);
                    }

                    await detailPage.close();
                    detailPage = null;

                } catch (err) {
                    console.log(`[!] Lỗi xử lý ${url}: ${err.message}`);
                    if (detailPage) await detailPage.close().catch(() => {});
                }
            }

        } catch (error) {
            console.log("[!] Lỗi vòng lặp chính:", error.message);
            if (page) await page.close().catch(() => {});
        }

        setTimeout(checkNewCompanies, CHECK_INTERVAL);
    }

    console.log('🚀 Puppeteer scraper khởi chạy...');
    console.log(`   API: ${API_BASE_URL}`);
    console.log(`   Secret: ${EXTENSION_SECRET ? '***' + EXTENSION_SECRET.slice(-6) : 'CHƯA CẤU HÌNH!'}`);
    checkNewCompanies();
}

startScraper();