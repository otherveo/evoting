<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SupabaseStorageService
{
    public function uploadCandidatePhoto(string $electionId, string $candidateId, UploadedFile $file): string
    {
        $baseUrl = rtrim((string) config('evoting.supabase.url'), '/');
        $secretKey = (string) config('evoting.supabase.secret_key');
        $bucket = (string) config('evoting.supabase.bucket', 'paslon-foto');

        if ($baseUrl === '' || $secretKey === '') {
            throw new RuntimeException('Konfigurasi penyimpanan foto belum lengkap.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $objectPath = "{$electionId}/{$candidateId}-".substr(bin2hex(random_bytes(4)), 0, 8).".{$ext}";
        $mimeType = $file->getMimeType() ?: 'image/jpeg';
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Gagal membaca berkas foto.');
        }

        $endpoint = "{$baseUrl}/storage/v1/object/{$bucket}/{$objectPath}";

        $response = Http::timeout(15)
            ->withHeaders([
                'Authorization' => "Bearer {$secretKey}",
                'apikey' => $secretKey,
                'x-upsert' => 'true',
            ])
            ->withBody($contents, $mimeType)
            ->post($endpoint);

        if (! $response->successful()) {
            Log::warning('Gagal mengunggah foto paslon ke Supabase Storage', [
                'status' => $response->status(),
                'election_id' => $electionId,
                'candidate_id' => $candidateId,
            ]);
            throw new RuntimeException('Gagal mengunggah foto paslon ke penyimpanan. Silakan coba lagi.');
        }

        return $objectPath;
    }

    public function deleteCandidatePhoto(?string $objectPath): void
    {
        if (! $objectPath || str_starts_with($objectPath, 'http') || str_starts_with($objectPath, 'data:')) {
            return;
        }

        $baseUrl = rtrim((string) config('evoting.supabase.url'), '/');
        $secretKey = (string) config('evoting.supabase.secret_key');
        $bucket = (string) config('evoting.supabase.bucket', 'paslon-foto');

        if ($baseUrl === '' || $secretKey === '') {
            return;
        }

        $cleanPath = ltrim($objectPath, '/');
        if (str_starts_with($cleanPath, $bucket.'/')) {
            $cleanPath = substr($cleanPath, strlen($bucket) + 1);
        }

        try {
            Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$secretKey}",
                    'apikey' => $secretKey,
                ])
                ->delete("{$baseUrl}/storage/v1/object/{$bucket}/{$cleanPath}");
        } catch (\Throwable $e) {
            Log::warning('Gagal menghapus foto lama dari Supabase Storage', [
                'path' => $cleanPath,
            ]);
        }
    }
}
