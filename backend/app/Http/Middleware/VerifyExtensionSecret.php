<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác minh rằng request đến từ Chrome Extension đã được uỷ quyền.
 *
 * Extension phải gửi kèm header: X-Extension-Secret: <giá trị EXTENSION_SECRET trong .env>
 * Nếu header không khớp hoặc bị thiếu → trả về 401 Unauthorized.
 */
class VerifyExtensionSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = config('app.extension_secret');

        // Nếu chưa cấu hình secret trong .env → chặn toàn bộ để tránh lọt qua khi misconfigured
        if (empty($expectedSecret)) {
            return response()->json([
                'error' => 'Server chưa cấu hình EXTENSION_SECRET. Liên hệ admin.',
            ], 500);
        }

        $receivedSecret = $request->header('X-Extension-Secret');

        // So sánh bằng hash_equals để chống timing attack
        if (empty($receivedSecret) || !hash_equals($expectedSecret, $receivedSecret)) {
            return response()->json([
                'error' => 'Unauthorized. Yêu cầu không hợp lệ.',
            ], 401);
        }

        return $next($request);
    }
}
