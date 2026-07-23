(function () {
    const MAX_WAIT = 300000;
    const start = Date.now();
    let verified = false;

    const poll = setInterval(async () => {
        if (Date.now() - start > MAX_WAIT) {
            clearInterval(poll);
            return;
        }

        const checkbox = document.getElementById("recaptcha-anchor");
        if (!checkbox) return;

        const isChecked = checkbox.getAttribute("aria-checked") === "true";
        if (isChecked && !verified) {
            verified = true;
            clearInterval(poll);
            chrome.runtime.sendMessage({ type: "CAPTCHA_VERIFIED" });
            return;
        }

        if (!isChecked && !verified) {
            const isLoading = checkbox.classList.contains("recaptcha-checkbox-loading");
            if (isLoading) return;

            const delay = 300 + Math.random() * 500;
            setTimeout(() => {
                const rect = checkbox.getBoundingClientRect();
                const cx = rect.left + rect.width / 2 + (Math.random() * 4 - 2);
                const cy = rect.top + rect.height / 2 + (Math.random() * 4 - 2);
                ["mouseover", "mouseenter", "mousemove", "mousedown", "mouseup", "click"].forEach((evtName, i) => {
                    setTimeout(() => {
                        checkbox.dispatchEvent(new MouseEvent(evtName, {
                            bubbles: true, cancelable: true, view: window, clientX: cx, clientY: cy,
                        }));
                    }, i * 20);
                });
            }, delay);
        }
    }, 500);
})();