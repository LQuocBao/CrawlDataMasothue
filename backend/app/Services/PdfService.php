<?php

namespace App\Services;

use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating formatted PDF reports for company data.
 * Uses barryvdh/laravel-dompdf for rendering.
 */
class PdfService
{
    /** Storage disk for generated PDFs */
    private const DISK = 'local';

    /** Directory for storing generated PDFs */
    private const PDF_DIR = 'pdfs';

    /**
     * Generate a PDF report for a company.
     *
     * @return string Absolute path to the generated PDF file
     */
    public function generateCompanyPdf(Company $company): string
    {
        $filename = sprintf(
            '%s/%s.pdf',
            self::PDF_DIR,
            $this->sanitizeFilename($company->name)
        );

        $pdf = Pdf::loadView('pdf.company-report', [
            'company' => $company,
            'industries' => $company->industries ?? [],
            'generatedAt' => now()->format('d/m/Y H:i:s'),
        ]);

        // A4 paper, portrait orientation
        $pdf->setPaper('a4', 'portrait');

        // Set PDF metadata
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        // Store the PDF
        Storage::disk(self::DISK)->put($filename, $pdf->output());

        $fullPath = Storage::disk(self::DISK)->path($filename);

        Log::info('PdfService: Generated PDF', [
            'mst' => $company->mst,
            'path' => $fullPath,
            'size_bytes' => filesize($fullPath),
        ]);

        return $fullPath;
    }

    /**
     * Clean up a PDF file after successful delivery.
     */
    public function cleanup(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Chuyển tên công ty thành tên file hợp lệ.
     * VD: "CÔNG TY TNHH ABC & XYZ" → "CONG-TY-TNHH-ABC-XYZ"
     */
    private function sanitizeFilename(string $name): string
    {
        // Bỏ dấu tiếng Việt
        $name = $this->removeVietnameseAccents($name);
        // Thay ký tự đặc biệt bằng dấu gạch
        $name = preg_replace('/[^A-Za-z0-9]+/', '-', $name);
        // Xóa gạch đầu/cuối, giới hạn 100 ký tự
        $name = trim($name, '-');
        $name = substr($name, 0, 100);
        return strtoupper($name);
    }

    /**
     * Bỏ dấu tiếng Việt.
     */
    private function removeVietnameseAccents(string $str): string
    {
        $map = [
            'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a',
            'ă'=>'a','ắ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
            'â'=>'a','ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
            'đ'=>'d',
            'è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e',
            'ê'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
            'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
            'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o',
            'ô'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
            'ơ'=>'o','ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
            'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u',
            'ư'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
            'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
            'À'=>'A','Á'=>'A','Ả'=>'A','Ã'=>'A','Ạ'=>'A',
            'Ă'=>'A','Ắ'=>'A','Ằ'=>'A','Ẳ'=>'A','Ẵ'=>'A','Ặ'=>'A',
            'Â'=>'A','Ấ'=>'A','Ầ'=>'A','Ẩ'=>'A','Ẫ'=>'A','Ậ'=>'A',
            'Đ'=>'D',
            'È'=>'E','É'=>'E','Ẻ'=>'E','Ẽ'=>'E','Ẹ'=>'E',
            'Ê'=>'E','Ế'=>'E','Ề'=>'E','Ể'=>'E','Ễ'=>'E','Ệ'=>'E',
            'Ì'=>'I','Í'=>'I','Ỉ'=>'I','Ĩ'=>'I','Ị'=>'I',
            'Ò'=>'O','Ó'=>'O','Ỏ'=>'O','Õ'=>'O','Ọ'=>'O',
            'Ô'=>'O','Ố'=>'O','Ồ'=>'O','Ổ'=>'O','Ỗ'=>'O','Ộ'=>'O',
            'Ơ'=>'O','Ớ'=>'O','Ờ'=>'O','Ở'=>'O','Ỡ'=>'O','Ợ'=>'O',
            'Ù'=>'U','Ú'=>'U','Ủ'=>'U','Ũ'=>'U','Ụ'=>'U',
            'Ư'=>'U','Ứ'=>'U','Ừ'=>'U','Ử'=>'U','Ữ'=>'U','Ự'=>'U',
            'Ỳ'=>'Y','Ý'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y','Ỵ'=>'Y',
        ];
        return strtr($str, $map);
    }
}
