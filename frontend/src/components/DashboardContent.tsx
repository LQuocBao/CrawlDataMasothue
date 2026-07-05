'use client';

import { useEffect, useState } from 'react';
import { fetchDashboardStats } from '@/lib/api';
import type { DashboardStats } from '@/types';
import { Building2, CheckCircle, Clock, TrendingUp } from 'lucide-react';

export function DashboardContent() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadStats();
    // Refresh every 30 seconds
    const interval = setInterval(loadStats, 30000);
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
            <div key={i} className="h-32 bg-gray-200 rounded-xl" />
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

  const statCards = [
    {
      label: 'Tổng doanh nghiệp',
      value: stats?.total_companies ?? 0,
      icon: Building2,
      color: 'text-blue-600',
      bg: 'bg-blue-50',
    },
    {
      label: 'Hôm nay',
      value: stats?.today_scraped ?? 0,
      icon: TrendingUp,
      color: 'text-green-600',
      bg: 'bg-green-50',
    },
    {
      label: 'Đã gửi thông báo',
      value: stats?.notifications_sent ?? 0,
      icon: CheckCircle,
      color: 'text-purple-600',
      bg: 'bg-purple-50',
    },
    {
      label: 'Chờ xử lý',
      value: stats?.pending_notifications ?? 0,
      icon: Clock,
      color: 'text-orange-600',
      bg: 'bg-orange-50',
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
                    {card.value.toLocaleString('vi-VN')}
                  </p>
                </div>
                <div className={`rounded-full p-3 ${card.bg}`}>
                  <Icon className={`h-6 w-6 ${card.color}`} aria-hidden="true" />
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Provinces Summary */}
      {stats?.provinces && stats.provinces.length > 0 && (
        <div className="card">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">
            Tỉnh/Thành phố đã thu thập
          </h3>
          <div className="flex flex-wrap gap-2">
            {stats.provinces.map((province) => (
              <span
                key={province}
                className="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700"
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
