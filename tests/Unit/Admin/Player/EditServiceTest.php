<?php

use PHPUnit\Framework\TestCase;

final class EditServiceTest extends TestCase
{
    public function testHandlePhotoUploadKeepsExistingWhenNoUpload(): void
    {
        $resolved = playerEditHandlePhotoUpload([], [], 'existing-photo.jpg', __DIR__ . '/fixtures/');
        $this->assertSame('existing-photo.jpg', $resolved);
    }

    public function testHandlePhotoUploadDeletesWhenDeleteFlagSetWithoutUpload(): void
    {
        $resolved = playerEditHandlePhotoUpload(['delete_photo' => '1'], [], 'existing-photo.jpg', __DIR__ . '/fixtures/');
        $this->assertNull($resolved);
    }

    public function testHandlePhotoUploadUploadWinsOverDeleteFlag(): void
    {
        $uploadDir = __DIR__ . '/fixtures/';
        $tempFile = tempnam(sys_get_temp_dir(), 'upload-photo-');
        $this->assertNotFalse($tempFile);
        $this->assertNotFalse(file_put_contents($tempFile, 'fake-image-content'));

        try {
            $files = [
                'photo' => [
                    'error' => UPLOAD_ERR_OK,
                    'tmp_name' => $tempFile,
                    'name' => 'new-photo.jpg',
                ],
            ];

            $resolved = playerEditHandlePhotoUpload(['delete_photo' => '1'], $files, 'existing-photo.jpg', $uploadDir);
            $this->assertSame('existing-photo.jpg', $resolved);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testHandleDocumentUploadKeepsExistingWhenNoUploadAndNoDelete(): void
    {
        $resolved = playerEditHandleDocumentUpload([], [], 'kk_image', 'kk', 'existing-kk.jpg', __DIR__ . '/fixtures/');
        $this->assertSame('existing-kk.jpg', $resolved);
    }

    public function testHandleDocumentUploadDeletesWhenDeleteFlagSet(): void
    {
        $resolved = playerEditHandleDocumentUpload(['delete_kk_image' => '1'], [], 'kk_image', 'kk', 'existing-kk.jpg', __DIR__ . '/fixtures/');
        $this->assertNull($resolved);
    }

    public function testCollectObsoleteFilesReturnsExistingFilesThatShouldBeRemovedAfterCommit(): void
    {
        $obsolete = playerEditCollectObsoleteFiles(
            [
                'photo' => 'old-photo.jpg',
                'kk_image' => 'old-kk.jpg',
            ],
            [
                'photo' => 'new-photo.jpg',
                'kk_image' => null,
            ]
        );

        $this->assertSame(['old-photo.jpg', 'old-kk.jpg'], $obsolete);
    }

    public function testCollectNewUploadsReturnsOnlyFilesCreatedDuringFailedUpdate(): void
    {
        $newUploads = playerEditCollectNewUploads(
            [
                'photo' => 'old-photo.jpg',
                'kk_image' => 'old-kk.jpg',
            ],
            [
                'photo' => 'new-photo.jpg',
                'kk_image' => 'old-kk.jpg',
                'ktp_image' => 'new-ktp.jpg',
            ]
        );

        $this->assertSame(['new-photo.jpg', 'new-ktp.jpg'], $newUploads);
    }
}
