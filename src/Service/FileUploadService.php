<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    private string $uploadDirectory;

    private array $allowedExtensions = [
        'pptx', 'ppt', 'docx', 'doc', 'xlsx', 'xls', 'pdf', 'jpg', 'jpeg', 'png', 'zip', 'rar'
    ];

    private int $maxFileSize = 50 * 1024 * 1024;

    public function __construct(
        private SluggerInterface $slugger
    ) {

        $this->uploadDirectory = dirname(__DIR__, 2) . '/public/uploads';

        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }
    }

    public function uploadMaterial(UploadedFile $file, int $teacherId): array
    {
        $extension = strtolower($file->guessExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new \Exception(
                "Ruxsat etilmagan fayl formati: {$extension}. " .
                "Ruxsat etilgan formatlar: " . implode(', ', $this->allowedExtensions)
            );
        }

        if ($file->getSize() > $this->maxFileSize) {
            throw new \Exception(
                "Fayl hajmi juda katta. Maksimal ruxsat etilgan hajm: " .
                $this->formatBytes($this->maxFileSize)
            );
        }

        $originalName = $file->getClientOriginalName();
        $timestamp = time();
        $safeFilename = $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME));
        $fileName = "teacher_{$teacherId}_{$safeFilename}_{$timestamp}.{$extension}";

        $file->move($this->uploadDirectory, $fileName);

        return [
            'fileName' => $fileName,
            'originalName' => $originalName,
            'fileType' => $extension
        ];
    }

    public function deleteFile(string $fileName): bool
    {
        $filePath = $this->uploadDirectory . '/' . $fileName;

        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
