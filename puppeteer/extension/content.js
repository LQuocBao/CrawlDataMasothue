const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const API_BASE_URL = "http://mst-app:8000";
async function extractAndSendData() {
    const html = document.documentElement.outerHTML;

    function extractXData(key) {
        let pattern1 = new RegExp(`x-data="\\{\\s*${key}:\\s*'([^']+)'`);
        let m1 = html.match(pattern1);
        if (m1) return m1[1].trim();

        let pattern2 = new RegExp(`x-data='\\{\\s*${key}:\\s*"([^"]+)"`);
        let m2 = html.match(pattern2);
        if (m2) return m2[1].trim();

        let pattern3 = new RegExp(`x-data="\\{\\s*${key}:\\s*\`([^\`]+)\``);
        let m3 = html.match(pattern3);
        if (m3) return m3[1].trim();
        return "";
    }

    const mst = extractXData('mst') || (document.querySelector('div[x-data*="mst"] span') ? document.querySelector('div[x-data*="mst"] span').innerText.trim() : "");
    let name = extractXData('companyName');
    if (!name) {
        const h1 = document.querySelector('h1');
        if (h1) name = h1.innerText.trim();
    }
    const phone = extractXData('phone') || (document.querySelector('p[x-data*="phone"]') ? document.querySelector('p[x-data*="phone"]').innerText.trim() : "");
    let address = extractXData('taxAddress') || extractXData('address') || (document.querySelector('span[x-data*="taxAddress"]') ? document.querySelector('span[x-data*="taxAddress"]').innerText.trim() : "");

    const industries = [];
    const industryEls = document.querySelectorAll('ul.space-y-3 > li');

    for (let i = 0; i < industryEls.length; i++) {
        const codeEl = industryEls[i].querySelector('span.w-12');
        const descEl = industryEls[i].querySelector('span.name-special');
        if (codeEl && descEl) {
            industries.push({
                code: codeEl.innerText.trim(),
                description: descEl.innerText.trim()
            });
        }
    }

    const data = {
        name: name,
        mst: mst,
        phone: phone,
        address: address,
        industries: industries
    };

    try {
        await fetch(`${API_BASE_URL}/api/v1/companies`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Extension-Secret": EXTENSION_SECRET
            },
            body: JSON.stringify(data)
        });
    } catch (error) {
        console.log("Lỗi gửi dữ liệu về máy chủ:", error);
    }
}

async function init() {
    const updateBtn = document.querySelector('button[aria-label="Cập nhật"]');

    if (updateBtn) {
        updateBtn.click();

        chrome.runtime.onMessage.addListener(async (msg) => {
            if (msg.type === "CAPTCHA_VERIFIED") {
                await sleep(500);
                const agreeBtn = document.querySelector('button.submit.bg-primary');
                if (agreeBtn) {
                    agreeBtn.click();
                    await sleep(3000);
                    await extractAndSendData();
                }
            }
        });
    } else {
        await extractAndSendData();
    }
}

init();