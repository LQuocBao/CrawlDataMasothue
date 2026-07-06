/**
 * Core type definitions matching the Laravel backend models.
 */

export interface Company {
  id: number;
  mst: string;
  name: string;
  international_name: string | null;
  short_name: string | null;
  address: string | null;
  province: string | null;
  district: string | null;
  representative: string | null;
  representative_title: string | null;
  phone: string | null;
  registration_date: string | null;
  operation_date: string | null;
  status: string;
  industries: Industry[];
  managing_tax_authority: string | null;
  notification_sent: boolean;
  scraped_at: string;
  created_at: string;
  updated_at: string;
}

export interface Industry {
  code: string;
  description: string;
  is_primary: boolean;
}

export interface Filter {
  id: number;
  name: string;
  provinces: string[] | null;
  industry_keywords: string[] | null;
  industry_codes: string[] | null;
  registration_days_back: number | null;
  require_phone: boolean;
  is_active: boolean;
  telegram_config_id: number | null;
  telegram_config?: TelegramConfig | null;
  created_at: string;
  updated_at: string;
}

export interface TelegramConfig {
  id: number;
  name: string;
  bot_token?: string; // Hidden by default in API
  chat_id: string;
  is_active: boolean;
  last_sent_at: string | null;
  daily_send_count: number;
  created_at: string;
  updated_at: string;
}

export interface FilterFormData {
  name: string;
  provinces: string[];
  industry_keywords: string[];
  industry_codes: string[];
  registration_days_back: number | null;
  require_phone: boolean;
  is_active: boolean;
  telegram_config_id: number | null;
}

export interface TelegramConfigFormData {
  name: string;
  bot_token: string;
  chat_id: string;
  is_active: boolean;
}

export interface DashboardStats {
  total_companies: number;
  with_phone: number;
  sent_today: number;
  notifications_sent: number;
  provinces: string[];
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ApiResponse<T> {
  data: T;
  message?: string;
}
