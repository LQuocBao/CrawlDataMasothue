'use client';

import { Settings, Send, FileSpreadsheet } from 'lucide-react';

export default function SettingsPage() {
  return (
    <div className="space-y-6 max-w-3xl">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <Settings className="h-6 w-6 text-primary-600" aria-hidden="true" />
          Cài đặt hệ thống
        </h2>
        <p className="text-sm text-gray-500 mt-1">
          Trung tâm điều hướng các cấu hình chính của hệ thống.
        </p>
      </div>

      {/* Quick Links */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a
          href="/sheets"
          className="card flex items-start gap-4 hover:border-green-300 hover:shadow-md transition-all group"
        >
          <div className="rounded-lg bg-green-50 p-3 group-hover:bg-green-100 transition-colors">
            <FileSpreadsheet className="h-6 w-6 text-green-600" aria-hidden="true" />
          </div>
          <div>
            <h3 className="font-semibold text-gray-900 group-hover:text-green-700">Google Sheets</h3>
            <p className="text-sm text-gray-500 mt-0.5">
              Cấu hình Webhook URL để ghi dữ liệu DN vào Sheet
            </p>
          </div>
        </a>

        <a
          href="/telegram"
          className="card flex items-start gap-4 hover:border-blue-300 hover:shadow-md transition-all group"
        >
          <div className="rounded-lg bg-blue-50 p-3 group-hover:bg-blue-100 transition-colors">
            <Send className="h-6 w-6 text-blue-600" aria-hidden="true" />
          </div>
          <div>
            <h3 className="font-semibold text-gray-900 group-hover:text-blue-700">Telegram</h3>
            <p className="text-sm text-gray-500 mt-0.5">
              Quản lý Bot Token và Chat ID nhóm nhận thông báo
            </p>
          </div>
        </a>
      </div>

      {/* Handover Guide */}
      <div className="card border-l-4 border-l-amber-400 bg-amber-50">
        <h4 className="text-sm font-semibold text-amber-800 mb-3">📋 Hướng dẫn bàn giao cho khách hàng</h4>
        <p className="text-sm text-amber-700 mb-3">
          Khi bàn giao cho khách hàng mới, bạn chỉ cần thay đổi <strong>2 thứ</strong> — toàn bộ hệ thống sẽ
          tự động chạy cho khách ngay lập tức:
        </p>
        <div className="space-y-3">
          <div className="flex items-start gap-3">
            <span className="inline-flex items-center justify-center w-6 h-6 bg-amber-200 text-amber-800 rounded-full text-xs font-bold shrink-0">1</span>
            <div>
              <p className="text-sm font-medium text-amber-800">Google Sheet URL</p>
              <p className="text-xs text-amber-700 mt-0.5">
                Vào trang <a href="/sheets" className="font-semibold underline">Google Sheets</a> → đổi Webhook URL sang Apps Script mới của khách → Lưu + Test kết nối
              </p>
            </div>
          </div>
          <div className="flex items-start gap-3">
            <span className="inline-flex items-center justify-center w-6 h-6 bg-amber-200 text-amber-800 rounded-full text-xs font-bold shrink-0">2</span>
            <div>
              <p className="text-sm font-medium text-amber-800">Telegram Chat ID</p>
              <p className="text-xs text-amber-700 mt-0.5">
                Vào trang <a href="/telegram" className="font-semibold underline">Telegram</a> → xóa config cũ → thêm config mới với Chat ID của group khách hàng
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* System Info */}
      <div className="card">
        <h4 className="text-base font-semibold text-gray-900 mb-3">Thông tin hệ thống</h4>
        <div className="grid grid-cols-2 gap-3 text-sm">
          <div className="bg-gray-50 rounded-lg p-3">
            <p className="text-gray-500 text-xs mb-1">Nguồn dữ liệu 1</p>
            <p className="font-medium text-gray-800">masothue.com</p>
            <p className="text-xs text-gray-500">Cào tự động qua Puppeteer</p>
          </div>
          <div className="bg-gray-50 rounded-lg p-3">
            <p className="text-gray-500 text-xs mb-1">Nguồn dữ liệu 2</p>
            <p className="font-medium text-gray-800">tramasothue.com.vn</p>
            <p className="text-xs text-gray-500">Cào tự động qua Puppeteer</p>
          </div>
          <div className="bg-gray-50 rounded-lg p-3">
            <p className="text-gray-500 text-xs mb-1">Lịch cào</p>
            <p className="font-medium text-gray-800">Mỗi 30 giây</p>
            <p className="text-xs text-gray-500">Tự động 24/7</p>
          </div>
          <div className="bg-gray-50 rounded-lg p-3">
            <p className="text-gray-500 text-xs mb-1">Điều kiện gửi thông báo</p>
            <p className="font-medium text-gray-800">Phải có Số điện thoại</p>
            <p className="text-xs text-gray-500">Lọc DN chất lượng cao</p>
          </div>
        </div>
      </div>
    </div>
  );
}
