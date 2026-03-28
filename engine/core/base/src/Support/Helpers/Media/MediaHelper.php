<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Media;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * MediaHelper — ดึงข้อมูล media จาก external services
 *
 * ความรับผิดชอบ:
 * - YouTube Data API v3: ดึง metadata ของ video
 *
 * หมายเหตุ:
 *   Base64 image conversion → ใช้ ImageBase64Converter แทน
 *   Media upload/retrieval  → ใช้ FileStorageService แทน
 */
final class MediaHelper
{
    /**
     * ดึงข้อมูล video จาก YouTube Data API v3
     *
     * @param  string  $videoId  YouTube video ID เช่น 'dQw4w9WgXcQ'
     * @param  string  $apiKey  YouTube Data API key
     * @return array|null ข้อมูล video (snippet, contentDetails, statistics) หรือ null ถ้าไม่พบ
     *
     * @throws InvalidArgumentException ถ้า videoId หรือ apiKey ว่างเปล่า
     */
    public function getYouTubeVideoInfo(string $videoId, string $apiKey): ?array
    {
        if ($videoId === '' || $apiKey === '') {
            throw new InvalidArgumentException('Video ID and API key are required.');
        }

        try {
            $response = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/videos', [
                'id' => $videoId,
                'key' => $apiKey,
                'part' => 'snippet,contentDetails,statistics',
                'fields' => 'items(id,snippet,contentDetails,statistics)',
            ]);

            if ($response->successful()) {
                return $response->json('items.0');
            }

            Log::warning('YouTube API request failed', ['status' => $response->status(), 'videoId' => $videoId]);

            return null;
        } catch (Throwable $e) {
            Log::error('YouTube API error', ['message' => $e->getMessage(), 'videoId' => $videoId]);

            return null;
        }
    }
}
