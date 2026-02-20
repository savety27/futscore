<?php

use PHPUnit\Framework\TestCase;

final class AddServiceTest extends TestCase
{
    public function testValidateKkUploadRequiresFile(): void
    {
        $error = playerAddValidateKkUpload([]);
        $this->assertSame('File Kartu Keluarga (KK) wajib diupload!', $error);
    }

    public function testValidateKkUploadAcceptsSuccessfulUpload(): void
    {
        $error = playerAddValidateKkUpload([
            'kk_file' => ['error' => UPLOAD_ERR_OK],
        ]);

        $this->assertNull($error);
    }

    public function testUploadOptionalImageReturnsEmptyWhenMissingUpload(): void
    {
        $filename = playerAddUploadOptionalImage([], 'photo_file', 'player_', __DIR__ . '/fixtures/');
        $this->assertSame('', $filename);
    }

    public function testUploadRequiredImageThrowsWhenMissingUpload(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File Kartu Keluarga (KK) wajib diupload!');

        playerAddUploadRequiredImage([], 'kk_file', 'kk_', __DIR__ . '/fixtures/');
    }

    public function testUploadImageRejectsInvalidType(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Format file tidak didukung. Harap upload file gambar (JPEG, PNG, GIF).');

        playerAddUploadImage([
            'name' => 'avatar.txt',
            'type' => 'text/plain',
            'size' => 100,
            'tmp_name' => 'php-temp-file',
        ], __DIR__ . '/fixtures/', 'player_');
    }

    public function testUploadImageRejectsOversizedFile(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Ukuran file terlalu besar. Maksimal 5MB.');

        playerAddUploadImage([
            'name' => 'avatar.jpg',
            'type' => 'image/jpeg',
            'size' => 6 * 1024 * 1024,
            'tmp_name' => 'php-temp-file',
        ], __DIR__ . '/fixtures/', 'player_');
    }
}
