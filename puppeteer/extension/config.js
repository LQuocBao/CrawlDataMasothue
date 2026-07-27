// ============================================================
// CẤU HÌNH EXTENSION - ĐIỀN VÀO TRƯỚC KHI SỬ DỤNG
// ============================================================

// API Key của Groq (lấy tại console.groq.com) - dùng cho CAPTCHA audio solver
const GROQ_API_KEY = "YOUR_GROQ_API_KEY_HERE";

// Khoá xác thực tĩnh - phải khớp với EXTENSION_SECRET trong .env của Laravel
const EXTENSION_SECRET = "fR3ahTiZb6L2jeIrnocyQ7EDklpwM8YVKFNA4uW0UJqt9OzH";

// URL của API backend (trong Docker network, mst-app là hostname của container Laravel)
const API_BASE_URL = "http://mst-app:8000";