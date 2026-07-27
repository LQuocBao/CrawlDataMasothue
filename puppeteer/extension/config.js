// ============================================================
// CẤU HÌNH EXTENSION - Các giá trị mặc định
// Giá trị thật được inject từ Docker environment variables
// thông qua script inject-config.sh khi container khởi động
// ============================================================

// API Key của Groq (lấy tại console.groq.com) - dùng cho CAPTCHA audio solver
const GROQ_API_KEY = "__GROQ_API_KEY__";

// Khoá xác thực tĩnh - phải khớp với EXTENSION_SECRET trong .env của Laravel
const EXTENSION_SECRET = "__EXTENSION_SECRET__";

// URL của API backend (trong Docker network, mst-app là hostname của container Laravel)
const API_BASE_URL = "__API_BASE_URL__";