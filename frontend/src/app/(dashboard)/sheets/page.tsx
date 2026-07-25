'use client';

import { useEffect, useState } from 'react';
import { fetchSettings, updateSettings, testSheetConnection } from '@/lib/api';
import type { AppSetting } from '@/types';
import { FileSpreadsheet, CheckCircle, XCircle, Loader2, ExternalLink, Save } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function SheetsPage() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);
  const [saveMessage, setSaveMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const [sheetUrl, setSheetUrl] = useState('');
  const [sheetEnabled, setSheetEnabled] = useState(true);

  useEffect(() => {
    loadSettings();
  }, []);

  async function loadSettings() {
    try {
      const response = await fetchSettings();
      const urlSetting = response.data.find((s: AppSetting) => s.key === 'google_sheet_webhook_url');
      const enabledSetting = response.data.find((s: AppSetting) => s.key === 'google_sheet_enabled');
      if (urlSetting) setSheetUrl(urlSetting.value || '');
      if (enabledSetting) setSheetEnabled(enabledSetting.value === '1');
    } catch (err) {
      console.error('Failed to load settings:', err);
    } finally {
      setLoading(false);
    }
  }

  async function handleSave() {
    setSaving(true);
    setSaveMessage(null);
    try {
      await updateSettings({
        settings: {
          google_sheet_webhook_url: sheetUrl || null,
          google_sheet_enabled: sheetEnabled ? '1' : '0',
        },
      });
      setSaveMessage({ type: 'success', text: '✓ Đã lưu cấu hình Google Sheet thành công!' });
      await loadSettings();
    } catch (err) {
      setSaveMessage({ type: 'error', text: err instanceof Error ? err.message : 'Lỗi khi lưu cấu hình' });
    } finally {
      setSaving(false);
    }
  }

  async function handleTest() {
    setTesting(true);
    setTestResult(null);
    try {
      const result = await testSheetConnection(sheetUrl || undefined);
      setTestResult(result);
    } catch (err) {
      setTestResult({
        success: false,
        message: err instanceof Error ? err.message : 'Kết nối thất bại',
      });
    } finally {
      setTesting(false);
    }
  }

  if (loading) {
    return (
      <div className="animate-pulse space-y-4">
        <div className="h-8 w-64 bg-gray-200 rounded" />
        <div className="h-64 bg-gray-100 rounded-xl" />
      </div>
    );
  }

  return (
    <div className="space-y-6 max-w-3xl">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <FileSpreadsheet className="h-6 w-6 text-green-600" aria-hidden="true" />
          Google Sheets
        </h2>
        <p className="text-sm text-gray-500 mt-1">
          Cấu hình URL Webhook để tự động ghi dữ liệu doanh nghiệp mới vào Google Sheet.
        </p>
      </div>

      {/* Main Config Card */}
      <div className="card space-y-5">
        <div>
          <h3 className="text-base font-semibold text-gray-900 mb-1">Webhook URL (Apps Script)</h3>
          <p className="text-sm text-gray-500">
            Mỗi lần hệ thống cào được doanh nghiệp mới, nó sẽ tự động POST dữ liệu vào URL này.{' '}
            <a
              href="https://script.google.com"
              target="_blank"
              rel="noopener noreferrer"
              className="text-primary-600 hover:underline inline-flex items-center gap-1"
            >
              Mở Google Apps Script <ExternalLink className="h-3 w-3" />
            </a>
          </p>
        </div>

        {/* URL Input */}
        <div>
          <label htmlFor="sheet-url" className="label">
            Webhook URL *
          </label>
          <input
            id="sheet-url"
            type="url"
            className="input font-mono text-xs"
            placeholder="https://script.google.com/macros/s/.../exec"
            value={sheetUrl}
            onChange={(e) => setSheetUrl(e.target.value)}
          />
          <p className="text-xs text-gray-400 mt-1">
            Lấy URL này từ Google Apps Script → Triển khai → Quản lý các lần triển khai → Ứng dụng web → URL
          </p>
        </div>

        {/* Enable toggle */}
        <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
          <input
            id="sheet-enabled"
            type="checkbox"
            className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            checked={sheetEnabled}
            onChange={(e) => setSheetEnabled(e.target.checked)}
          />
          <label htmlFor="sheet-enabled" className="text-sm font-medium text-gray-700">
            Bật tự động ghi dữ liệu vào Google Sheet
          </label>
        </div>

        {/* Actions */}
        <div className="flex flex-wrap items-center gap-3 pt-1">
          <button
            onClick={handleSave}
            disabled={saving}
            className="btn-primary flex items-center gap-2"
          >
            {saving ? (
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            ) : (
              <Save className="h-4 w-4" aria-hidden="true" />
            )}
            {saving ? 'Đang lưu...' : 'Lưu cấu hình'}
          </button>

          <button
            onClick={handleTest}
            disabled={testing || !sheetUrl}
            className="btn-secondary flex items-center gap-2"
          >
            {testing ? (
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            ) : null}
            {testing ? 'Đang test...' : 'Test kết nối'}
          </button>

          {testResult && (
            <div className={cn('flex items-center gap-1.5 text-sm font-medium', testResult.success ? 'text-green-600' : 'text-red-600')}>
              {testResult.success ? (
                <CheckCircle className="h-4 w-4" aria-hidden="true" />
              ) : (
                <XCircle className="h-4 w-4" aria-hidden="true" />
              )}
              <span>{testResult.message}</span>
            </div>
          )}
        </div>

        {saveMessage && (
          <p className={cn('text-sm font-medium', saveMessage.type === 'success' ? 'text-green-600' : 'text-red-600')}>
            {saveMessage.text}
          </p>
        )}
      </div>

      {/* Handover Note */}
      <div className="card border-l-4 border-l-amber-400 bg-amber-50">
        <h4 className="text-sm font-semibold text-amber-800 mb-2">📋 Hướng dẫn bàn giao cho khách hàng</h4>
        <ol className="text-sm text-amber-700 list-decimal list-inside space-y-1">
          <li>Vào Google Apps Script của khách → tạo một Deployment mới → lấy URL mới</li>
          <li>Dán URL mới vào ô Webhook URL ở trên và bấm <strong>Lưu cấu hình</strong></li>
          <li>Bấm <strong>Test kết nối</strong> để xác nhận hệ thống ghi được vào Sheet của khách</li>
          <li>Đổi Chat ID Telegram tại trang <a href="/telegram" className="font-semibold underline">Telegram</a></li>
        </ol>
      </div>

      {/* How it works */}
      <div className="card">
        <h4 className="text-base font-semibold text-gray-900 mb-3">Cách hoạt động</h4>
        <div className="space-y-2 text-sm text-gray-600">
          <div className="flex items-start gap-2">
            <span className="inline-flex items-center justify-center w-5 h-5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold shrink-0 mt-0.5">1</span>
            <p>Hệ thống cào doanh nghiệp mới từ <strong>masothue.com</strong> và <strong>tramasothue.com.vn</strong></p>
          </div>
          <div className="flex items-start gap-2">
            <span className="inline-flex items-center justify-center w-5 h-5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold shrink-0 mt-0.5">2</span>
            <p>Lưu vào Database, sau đó POST dữ liệu (MST, Tên, SĐT, Địa chỉ, Ngành nghề...) đến Webhook URL này</p>
          </div>
          <div className="flex items-start gap-2">
            <span className="inline-flex items-center justify-center w-5 h-5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold shrink-0 mt-0.5">3</span>
            <p>Google Apps Script nhận dữ liệu và ghi vào Sheet. Mỗi ngày tạo 1 tab mới tự động</p>
          </div>
          <div className="flex items-start gap-2">
            <span className="inline-flex items-center justify-center w-5 h-5 bg-blue-100 text-blue-700 rounded-full text-xs font-bold shrink-0 mt-0.5">4</span>
            <p>Chỉ ghi DN <strong>có số điện thoại</strong> vào Sheet — đảm bảo chất lượng danh sách</p>
          </div>
        </div>
      </div>
    </div>
  );
}
