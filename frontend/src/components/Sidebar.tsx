'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import { LayoutDashboard, Filter, Send, Database, FileSpreadsheet } from 'lucide-react';

const navItems = [
  { href: '/', label: 'Dashboard', icon: LayoutDashboard },
  { href: '/filters', label: 'Bộ lọc', icon: Filter },
  { href: '/telegram', label: 'Telegram', icon: Send },
  { href: '/sheets', label: 'Google Sheets', icon: FileSpreadsheet },
];

export function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="w-64 border-r border-gray-200 bg-white flex flex-col" role="navigation" aria-label="Main navigation">
      <div className="p-6 border-b border-gray-100">
        <div className="flex items-center gap-2">
          <Database className="h-6 w-6 text-primary-600" aria-hidden="true" />
          <h1 className="text-lg font-bold text-gray-900">MST Scraper</h1>
        </div>
        <p className="text-xs text-gray-500 mt-1">Giám sát doanh nghiệp mới</p>
      </div>

      <nav className="flex-1 p-4 space-y-1">
        {navItems.map((item) => {
          const Icon = item.icon;
          const isActive = pathname === item.href;

          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                isActive
                  ? 'bg-primary-50 text-primary-700'
                  : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
              )}
              aria-current={isActive ? 'page' : undefined}
            >
              <Icon className="h-5 w-5" aria-hidden="true" />
              {item.label}
            </Link>
          );
        })}
      </nav>

      <div className="p-4 border-t border-gray-100">
        <div className="rounded-lg bg-green-50 p-3">
          <div className="flex items-center gap-2">
            <span className="relative flex h-2 w-2">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
            </span>
            <span className="text-xs font-medium text-green-700">Scraper đang chạy</span>
          </div>
        </div>
      </div>
    </aside>
  );
}
