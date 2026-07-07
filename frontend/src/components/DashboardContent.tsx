'use client';

import { useEffect, useState } from 'react';
import { fetchDashboardStats } from '@/lib/api';
import type { DashboardStats } from '@/types';
import { Building2, Phone, Send, TrendingUp } from 'lucide-react';

export function DashboardContent() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadStats();
    const interval = setInterval(loadStats, 15000);
    return () => clearInterval(interval);
  }, []);

  async function loadStats() {
    try {
      const response = await fetchDashboardStats();
      setStats(response.data);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load stats');
    } finally {
      setLoading(false);
    }
  }

  if (loading) {
    return (
      <div className="animate-pulse space-y-6">
        <div className="h-8 w-48 bg-gray-200 rounded" />
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="h-32 bg-gray-100 rounded-xl" />
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
        <p className="text-sm text-red-700">Lỗi: {error}</p>
        <button onClick={loadStats} className="btn-secondary mt-2 text-xs">
          Thử lại
        </button>
      </div>
    );
  }

  const totalCompanies = stats?.total_companies ?? 0;
  const withPhone = stats?.with_phone ?? 0;
  const sentToday = stats?.sent_today ?? 0;
  const skipped = totalCompanies - withPhone;

  const statCards = [
    {
      label: 'Tổng DN đã quét',
      value: totalCompanies,
      icon: Building2,
      color: 'text-blue-600',
      bg: 'bg-blue-50',
      desc: 'Tất cả DN đã thu thập',
    },
    {
      label: 'DN có SĐT',
      value: withPhone,
      icon: Phone,
      color: 'text-green-600',
      bg: 'bg-green-50',
      desc: 'DN đủ điều kiện gửi',
    },
    {
      label: 'Đã gửi hôm nay',
      value: sentToday,
      icon: Send,
      color: 'text-purple-600',
      bg: 'bg-purple-50',
      desc: 'Thông báo Telegram',
    },
    {
      label: 'DN bị loại',
      value: skipped,
      icon: TrendingUp,
      color: 'text-red-500',
      bg: 'bg-red-50',
      desc: 'Không có SĐT',
    },
  ];

  return (
    <div className="space-y-8">
      <div>
        <h2 className="text-2xl font-bold text-gray-900">Dashboard</h2>
        <p className="text-sm text-gray-500 mt-1">
          Tổng quan hệ thống giám sát doanh nghiệp mới đăng ký
        </p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {statCards.map((card) => {
          const Icon = card.icon;
          return (
            <div key={card.label} className="card">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-500">{card.label}</p>
                  <p className="text-3xl font-bold text-gray-900 mt-1">
                    {typeof card.value === 'number' ? card.value.toLocaleString('vi-VN') : card.value}
                  </p>
                  <p className="text-xs text-gray-400 mt-1">{card.desc}</p>
                </div>
                <div className={`rounded-full p-3 ${card.bg}`}>
                  <Icon className={`h-6 w-6 ${card.color}`} aria-hidden="true" />
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Recent Activity */}
      <div className="card">
        <h3 className="text-lg font-semibold text-gray-900 mb-2">Trạng thái hệ thống</h3>
        <div className="space-y-3">
          <div className="flex items-center gap-3">
            <span className="relative flex h-3 w-3">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
            <span className="text-sm text-gray-700">Scraper đang hoạt động (quét mỗi 30 giây)</span>
          </div>
          <div className="flex items-center gap-3">
            <span className="relative flex h-3 w-3">
              <span className="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
            <span className="text-sm text-gray-700">Telegram Bot kết nối</span>
          </div>
          <div className="flex items-center gap-3">
            <span className="relative flex h-3 w-3">
              <span className="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
            <span className="text-sm text-gray-700">Proxy TMProxy active</span>
          </div>
        </div>
      </div>

      {/* Provinces Summary */}
      {stats?.provinces && stats.provinces.length > 0 && (
        <div className="card">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">
            Tỉnh/Thành phố đã thu thập ({stats.provinces.length})
          </h3>
          <div className="flex flex-wrap gap-2">
            {stats.provinces.map((province) => (
              <span
                key={province}
                className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700"
              >
                {province}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
