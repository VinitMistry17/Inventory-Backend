<?php

namespace App\Services;

use App\Contracts\MediaUploader;
use Cloudinary\Cloudinary;

class CloudinaryMediaUploader implements MediaUploader
{
    protected $cloudinary;

    public function __construct($cloudinary = null)
    {
        $cloudName = config('cloudinary.cloud.cloud_name');
        if ($cloudName === null) {
            $cloudName = env('CLOUDINARY_CLOUD_NAME');
        }

        $apiKey = config('cloudinary.api.key');
        if ($apiKey === null) {
            $apiKey = env('CLOUDINARY_API_KEY');
        }

        $apiSecret = config('cloudinary.api.secret');
        if ($apiSecret === null) {
            $apiSecret = env('CLOUDINARY_API_SECRET');
        }

        if (blank($cloudName) || blank($apiKey) || blank($apiSecret)) {
            throw new \RuntimeException(
                'Cloudinary configuration is incomplete. Set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET in your environment or config.'
            );
        }

        $this->cloudinary = $cloudinary ?? new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => ['secure' => true],
        ]);
    }

    public function uploadFile($file, string $folder): array
    {
        $result = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => $folder,
        ]);

        return [
            'secure_url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    public function delete(string $publicId): void
    {
        $this->cloudinary->uploadApi()->destroy($publicId);
    }
}
