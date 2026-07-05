'use client';

import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  fetchTelegramConfigs,
  createTelegramConfig,
  deleteTelegramConfig,
  testTelegramConfig,
} from '@/lib/api';
import type { TelegramConfig } from '@/types';
import { Plus, Trash2, Zap, Send } from 'lucide-react';
import { cn } from '@/lib/utils';
import { formatDateTime } from '@/lib/utils';

const telegramSchema = z.object({
  name: z.string().min(1, 'Tên cấu hình không được để trống'),
  bot_token: z.string().min(10, 'Bot token không hợp lệ'),
  chat_id: z.string().min(1, 'Chat ID không được để trống'),
  is_active: z.boolean().default(true),
});

type TelegramFormValues = z.infer<typeof telegramSchema>;

export function TelegramManager() {
  const [configs, setConfigs] = useState<TelegramConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [testingId, setTestingId] = useState<number | null>(null);

  const form = useForm<TelegramFormValues>({
    resolver: zodResolver(telegramSchema),
    defaultValues: {
      name: '',
      bot_token: '',
      chat_id: '',
      is_active: true,
    },
  });

  useEffect(() => {
    loadConfigs();
  }, []);

  async function loadConfigs() {
    try {
      const response = await fetchTelegramConfigs();
      setConfigs(response.data);
    } catch (err) {
      console.error('Failed to load configs:', err);
    } finally {
      setLoading(false);
    }
  }

  async function onSubmit(values: TelegramFormValues) {
    setSubmitting(true);
    try {
      await createTelegramConfig(values);
      await loadConfigs();
      setShowForm(false);
      form.reset();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Lỗi khi tạo cấu hình');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm('Bạn có chắc chắn muốn xóa cấu hình Telegram này?')) return;
    try {
      await deleteTelegramConfig(id);
      await loadConfigs();
    } catch (err) {
      console.error('Failed to delete:', err);
    }
  }

  async function handleTest(id: number) {
    setTestingId(id);
    try {
      const result = await testTelegramConfig(id);
      alert(result.success ? 'Kết nối thành công!' : `Lỗi: ${result.message}`);
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Kết nối thất bại');
    } finally {
      setTestingId(null);
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
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-gray-900">Cấu hình Telegram</h2>
          <p className="text-sm text-gray-500 mt-1">
            Quản lý Bot Token và Chat ID để nhận thông báo
          </p>
        </div>
        <button onClick={() => setShowForm(true)} className="btn-primary">
          <Plus className="h-4 w-4 mr-2" aria-hidden="true" />
          Thêm cấu hình
        </button>
      </div>

      {/* Instructions */}
      <div className="rounded-lg bg-blue-50 border border-blue-100 p-4">
        <h4 className="text-sm font-medium text-blue-800 mb-2">Hướng dẫn nhanh:</h4>
        <ol className="text-sm text-blue-700 space-y-1 list-decimal list-inside">
          <li>Tạo Bot mới qua @BotFather trên Telegram</li>
          <li>Sao chép Bot Token được cung cấp</li>
          <li>Thêm Bot vào group/channel cần nhận thông báo</li>
          <li>Lấy Chat ID (có thể dùng @userinfobot hoặc API getUpdates)</li>
        </ol>
      </div>

      {/* Create Form */}
      {showForm && (
        <div className="card" role="form" aria-label="Thêm cấu hình Telegram">
          <h3 className="text-lg font-semibold mb-4">Thêm cấu hình Telegram</h3>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <div>
              <label htmlFor="tg-name" className="label">Tên cấu hình *</label>
              <input
                id="tg-name"
                className="input"
                placeholder="VD: Group Sales Team"
                {...form.register('name')}
              />
              {form.formState.errors.name && (
                <p className="text-xs text-red-500 mt-1">{form.formState.errors.name.message}</p>
              )}
            </div>

            <div>
              <label htmlFor="tg-token" className="label">Bot Token *</label>
              <input
                id="tg-token"
                className="input font-mono text-xs"
                placeholder="1234567890:ABCdefGHIjklMNOpqrSTUvwxYZ"
                type="password"
                autoComplete="off"
                {...form.register('bot_token')}
              />
              {form.formState.errors.bot_token && (
                <p className="text-xs text-red-500 mt-1">{form.formState.errors.bot_token.message}</p>
              )}
            </div>

            <div>
              <label htmlFor="tg-chatid" className="label">Chat ID *</label>
              <input
                id="tg-chatid"
                className="input"
                placeholder="VD: -1001234567890"
                {...form.register('chat_id')}
              />
              {form.formState.errors.chat_id && (
                <p className="text-xs text-red-500 mt-1">{form.formState.errors.chat_id.message}</p>
              )}
              <p className="text-xs text-gray-400 mt-1">
                Dùng số âm cho group/channel. VD: -1001234567890
              </p>
            </div>

            <div className="flex items-center gap-2">
              <input
                id="tg-active"
                type="checkbox"
                className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                {...form.register('is_active')}
              />
              <label htmlFor="tg-active" className="text-sm text-gray-700">
                Kích hoạt ngay
              </label>
            </div>

            <div className="flex gap-3 pt-2">
              <button type="submit" className="btn-primary" disabled={submitting}>
                {submitting ? 'Đang xác thực...' : 'Tạo cấu hình'}
              </button>
              <button
                type="button"
                onClick={() => setShowForm(false)}
                className="btn-secondary"
              >
                Hủy
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Configs List */}
      {configs.length === 0 ? (
        <div className="card text-center py-12">
          <Send className="h-12 w-12 text-gray-300 mx-auto mb-3" aria-hidden="true" />
          <p className="text-gray-500">Chưa có cấu hình Telegram nào.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {configs.map((config) => (
            <div
              key={config.id}
              className={cn('card flex items-center justify-between', !config.is_active && 'opacity-60')}
            >
              <div>
                <div className="flex items-center gap-2">
                  <h4 className="font-semibold text-gray-900">{config.name}</h4>
                  <span
                    className={cn(
                      'text-xs px-2 py-0.5 rounded-full',
                      config.is_active
                        ? 'bg-green-100 text-green-700'
                        : 'bg-gray-100 text-gray-500'
                    )}
                  >
                    {config.is_active ? 'Active' : 'Inactive'}
                  </span>
                </div>
                <div className="mt-1 text-sm text-gray-500 space-y-0.5">
                  <p>Chat ID: <code className="text-xs bg-gray-100 px-1 rounded">{config.chat_id}</code></p>
                  {config.last_sent_at && (
                    <p>Gửi lần cuối: {formatDateTime(config.last_sent_at)}</p>
                  )}
                  <p>Số lần gửi hôm nay: {config.daily_send_count}</p>
                </div>
              </div>

              <div className="flex items-center gap-1">
                <button
                  onClick={() => handleTest(config.id)}
                  disabled={testingId === config.id}
                  className="p-2 rounded-lg hover:bg-green-50 transition-colors"
                  title="Test kết nối"
                  aria-label="Test kết nối Telegram"
                >
                  <Zap
                    className={cn(
                      'h-4 w-4',
                      testingId === config.id ? 'text-gray-400 animate-pulse' : 'text-green-500'
                    )}
                  />
                </button>
                <button
                  onClick={() => handleDelete(config.id)}
                  className="p-2 rounded-lg hover:bg-red-50 transition-colors"
                  title="Xóa"
                  aria-label="Xóa cấu hình"
                >
                  <Trash2 className="h-4 w-4 text-red-500" />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
