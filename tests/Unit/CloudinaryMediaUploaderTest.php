<?php

namespace Tests\Unit;

use App\Services\CloudinaryMediaUploader;
use Tests\TestCase;

class CloudinaryMediaUploaderTest extends TestCase
{
    public function test_missing_cloudinary_credentials_throw_clear_runtime_exception(): void
    {
        $_ENV['CLOUDINARY_CLOUD_NAME'] = '';
        $_ENV['CLOUDINARY_API_KEY'] = '';
        $_ENV['CLOUDINARY_API_SECRET'] = '';

        $_SERVER['CLOUDINARY_CLOUD_NAME'] = '';
        $_SERVER['CLOUDINARY_API_KEY'] = '';
        $_SERVER['CLOUDINARY_API_SECRET'] = '';

        putenv('CLOUDINARY_CLOUD_NAME=');
        putenv('CLOUDINARY_API_KEY=');
        putenv('CLOUDINARY_API_SECRET=');

        config([
            'cloudinary.cloud.cloud_name' => null,
            'cloudinary.api.key' => null,
            'cloudinary.api.secret' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cloudinary configuration is incomplete');

        new CloudinaryMediaUploader();
    }
}
