'use client';

import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { fetchFilters, fetchTelegramConfigs, createFilter, deleteFilter, updateFilter } from '@/lib/api';
import type { Filter, TelegramConfig } from '@/types';
import { Plus, Trash2, Power, PowerOff, Pencil } from 'lucide-react';
import { cn } from '@/lib/utils';

const filterSchema = z.object({
  name: z.string().min(1, 'Tên bộ lọc không được để trống'),
  provinces_input: z.string().optional(),
  keywords_input: z.string().optional(),
  codes_input: z.string().optional(),
  registration_days_back: z.string().optional(),
  require_phone: z.boolean().default(false),
  is_active: z.boolean().default(true),
  telegram_config_id: z.string().nullable().optional(),
});

type FilterFormValues = z.infer<typeof filterSchema>;

export function FilterManager() {
  const [filters, setFilters] = useState<Filter[]>([]);
  const [telegramConfigs, setTelegramConfigs] = useState<TelegramConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingFilter, setEditingFilter] = useState<Filter | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const form = useForm<FilterFormValues>({
    resolver: zodResolver(filterSchema),
    defaultValues: {
      name: '',
      provinces_input: '',
      keywords_input: '',
      codes_input: '',
      registration_days_back: '',
      require_phone: false,
      is_active: true,
      telegram_config_id: null,
    },
  });

  useEffect(() => {
    loadData();
  }, []);

  async function loadData() {
    try {
      const [filtersRes, telegramRes] = await Promise.all([
        fetchFilters(),
        fetchTelegramConfigs(),
      ]);
      setFilters(filtersRes.data);
      setTelegramConfigs(telegramRes.data);
    } catch (err) {
      console.error('Failed to load data:', err);
    } finally {
      setLoading(false);
    }
  }

  function openEditForm(filter: Filter) {
    setEditingFilter(filter);
    form.reset({
      name: filter.name,
      provinces_input: filter.provinces?.join(', ') ?? '',
      keywords_input: filter.industry_keywords?.join(', ') ?? '',
      codes_input: filter.industry_codes?.join(', ') ?? '',
      registration_days_back: filter.registration_days_back?.toString() ?? '',
      require_phone: filter.require_phone ?? false,
      is_active: filter.is_active,
      telegram_config_id: filter.telegram_config_id?.toString() ?? null,
    });
    setShowForm(true);
  }

  function openCreateForm() {
    setEditingFilter(null);
    form.reset({
      name: '',
      provinces_input: '',
      keywords_input: '',
      codes_input: '',
      registration_days_back: '3',
      require_phone: true,
      is_active: true,
      telegram_config_id: null,
    });
    setShowForm(true);
  }

  async function onSubmit(values: FilterFormValues) {
    setSubmitting(true);
    try {
      const payload = {
        name: values.name,
        provinces: values.provinces_input
          ? values.provinces_input.split(',').map((s) => s.trim()).filter(Boolean)
          : [],
        industry_keywords: values.keywords_input
          ? values.keywords_input.split(',').map((s) => s.trim()).filter(Boolean)
          : [],
        industry_codes: values.codes_input
          ? values.codes_input.split(',').map((s) => s.trim()).filter(Boolean)
          : [],
        registration_days_back: values.registration_days_back
          ? Number(values.registration_days_back)
          : null,
        require_phone: values.require_phone,
        is_active: values.is_active,
        telegram_config_id: values.telegram_config_id
          ? Number(values.telegram_config_id)
          : null,
      };

      if (editingFilter) {
        await updateFilter(editingFilter.id, payload);
      } else {
        await createFilter(payload);
      }

      await loadData();
      setShowForm(false);
      form.reset();
    } catch (err) {
      console.error('Failed to save filter:', err);
      alert(err instanceof Error ? err.message : 'Lỗi khi lưu bộ lọc');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm('Bạn có chắc chắn muốn xóa bộ lọc này?')) return;
    try {
      await deleteFilter(id);
      await loadData();
    } catch (err) {
      console.error('Failed to delete:', err);
    }
  }

  async function handleToggle(filter: Filter) {
    try {
      await updateFilter(filter.id, { is_active: !filter.is_active });
      await loadData();
    } catch (err) {
      console.error('Failed to toggle:', err);
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
          <h2 className="text-2xl font-bold text-gray-900">Bộ lọc</h2>
          <p className="text-sm text-gray-500 mt-1">
            Cấu hình điều kiện lọc doanh nghiệp theo tỉnh, ngành nghề
          </p>
        </div>
        <button onClick={openCreateForm} className="btn-primary">
          <Plus className="h-4 w-4 mr-2" aria-hidden="true" />
          Thêm bộ lọc
        </button>
      </div>

      {/* Create/Edit Form */}
      {showForm && (
        <div className="card" role="form" aria-label={editingFilter ? 'Sửa bộ lọc' : 'Thêm bộ lọc mới'}>
          <h3 className="text-lg font-semibold mb-4">
            {editingFilter ? 'Sửa bộ lọc' : 'Thêm bộ lọc mới'}
          </h3>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            {/* Filter Name */}
            <div>
              <label htmlFor="filter-name" className="label">Tên bộ lọc *</label>
              <input
                id="filter-name"
                className="input"
                placeholder="VD: DN Hà Nội - Công nghệ"
                {...form.register('name')}
              />
              {form.formState.errors.name && (
                <p className="text-xs text-red-500 mt-1">{form.formState.errors.name.message}</p>
              )}
            </div>

            {/* Provinces */}
            <div>
              <label htmlFor="filter-provinces" className="label">Tỉnh/Thành phố</label>
              <input
                id="filter-provinces"
                className="input"
                placeholder="VD: Hà Nội, Hồ Chí Minh, Đà Nẵng (phân cách bằng dấu phẩy)"
                {...form.register('provinces_input')}
              />
              <p className="text-xs text-gray-400 mt-1">
                Để trống = tất cả tỉnh. Phân cách bằng dấu phẩy.
              </p>
            </div>

            {/* Industry Keywords */}
            <div>
              <label htmlFor="filter-keywords" className="label">Từ khóa ngành nghề</label>
              <input
                id="filter-keywords"
                className="input"
                placeholder="VD: công nghệ, phần mềm, thương mại điện tử"
                {...form.register('keywords_input')}
              />
              <p className="text-xs text-gray-400 mt-1">
                Hệ thống sẽ tìm các từ khóa này trong mô tả ngành nghề.
              </p>
            </div>

            {/* Industry Codes */}
            <div>
              <label htmlFor="filter-codes" className="label">Mã ngành (VSIC)</label>
              <input
                id="filter-codes"
                className="input"
                placeholder="VD: 6201, 6202, 4791"
                {...form.register('codes_input')}
              />
              <p className="text-xs text-gray-400 mt-1">
                Mã ngành theo chuẩn VSIC. Phân cách bằng dấu phẩy.
              </p>
            </div>

            {/* Registration Days Back */}
            <div>
              <label htmlFor="filter-days" className="label">Ngày đăng ký (N ngày gần nhất)</label>
              <input
                id="filter-days"
                className="input"
                type="number"
                min="1"
                max="365"
                placeholder="VD: 3 (lấy DN đăng ký trong 3 ngày qua)"
                {...form.register('registration_days_back')}
              />
              <p className="text-xs text-gray-400 mt-1">
                Chỉ lấy DN có ngày đăng ký trong N ngày gần nhất. Để trống = không lọc theo ngày.
              </p>
            </div>

            {/* Require Phone */}
            <div className="flex items-center gap-2">
              <input
                id="filter-require-phone"
                type="checkbox"
                className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                {...form.register('require_phone')}
              />
              <label htmlFor="filter-require-phone" className="text-sm text-gray-700">
                Chỉ lấy DN có số điện thoại
              </label>
            </div>

            {/* Telegram Config */}
            <div>
              <label htmlFor="filter-telegram" className="label">Gửi thông báo đến</label>
              <select
                id="filter-telegram"
                className="input"
                {...form.register('telegram_config_id')}
              >
                <option value="">-- Chọn cấu hình Telegram --</option>
                {telegramConfigs.map((config) => (
                  <option key={config.id} value={config.id}>
                    {config.name} ({config.chat_id})
                  </option>
                ))}
              </select>
            </div>

            {/* Active Toggle */}
            <div className="flex items-center gap-2">
              <input
                id="filter-active"
                type="checkbox"
                className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                {...form.register('is_active')}
              />
              <label htmlFor="filter-active" className="text-sm text-gray-700">
                Kích hoạt bộ lọc
              </label>
            </div>

            {/* Actions */}
            <div className="flex gap-3 pt-2">
              <button type="submit" className="btn-primary" disabled={submitting}>
                {submitting ? 'Đang lưu...' : editingFilter ? 'Cập nhật' : 'Tạo bộ lọc'}
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

      {/* Filters List */}
      {filters.length === 0 ? (
        <div className="card text-center py-12">
          <p className="text-gray-500">Chưa có bộ lọc nào. Bấm "Thêm bộ lọc" để bắt đầu.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {filters.map((filter) => (
            <div
              key={filter.id}
              className={cn(
                'card flex items-start justify-between',
                !filter.is_active && 'opacity-60'
              )}
            >
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <h4 className="font-semibold text-gray-900">{filter.name}</h4>
                  <span
                    className={cn(
                      'text-xs px-2 py-0.5 rounded-full',
                      filter.is_active
                        ? 'bg-green-100 text-green-700'
                        : 'bg-gray-100 text-gray-500'
                    )}
                  >
                    {filter.is_active ? 'Đang hoạt động' : 'Tạm dừng'}
                  </span>
                </div>

                <div className="mt-2 space-y-1 text-sm text-gray-600">
                  {filter.provinces && filter.provinces.length > 0 && (
                    <p>
                      <span className="font-medium">Tỉnh:</span>{' '}
                      {filter.provinces.join(', ')}
                    </p>
                  )}
                  {filter.industry_keywords && filter.industry_keywords.length > 0 && (
                    <p>
                      <span className="font-medium">Từ khóa:</span>{' '}
                      {filter.industry_keywords.join(', ')}
                    </p>
                  )}
                  {filter.industry_codes && filter.industry_codes.length > 0 && (
                    <p>
                      <span className="font-medium">Mã ngành:</span>{' '}
                      {filter.industry_codes.join(', ')}
                    </p>
                  )}
                  {filter.registration_days_back && (
                    <p>
                      <span className="font-medium">Ngày ĐK:</span>{' '}
                      {filter.registration_days_back} ngày gần nhất
                    </p>
                  )}
                  {filter.require_phone && (
                    <p>
                      <span className="font-medium">Yêu cầu:</span>{' '}
                      Phải có SĐT
                    </p>
                  )}
                  {filter.telegram_config && (
                    <p>
                      <span className="font-medium">Telegram:</span>{' '}
                      {filter.telegram_config.name}
                    </p>
                  )}
                </div>
              </div>

              <div className="flex items-center gap-1 ml-4">
                <button
                  onClick={() => handleToggle(filter)}
                  className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                  title={filter.is_active ? 'Tạm dừng' : 'Kích hoạt'}
                  aria-label={filter.is_active ? 'Tạm dừng bộ lọc' : 'Kích hoạt bộ lọc'}
                >
                  {filter.is_active ? (
                    <PowerOff className="h-4 w-4 text-orange-500" />
                  ) : (
                    <Power className="h-4 w-4 text-green-500" />
                  )}
                </button>
                <button
                  onClick={() => openEditForm(filter)}
                  className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                  title="Sửa"
                  aria-label="Sửa bộ lọc"
                >
                  <Pencil className="h-4 w-4 text-gray-500" />
                </button>
                <button
                  onClick={() => handleDelete(filter.id)}
                  className="p-2 rounded-lg hover:bg-red-50 transition-colors"
                  title="Xóa"
                  aria-label="Xóa bộ lọc"
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
