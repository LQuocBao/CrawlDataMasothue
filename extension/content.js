const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function extractAndSendData() {
    const nameEl = document.querySelector('h1 span[x-text="companyName"]');
    const mstEl = document.querySelector('div[x-data*="mst"] span');
    const phoneEl = document.querySelector('p[x-data*="phone"]');
    const addressEl = document.querySelector('span[x-data*="taxAddress"]');

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
        name: nameEl ? nameEl.innerText.trim() : "",
        mst: mstEl ? mstEl.innerText.trim() : "",
        phone: phoneEl ? phoneEl.innerText.trim() : "",
        address: addressEl ? addressEl.innerText.trim() : "",
        industries: industries
    };

    try {
        await fetch(`${API_BASE_URL}/api/companies`, {
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