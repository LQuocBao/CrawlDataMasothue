import { Sidebar } from '@/components/Sidebar';

/**
 * Layout chung cho tất cả trang dashboard.
 * Sidebar chỉ render 1 lần, không re-render khi chuyển trang.
 */
export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex h-screen">
      <Sidebar />
      <main className="flex-1 overflow-y-auto p-8">
        {children}
      </main>
    </div>
  );
}
