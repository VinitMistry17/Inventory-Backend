<?php

namespace App\Contracts;

interface MediaUploader
{
    public function uploadFile($file, string $folder): array;

    public function delete(string $publicId): void;
}
