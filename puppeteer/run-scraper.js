const puppeteer = require('puppeteer');
const path = require('path');

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

    // Dùng Map: url -> timestamp lần đầu phát hiện
    // Chỉ skip nếu đã xử lý trong vòng 2 giờ (tránh re-process), reset sau đó
    const processedUrls = new Map();
    const PROCESSED_TTL_MS = 2 * 60 * 60 * 1000; // 2 giờ

    async function checkNewCompanies() {
        try {
            const page = await browser.newPage();

            // Set timeout và User-Agent như browser thật
            await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
            await page.setDefaultNavigationTimeout(30000);

            await page.goto('https://tramasothue.com.vn/', { waitUntil: 'domcontentloaded', timeout: 30000 });

            // Đợi thêm 2s để JS render xong
            await new Promise(r => setTimeout(r, 2000));

            const newUrls = await page.evaluate(() => {
                // Selector đúng với HTML thực tế của tramasothue.com.vn:
                // div.bg-white.p-5.rounded-xl chứa span có text "Mới đăng ký"
                const items = document.querySelectorAll('div.bg-white.p-5.rounded-xl');
                const urls = [];
                items.forEach(item => {
                    // Kiểm tra badge "Mới đăng ký"
                    const spans = item.querySelectorAll('span');
                    let hasBadge = false;
                    spans.forEach(span => {
                        if (span.textContent.trim().includes('Mới đăng ký')) {
                            hasBadge = true;
                        }
                    });
                    if (!hasBadge) return;

                    // Lấy URL từ thẻ <a> trong h3 trước, fallback toàn block
                    const h3Link = item.querySelector('h3 a[href]');
                    const anyLink = item.querySelector('a[href]');
                    const link = h3Link || anyLink;
                    if (link && link.href && link.href.includes('tramasothue.com.vn')) {
                        urls.push(link.href);
                    }
                });
                return urls;
            });

            await page.close();

            console.log(`[*] Tìm thấy ${newUrls.length} DN "Mới đăng ký" trên trang chủ tramasothue`);

            // Dọn TTL: xóa các entry đã quá 2 giờ để cho phép re-check
            const now = Date.now();
            for (const [url, ts] of processedUrls.entries()) {
                if (now - ts > PROCESSED_TTL_MS) {
                    processedUrls.delete(url);
                }
            }

            for (const url of newUrls) {
                if (processedUrls.has(url)) {
                    continue;
                }

                processedUrls.set(url, Date.now());
                console.log(`[+] Phát hiện doanh nghiệp mới: ${url}`);

                const detailPage = await browser.newPage();
                await detailPage.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');

                const postDataPromise = new Promise(resolve => {
                    detailPage.on('request', request => {
                        if (request.url().includes('/api/v1/companies') && request.method() === 'POST') {
                            resolve();
                        }
                    });
                });

                try {
                    await detailPage.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });

                    await Promise.race([
                        postDataPromise,
                        new Promise(r => setTimeout(r, 45000))
                    ]);
                } catch (err) {
                    console.log(`[!] Lỗi khi mở trang chi tiết: ${url} - ${err.message}`);
                }

                console.log(`[V] Đã lấy xong dữ liệu, đóng thẻ: ${url}`);
                await detailPage.close();
            }
        } catch (error) {
            console.log("[!] Lỗi trong vòng lặp:", error.message);
        }

        setTimeout(checkNewCompanies, 20000);
    }

    console.log('🚀 Hệ thống cào dữ liệu siêu tốc bằng Puppeteer đã khởi chạy...');
    checkNewCompanies();
}

startScraper();