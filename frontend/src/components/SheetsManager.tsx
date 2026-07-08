'use client';

import { useEffect, useState } from 'react';
import { FileSpreadsheet, ExternalLink } from 'lucide-react';

interface SheetItem {
  id: string;
  name: string;
  date: string;
  url: string;
}

export function SheetsManager() {
  const [sheets, setSheets] = useState<SheetItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadSheets();
  }, []);

  async function loadSheets() {
    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || '';
      const res = await fetch(`${apiUrl}/api/v1/sheets`);
      const data = await res.json();
      setSheets(data.data || []);
    } catch (err) {
      console.error('Failed to load sheets:', err);
    } finally {
      setLoading(false);
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
      <div>
        <h2 className="text-2xl font-bold text-gray-900">Google Sheets</h2>
        <p className="text-sm text-gray-500 mt-1">
          Dữ liệu DN mới mỗi ngày (lưu trữ 30 ngày)
        </p>
      </div>

      {sheets.length === 0 ? (
        <div className="card text-center py-12">
          <FileSpreadsheet className="h-12 w-12 text-gray-300 mx-auto mb-3" />
          <p className="text-gray-500">Chưa có dữ liệu. Sheet sẽ được tạo khi có DN mới.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {sheets.map((sheet) => {
            const date = new Date(sheet.date);
            const formattedDate = date.toLocaleDateString('vi-VN', {
              weekday: 'long',
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
            });

            return (
              <a
                key={sheet.id}
                href={sheet.url}
                target="_blank"
                rel="noopener noreferrer"
                className="card flex items-center justify-between hover:border-green-300 hover:shadow-md transition-all group"
              >
                <div className="flex items-center gap-4">
                  <div className="rounded-lg bg-green-50 p-3">
                    <FileSpreadsheet className="h-6 w-6 text-green-600" />
                  </div>
                  <div>
                    <h4 className="font-semibold text-gray-900 group-hover:text-green-700">
                      {sheet.name}
                    </h4>
                    <p className="text-sm text-gray-500">{formattedDate}</p>
                  </div>
                </div>
                <ExternalLink className="h-5 w-5 text-gray-400 group-hover:text-green-600" />
              </a>
            );
          })}
        </div>
      )}
    </div>
  );
}
