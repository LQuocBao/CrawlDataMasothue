'use client';

import { useEffect, useState } from 'react';
import {
  fetchSettings,
  updateSettings,
  testSheetConnection,
} from '@/lib/api';
import type { AppSetting } from '@/types';
import { Settings, FileSpreadsheet, CheckCircle, XCircle, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function SettingsPage() {
  const [settings, setSettings] = useState<AppSetting[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  // Form state
  const [sheetUrl, setSheetUrl] = useState('');
  const [sheetEnabled, setSheetEnabled] = useState(true);

  useEffect(() => {
    loadSettings();
  }, []);

  async function loadSettings() {
    try {
      const response = await fetchSettings();
      setSettings(response.data);

      // Populate form from settings
      const urlSetting = response.data.find((s) => s.key === 'google_sheet_webhook_url');
      const enabledSetting = response.data.find((s) => s.key === 'google_sheet_enabled');

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
    setMessage(null);
    try {
      await updateSettings({
        settings: {
          google_sheet_webhook_url: sheetUrl || null,
          google_sheet_enabled: sheetEnabled ? '1' : '0',
        },
      });
      setMessage('Lưu cài đặt thành công!');
      await loadSettings();
    } catch (err) {
      setMessage(err instanceof Error ? err.message : 'Lỗi khi lưu cài đặt');
    } finally {
      setSaving(false);
    }
  }

  async function handleTestSheet() {
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
        <div className="h-8 w-48 bg-gray-200 rounded" />
        <div className="h-64 bg-gray-200 rounded-xl" />
      </div>
    );
  }

  return (
    <div className="space-y-6 max-w-3xl">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <Settings className="h-6 w-6 text-primary-600" aria-hidden="true" />
          Cài đặt hệ thống
        </h2>
        <p className="text-sm text-gray-500 mt-1">
          Cấu hình Google Sheet và các tính năng hệ thống. Thay đổi ở đây sẽ áp dụng ngay lập tức.
        </p>
      </div>

      {/* Google Sheet Configuration */}
      <div className="card">
        <div className="flex items-center gap-2 mb-4">
          <FileSpreadsheet className="h-5 w-5 text-green-600" aria-hidden="true" />
          <h3 className="text-lg font-semibold text-gray-900">Google Sheet</h3>
        </div>

        <p className="text-sm text-gray-500 mb-4">
          URL webhook Apps Script để ghi dữ liệu doanh nghiệp vào Google Sheet.
          Khi bàn giao cho khách, chỉ cần đổi URL này sang Sheet mới.
        </p>

        <div className="space-y-4">
          {/* Webhook URL */}
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
              URL lấy từ Google Apps Script sau khi Deploy webapp
            </p>
          </div>

          {/* Enable/Disable */}
          <div className="flex items-center gap-3">
            <input
              id="sheet-enabled"
              type="checkbox"
              className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
              checked={sheetEnabled}
              onChange={(e) => setSheetEnabled(e.target.checked)}
            />
            <label htmlFor="sheet-enabled" className="text-sm text-gray-700">
              Bật ghi dữ liệu vào Google Sheet
            </label>
          </div>

          {/* Test Connection */}
          <div className="flex items-center gap-3">
            <button
              onClick={handleTestSheet}
              disabled={testing || !sheetUrl}
              className="btn-secondary text-sm"
            >
              {testing ? (
                <>
                  <Loader2 className="h-4 w-4 mr-1 animate-spin" aria-hidden="true" />
                  Đang test...
                </>
              ) : (
                'Test kết nối'
              )}
            </button>

            {testResult && (
              <div className={cn('flex items-center gap-1 text-sm', testResult.success ? 'text-green-600' : 'text-red-600')}>
                {testResult.success ? (
                  <CheckCircle className="h-4 w-4" aria-hidden="true" />
                ) : (
                  <XCircle className="h-4 w-4" aria-hidden="true" />
                )}
                <span>{testResult.message}</span>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Telegram Quick Reference */}
      <div className="card">
        <h3 className="text-lg font-semibold text-gray-900 mb-2">Telegram</h3>
        <p className="text-sm text-gray-500 mb-3">
          Cấu hình Bot Token và Chat ID ở trang{' '}
          <a href="/telegram" className="text-primary-600 hover:underline font-medium">
            Telegram
          </a>
          . Khi bàn giao cho khách, đổi Chat ID sang group mới của khách.
        </p>
        <div className="rounded-lg bg-yellow-50 border border-yellow-100 p-3">
          <p className="text-sm text-yellow-800">
            <strong>Lưu ý bàn giao:</strong> Chỉ cần thay đổi 2 thứ:
          </p>
          <ol className="text-sm text-yellow-700 list-decimal list-inside mt-1 space-y-0.5">
            <li>Google Sheet URL ở trên → Sheet mới của khách</li>
            <li>Telegram Chat ID ở trang Telegram → Group mới của khách</li>
          </ol>
        </div>
      </div>

      {/* Save Button */}
      <div className="flex items-center gap-4">
        <button
          onClick={handleSave}
          disabled={saving}
          className="btn-primary"
        >
          {saving ? (
            <>
              <Loader2 className="h-4 w-4 mr-1 animate-spin" aria-hidden="true" />
              Đang lưu...
            </>
          ) : (
            'Lưu cài đặt'
          )}
        </button>

        {message && (
          <span className="text-sm text-green-600 font-medium">{message}</span>
        )}
      </div>
    </div>
  );
}
