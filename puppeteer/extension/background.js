importScripts("config.js");

chrome.runtime.onMessage.addListener((msg, sender) => {
    if (msg.type === "DOWNLOAD_AUDIO" && msg.url) {
        handleAudio(msg.url, sender.tab?.id);
    }
    if (msg.type === "CAPTCHA_VERIFIED" && sender.tab?.id) {
        chrome.tabs.sendMessage(sender.tab.id, { type: "CAPTCHA_VERIFIED" });
    }
});

async function handleAudio(audioUrl, tabId) {
    try {
        const audioResp = await fetch(audioUrl);
        const audioBlob = await audioResp.blob();

        const formData = new FormData();
        formData.append("file", audioBlob, "captcha.mp3");
        formData.append("model", "whisper-large-v3-turbo");
        formData.append("response_format", "json");
        formData.append("language", "en");

        const groqResp = await fetch("https://api.groq.com/openai/v1/audio/transcriptions", {
            method: "POST",
            headers: { "Authorization": `Bearer ${GROQ_API_KEY}` },
            body: formData,
        });

        const data = await groqResp.json();
        const answer = data.text?.trim().toLowerCase().replace(/[^a-z0-9 ]/g, "").trim();

        if (answer && tabId) {
            chrome.tabs.sendMessage(tabId, { type: "CAPTCHA_ANSWER", answer });
        }
    } catch (e) {
        console.log("Lỗi xử lý âm thanh:", e.message);
    }
}