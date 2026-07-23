const puppeteer = require('puppeteer');
const path = require('path');

async function startScraper() {
    const extensionPath = path.resolve(__dirname, 'extension');

    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: '/usr/bin/google-chrome', // Dùng Chrome đã cài trong Dockerfile
        args: [
            `--disable-extensions-except=${extensionPath}`,
            `--load-extension=${extensionPath}`,
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage', // Chống sập trang khi chạy lâu
            '--disable-web-security', // Tắt CORS
            '--allow-running-insecure-content' // Cho phép gửi request HTTP (mst-app) từ trang HTTPS (tramasothue)
        ]
    });

    const processedUrls = new Set();

    async function checkNewCompanies() {
        try {
            const page = await browser.newPage();
            await page.goto('https://tramasothue.com.vn/', { waitUntil: 'networkidle2' });

            const newUrls = await page.evaluate(() => {
                const items = document.querySelectorAll('.bg-white.p-5.rounded-xl');
                const urls = [];
                items.forEach(item => {
                    if (item.innerText.includes('Mới đăng ký')) {
                        const aTag = item.querySelector('a');
                        if (aTag) urls.push(aTag.href);
                    }
                });
                return urls;
            });

            await page.close();

            for (const url of newUrls) {
                if (!processedUrls.has(url)) {
                    processedUrls.add(url);
                    console.log(`[+] Phát hiện doanh nghiệp mới: ${url}`);

                    const detailPage = await browser.newPage();

                    const postDataPromise = new Promise(resolve => {
                        detailPage.on('request', request => {
                            if (request.url().includes('/api/v1/companies') && request.method() === 'POST') {
                                resolve();
                            }
                        });
                    });

                    await detailPage.goto(url, { waitUntil: 'networkidle2' });

                    await Promise.race([
                        postDataPromise,
                        new Promise(r => setTimeout(r, 45000))
                    ]);

                    console.log(`[V] Đã lấy xong dữ liệu, đóng thẻ: ${url}`);
                    await detailPage.close();
                }
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