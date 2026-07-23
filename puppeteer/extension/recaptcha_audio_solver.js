(function () {
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    let done = false;

    async function run() {
        if (done) return;
        done = true;
        await sleep(800);

        const audioBtn = document.getElementById("recaptcha-audio-button");
        if (audioBtn && audioBtn.style.display !== "none") {
            audioBtn.click();
            await sleep(1500);
        }

        let audio = document.getElementById("audio-source");
        const end = Date.now() + 5000;
        while (!audio?.src && Date.now() < end) {
            await sleep(200);
            audio = document.getElementById("audio-source");
        }

        if (audio?.src) {
            chrome.runtime.sendMessage({ type: "DOWNLOAD_AUDIO", url: audio.src });
        }
    }

    chrome.runtime.onMessage.addListener((msg) => {
        if (msg.type === "CAPTCHA_ANSWER" && msg.answer) {
            const input = document.getElementById("audio-response");
            if (input) {
                input.value = msg.answer;
                input.dispatchEvent(new Event("input", { bubbles: true }));
                const verifyBtn = document.getElementById("recaptcha-verify-button");
                if (verifyBtn) {
                    setTimeout(() => verifyBtn.click(), 500);
                }
            }
        }
    });

    const observer = new MutationObserver(() => {
        if (document.getElementById("rc-imageselect") || document.getElementById("audio-source")) {
            observer.disconnect();
            run();
        }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();