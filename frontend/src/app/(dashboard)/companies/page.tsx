'use client';

import { useEffect, useState, useCallback } from 'react';
import { fetchCompanies } from '@/lib/api';
import type { Company, PaginatedResponse } from '@/types';
import { Search, Phone, Building2, MapPin, ChevronLeft, ChevronRight, ExternalLink } from 'lucide-react';
import { cn } from '@/lib/utils';

const SOURCE_LABELS: Record<string, { label: string; color: string }> = {
  masothue: { label: 'masothue', color: 'bg-blue-100 text-blue-700' },
  tramasothue: { label: 'tramasothue', color: 'bg-purple-100 text-purple-700' },
  both: { label: 'Cả 2 nguồn', color: 'bg-green-100 text-green-700' },
};

export default function CompaniesPage() {
  const [data, setData] = useState<PaginatedResponse<Company> | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [province, setProvince] = useState('');
  const [page, setPage] = useState(1);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const result = await fetchCompanies({ page, per_page: 20, search: search || undefined, province: province || undefined });
      setData(result);
    } catch (err) {
      console.error('Failed to load companies:', err);
    } finally {
      setLoading(false);
    }
  }, [page, search, province]);

  useEffect(() => {
    load();
  }, [load]);

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    setSearch(searchInput);
    setPage(1);
  }

  function handleClear() {
    setSearchInput('');
    setSearch('');
    setProvince('');
    setPage(1);
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-gray-900">Doanh nghiệp</h2>
          <p className="text-sm text-gray-500 mt-1">
            Toàn bộ doanh nghiệp đã thu thập từ các nguồn
            {data && <span className="ml-1 font-medium text-gray-700">({data.total.toLocaleString('vi-VN')} DN)</span>}
          </p>
        </div>
      </div>

      {/* Search & Filter Bar */}
      <div className="card">
        <form onSubmit={handleSearch} className="flex flex-wrap gap-3 items-end">
          <div className="flex-1 min-w-[200px]">
            <label htmlFor="company-search" className="label">Tìm kiếm</label>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" aria-hidden="true" />
              <input
                id="company-search"
                className="input pl-9"
                placeholder="Tên, MST, người đại diện..."
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
              />
            </div>
          </div>

          <div className="w-48">
            <label htmlFor="province-filter" className="label">Tỉnh/Thành phố</label>
            <input
              id="province-filter"
              className="input"
              placeholder="VD: Hà Nội"
              value={province}
              onChange={(e) => { setProvince(e.target.value); setPage(1); }}
            />
          </div>

          <div className="flex gap-2">
            <button type="submit" className="btn-primary">
              Tìm
            </button>
            <button type="button" onClick={handleClear} className="btn-secondary">
              Xóa lọc
            </button>
          </div>
        </form>
      </div>

      {/* Table */}
      <div className="card p-0 overflow-hidden">
        {loading ? (
          <div className="animate-pulse divide-y divide-gray-100">
            {[...Array(5)].map((_, i) => (
              <div key={i} className="p-4 flex gap-4">
                <div className="h-4 bg-gray-200 rounded w-32" />
                <div className="h-4 bg-gray-200 rounded flex-1" />
                <div className="h-4 bg-gray-200 rounded w-24" />
              </div>
            ))}
          </div>
        ) : !data || data.data.length === 0 ? (
          <div className="text-center py-16">
            <Building2 className="h-12 w-12 text-gray-300 mx-auto mb-3" aria-hidden="true" />
            <p className="text-gray-500">Không tìm thấy doanh nghiệp nào.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                  <th className="px-4 py-3">MST</th>
                  <th className="px-4 py-3">Tên doanh nghiệp</th>
                  <th className="px-4 py-3">Số ĐT</th>
                  <th className="px-4 py-3">Tỉnh/TP</th>
                  <th className="px-4 py-3">Ngày ĐK</th>
                  <th className="px-4 py-3">Nguồn</th>
                  <th className="px-4 py-3">Đã gửi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data.data.map((company) => {
                  const src = SOURCE_LABELS[company.source as string] ?? { label: company.source as string, color: 'bg-gray-100 text-gray-600' };
                  return (
                    <tr key={company.id} className="hover:bg-gray-50 transition-colors">
                      <td className="px-4 py-3 font-mono text-xs text-gray-600 whitespace-nowrap">
                        <a
                          href={`https://masothue.com/${company.mst}`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-blue-600 hover:underline flex items-center gap-1"
                        >
                          {company.mst}
                          <ExternalLink className="h-3 w-3" />
                        </a>
                      </td>
                      <td className="px-4 py-3 max-w-[260px]">
                        <p className="font-medium text-gray-900 truncate" title={company.name}>{company.name}</p>
                        {company.representative && (
                          <p className="text-xs text-gray-400 truncate">{company.representative}</p>
                        )}
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        {company.phone ? (
                          <span className="flex items-center gap-1 text-green-700 font-medium">
                            <Phone className="h-3.5 w-3.5" aria-hidden="true" />
                            {company.phone}
                          </span>
                        ) : (
                          <span className="text-gray-300 text-xs">Không có</span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        {company.province ? (
                          <span className="flex items-center gap-1 text-gray-600">
                            <MapPin className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                            <span className="truncate max-w-[100px]">{company.province}</span>
                          </span>
                        ) : (
                          <span className="text-gray-300 text-xs">—</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                        {company.operation_date
                          ? new Date(company.operation_date).toLocaleDateString('vi-VN')
                          : '—'}
                      </td>
                      <td className="px-4 py-3">
                        <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium', src.color)}>
                          {src.label}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        {company.notification_sent ? (
                          <span className="inline-flex items-center gap-1 text-xs text-green-600 font-medium">
                            <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                            Đã gửi
                          </span>
                        ) : (
                          <span className="text-xs text-gray-400">Chưa gửi</span>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Pagination */}
      {data && data.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <p className="text-gray-500">
            Trang <span className="font-medium">{data.current_page}</span> / <span className="font-medium">{data.last_page}</span>
            {' '}· Tổng <span className="font-medium">{data.total.toLocaleString('vi-VN')}</span> doanh nghiệp
          </p>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page === 1}
              className="btn-secondary py-1.5 px-3 flex items-center gap-1 disabled:opacity-40"
              aria-label="Trang trước"
            >
              <ChevronLeft className="h-4 w-4" aria-hidden="true" />
              Trước
            </button>
            <button
              onClick={() => setPage((p) => Math.min(data.last_page, p + 1))}
              disabled={page === data.last_page}
              className="btn-secondary py-1.5 px-3 flex items-center gap-1 disabled:opacity-40"
              aria-label="Trang sau"
            >
              Sau
              <ChevronRight className="h-4 w-4" aria-hidden="true" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
