<?php

namespace App\Services;

use App\Models\Company;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\ValueRange;
use Google\Service\Drive;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service tích hợp Google Sheets.
 * - Mỗi ngày tạo 1 sheet mới trong folder Drive
 * - Mỗi DN mới có SĐT → thêm 1 dòng
 * - Sheet quá 30 ngày → tự xóa
 */
class GoogleSheetService
{
    private ?Client $client = null;
    private ?Sheets $sheetsService = null;
    private ?Drive $driveService = null;

    private const FOLDER_ID = '10O6NWxq6s92Kb63YpqMb6x04gmAwW6y9';
    private const CACHE_PREFIX = 'gsheet_id:';
    private const RETENTION_DAYS = 30;

    /**
     * Thêm 1 dòng DN vào Google Sheet hôm nay.
     */
    public function appendCompany(Company $company): bool
    {
        try {
            $sheetId = $this->getOrCreateTodaySheet();
            if (!$sheetId) return false;

            $sheets = $this->getSheetsService();

            $primaryIndustry = '';
            $industries = $company->industries ?? [];
            $primary = collect($industries)->firstWhere('is_primary', true);
            $primaryIndustry = $primary['description'] ?? ($industries[0]['description'] ?? '');

            $values = [[
                $company->mst,
                $company->name,
                $company->phone ?? '',
                $company->address ?? '',
                $company->representative ?? '',
                $company->operation_date?->format('d/m/Y') ?? '',
                $primaryIndustry,
                $company->province ?? '',
                now()->format('H:i:s'),
            ]];

            $body = new ValueRange(['values' => $values]);

            $sheets->spreadsheets_values->append(
                $sheetId,
                'A:I',
                $body,
                ['valueInputOption' => 'RAW']
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('GoogleSheetService: Failed to append', [
                'mst' => $company->mst,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Lấy hoặc tạo Google Sheet cho ngày hôm nay.
     */
    public function getOrCreateTodaySheet(): ?string
    {
        $date = now()->format('Y-m-d');
        $cacheKey = self::CACHE_PREFIX . $date;

        // Check cache trước
        $sheetId = Cache::get($cacheKey);
        if ($sheetId) return $sheetId;

        try {
            $drive = $this->getDriveService();
            $title = 'DN mới ' . now()->format('d-m-Y');

            // Tìm sheet hôm nay đã tạo chưa (tránh tạo trùng)
            $query = "name='{$title}' and '{$this->getFolderId()}' in parents and trashed=false";
            $results = $drive->files->listFiles([
                'q' => $query,
                'fields' => 'files(id)',
                'spaces' => 'drive',
            ]);

            if (count($results->getFiles()) > 0) {
                $sheetId = $results->getFiles()[0]->getId();
                Cache::put($cacheKey, $sheetId, 86400);
                return $sheetId;
            }

            // Tạo sheet mới
            $sheets = $this->getSheetsService();
            $spreadsheet = new Spreadsheet([
                'properties' => ['title' => $title],
            ]);

            $created = $sheets->spreadsheets->create($spreadsheet);
            $sheetId = $created->getSpreadsheetId();

            // Di chuyển vào folder
            $drive->files->update($sheetId, new \Google\Service\Drive\DriveFile(), [
                'addParents' => $this->getFolderId(),
                'removeParents' => 'root',
                'fields' => 'id, parents',
            ]);

            // Thêm header row
            $headers = new ValueRange([
                'values' => [['MST', 'Tên DN', 'SĐT', 'Địa chỉ', 'Đại diện', 'Ngày TL', 'Ngành nghề chính', 'Tỉnh/TP', 'Giờ quét']]
            ]);
            $sheets->spreadsheets_values->update(
                $sheetId,
                'A1:I1',
                $headers,
                ['valueInputOption' => 'RAW']
            );

            // Share public (anyone with link can view)
            $permission = new \Google\Service\Drive\Permission([
                'type' => 'anyone',
                'role' => 'reader',
            ]);
            $drive->permissions->create($sheetId, $permission);

            Cache::put($cacheKey, $sheetId, 86400);

            // Cleanup sheets cũ hơn 30 ngày
            $this->cleanupOldSheets();

            Log::info("GoogleSheetService: Created sheet '{$title}'", ['id' => $sheetId]);

            return $sheetId;
        } catch (\Throwable $e) {
            Log::error('GoogleSheetService: Failed to create sheet', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Lấy danh sách sheets (30 ngày gần nhất) để hiện trên frontend.
     */
    public function listSheets(): array
    {
        try {
            $drive = $this->getDriveService();

            $query = "'{$this->getFolderId()}' in parents and trashed=false and mimeType='application/vnd.google-apps.spreadsheet'";
            $results = $drive->files->listFiles([
                'q' => $query,
                'fields' => 'files(id, name, createdTime, webViewLink)',
                'orderBy' => 'createdTime desc',
                'pageSize' => 30,
            ]);

            $sheets = [];
            foreach ($results->getFiles() as $file) {
                $sheets[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'date' => $file->getCreatedTime(),
                    'url' => $file->getWebViewLink(),
                ];
            }

            return $sheets;
        } catch (\Throwable $e) {
            Log::error('GoogleSheetService: Failed to list sheets', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Xóa sheets cũ hơn 30 ngày.
     */
    private function cleanupOldSheets(): void
    {
        try {
            $drive = $this->getDriveService();
            $cutoff = now()->subDays(self::RETENTION_DAYS)->format('Y-m-d\TH:i:s');

            $query = "'{$this->getFolderId()}' in parents and trashed=false and createdTime < '{$cutoff}'";
            $results = $drive->files->listFiles([
                'q' => $query,
                'fields' => 'files(id, name)',
            ]);

            foreach ($results->getFiles() as $file) {
                $drive->files->delete($file->getId());
                Log::info("GoogleSheetService: Deleted old sheet '{$file->getName()}'");
            }
        } catch (\Throwable $e) {
            Log::warning('GoogleSheetService: Cleanup failed', ['error' => $e->getMessage()]);
        }
    }

    private function getFolderId(): string
    {
        return config('scraper.google_drive_folder_id', self::FOLDER_ID);
    }

    private function getClient(): Client
    {
        if ($this->client) return $this->client;

        $this->client = new Client();
        $this->client->setAuthConfig(config('scraper.google_credentials_path'));
        $this->client->addScope(Sheets::SPREADSHEETS);
        $this->client->addScope(Drive::DRIVE);

        return $this->client;
    }

    private function getSheetsService(): Sheets
    {
        if ($this->sheetsService) return $this->sheetsService;
        $this->sheetsService = new Sheets($this->getClient());
        return $this->sheetsService;
    }

    private function getDriveService(): Drive
    {
        if ($this->driveService) return $this->driveService;
        $this->driveService = new Drive($this->getClient());
        return $this->driveService;
    }
}
