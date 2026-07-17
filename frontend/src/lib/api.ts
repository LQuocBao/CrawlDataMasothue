/**
 * API client for communicating with the Laravel backend.
 * All requests are routed through Next.js rewrites in development.
 */

import type {
  ApiResponse,
  Company,
  DashboardStats,
  Filter,
  FilterFormData,
  PaginatedResponse,
  TelegramConfig,
  TelegramConfigFormData,
} from '@/types';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || '';

async function request<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  const url = `${API_BASE}/api/v1${endpoint}`;

  const response = await fetch(url, {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...options.headers,
    },
    ...options,
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    throw new Error(error.message || `API Error: ${response.status}`);
  }

  return response.json();
}

// ─── Companies ──────────────────────────────────────────────────────────────

export async function fetchCompanies(params?: {
  page?: number;
  per_page?: number;
  province?: string;
  search?: string;
}): Promise<PaginatedResponse<Company>> {
  const searchParams = new URLSearchParams();
  if (params?.page) searchParams.set('page', String(params.page));
  if (params?.per_page) searchParams.set('per_page', String(params.per_page));
  if (params?.province) searchParams.set('province', params.province);
  if (params?.search) searchParams.set('search', params.search);

  const query = searchParams.toString();
  return request<PaginatedResponse<Company>>(`/companies${query ? `?${query}` : ''}`);
}

export async function fetchDashboardStats(): Promise<ApiResponse<DashboardStats>> {
  return request<ApiResponse<DashboardStats>>('/companies/stats');
}

// ─── Filters ────────────────────────────────────────────────────────────────

export async function fetchFilters(): Promise<ApiResponse<Filter[]>> {
  return request<ApiResponse<Filter[]>>('/filters');
}

export async function createFilter(data: FilterFormData): Promise<ApiResponse<Filter>> {
  return request<ApiResponse<Filter>>('/filters', {
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateFilter(
  id: number,
  data: Partial<FilterFormData>
): Promise<ApiResponse<Filter>> {
  return request<ApiResponse<Filter>>(`/filters/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function deleteFilter(id: number): Promise<void> {
  await request(`/filters/${id}`, { method: 'DELETE' });
}

// ─── Telegram Configs ───────────────────────────────────────────────────────

export async function fetchTelegramConfigs(): Promise<ApiResponse<TelegramConfig[]>> {
  return request<ApiResponse<TelegramConfig[]>>('/telegram-configs');
}

export async function createTelegramConfig(
  data: TelegramConfigFormData
): Promise<ApiResponse<TelegramConfig>> {
  return request<ApiResponse<TelegramConfig>>('/telegram-configs', {
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function updateTelegramConfig(
  id: number,
  data: Partial<TelegramConfigFormData>
): Promise<ApiResponse<TelegramConfig>> {
  return request<ApiResponse<TelegramConfig>>(`/telegram-configs/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function deleteTelegramConfig(id: number): Promise<void> {
  await request(`/telegram-configs/${id}`, { method: 'DELETE' });
}

export async function testTelegramConfig(
  id: number
): Promise<{ success: boolean; message: string }> {
  return request(`/telegram-configs/${id}/test`, { method: 'POST' });
}

// ─── Settings ───────────────────────────────────────────────────────────────

import type { AppSetting, SettingsUpdatePayload } from '@/types';

export async function fetchSettings(): Promise<ApiResponse<AppSetting[]>> {
  return request<ApiResponse<AppSetting[]>>('/settings');
}

export async function updateSettings(
  data: SettingsUpdatePayload
): Promise<ApiResponse<AppSetting[]>> {
  return request<ApiResponse<AppSetting[]>>('/settings', {
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export async function testSheetConnection(
  url?: string
): Promise<{ success: boolean; message: string }> {
  return request('/settings/test-sheet', {
    method: 'POST',
    body: JSON.stringify({ url }),
  });
}
