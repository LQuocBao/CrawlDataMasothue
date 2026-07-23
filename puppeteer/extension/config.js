// ============================================================
// CẤU HÌNH EXTENSION - ĐIỀN VÀO TRƯỚC KHI SỬ DỤNG
// KHÔNG commit file này lên git khi đã điền key thật
// ============================================================

// API Key của Groq (lấy tại console.groq.com)
const GROQ_API_KEY = "YOUR_GROQ_API_KEY_HERE";

// Khoá xác thực tĩnh - phải khớp với EXTENSION_SECRET trong .env của Laravel
// Tạo ngẫu nhiên tại: https://randomkeygen.com/ (dùng 256-bit WEP)
const EXTENSION_SECRET = "YOUR_EXTENSION_SECRET_HERE";

// URL của API backend (đổi sang địa chỉ server thật khi deploy)
const API_BASE_URL = "http://localhost:8000";