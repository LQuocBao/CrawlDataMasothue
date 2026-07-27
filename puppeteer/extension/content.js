const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/**
 * Chiến lược extract-first:
 * 1. LUÔN extract dữ liệu cơ bản ngay (MST, name có sẵn trong x-data)
 * 2. Gửi về API ngay lập tức (dù chưa có phone/address)
 * 3. Nếu CAPTCHA thành công → extract lần 2 → gửi update (có phone/address)
 */
async function extractAndSendData() {
    const html = document.documentElement.outerHTML;

    function extractXData(key) {
        // Pattern 1: x-data="{ key: 'value' }"
        let m = html.match(new RegExp(`x-data="\\{\\s*${key}:\\s*'([^']+)'`));
        if (m) return m[1].trim();

        // Pattern 2: x-data='{ key: "value" }'
        m = html.match(new RegExp(`x-data='\\{\\s*${key}:\\s*"([^"]+)"`));
        if (m) return m[1].trim();

        // Pattern 3: x-data="{ key: `value` }"
        m = html.match(new RegExp(`x-data="\\{\\s*${key}:\\s*\`([^\`]+)\``));
        if (m) return m[1].trim();

        return "";
    }

    // Fallback DOM selectors
    function queryText(selector) {
        const el = document.querySelector(selector);
        return el ? el.innerText.trim() : "";
    }

    const mst = extractXData('mst')
        || queryText('div[x-data*="mst"] span[x-text="mst"]')
        || queryText('div[x-data*="mst"] span');

    let name = extractXData('companyName');
    if (!name) {
        const h1 = document.querySelector('h1');
        if (h1) name = h1.innerText.trim();
    }

    const phone = extractXData('phone')
        || queryText('p[x-data*="phone"] span[x-text="phone"]')
        || queryText('[x-data*="phone"]');

    let address = extractXData('taxAddress')
        || extractXData('address')
        || queryText('span[x-data*="taxAddress"]')
        || queryText('[x-data*="address"]');

    const representative = extractXData('representative')
        || extractXData('owner')
        || queryText('[x-data*="representative"]');

    const activeDate = extractXData('activeDate')
        || extractXData('startDate')
        || "";

    // Ngành nghề
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

    // Phải có ít nhất MST để gửi
    if (!mst) {
        console.log("[content.js] Không tìm thấy MST, bỏ qua trang này");
        return;
    }

    const data = {
        name: name || "Chưa có tên",
        mst: mst,
        phone: phone || "",
        address: address || "",
        representative: representative || "",
        operation_date: activeDate || "",
        industries: industries
    };

    console.log("[content.js] Gửi dữ liệu DN:", JSON.stringify({mst: data.mst, name: data.name, phone: data.phone}));

    try {
        const resp = await fetch(`${API_BASE_URL}/api/v1/companies`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Extension-Secret": EXTENSION_SECRET
            },
            body: JSON.stringify(data)
        });
        const result = await resp.json();
        console.log("[content.js] API response:", resp.status, result.message || "");
    } catch (error) {
        console.log("[content.js] Lỗi gửi dữ liệu:", error.message);
    }
}

async function init() {
    // Bỏ qua trang chủ (homepage) - chỉ extract trên trang chi tiết DN
    const path = window.location.pathname;
    if (path === "/" || path === "") {
        console.log("[content.js] Trang chủ, bỏ qua extraction");
        return;
    }

    // Đợi 2s cho Alpine.js render xong x-data
    await sleep(2000);

    // ===== BƯỚC 1: Luôn extract và gửi dữ liệu cơ bản trước =====
    await extractAndSendData();

    // ===== BƯỚC 2: Thử CAPTCHA để lấy thêm data (bonus, không bắt buộc) =====
    const updateBtn = document.querySelector('button[aria-label="Cập nhật"]');

    if (updateBtn) {
        console.log("[content.js] Tìm thấy nút Cập nhật, bắt đầu CAPTCHA flow...");
        updateBtn.click();

        chrome.runtime.onMessage.addListener(async (msg) => {
            if (msg.type === "CAPTCHA_VERIFIED") {
                console.log("[content.js] CAPTCHA verified! Đang click Đồng ý...");
                await sleep(500);

                // Tìm nút "Đồng ý" trong modal
                const agreeBtn = document.querySelector('button.submit.bg-primary')
                    || document.querySelector('button.submit');
                if (agreeBtn) {
                    agreeBtn.click();
                    await sleep(3000);

                    // Extract lại sau khi data đã được cập nhật
                    console.log("[content.js] Extract lần 2 sau CAPTCHA...");
                    await extractAndSendData();
                }
            }
        });
    }
}

init();