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

    public function testHandlePhotoUploadRejectsInvalidType(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Format file tidak didukung. Harap upload file gambar (JPEG, PNG, GIF).');

        playerEditHandlePhotoUpload([], [
            'photo' => [
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => 'php-temp-file',
                'name' => 'new-photo.txt',
                'type' => 'text/plain',
                'size' => 100,
            ],
        ], 'existing-photo.jpg', __DIR__ . '/fixtures/');
    }

    public function testHandlePhotoUploadValidatesUploadedFileBeforeDeleteFlag(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Format file tidak didukung. Harap upload file gambar (JPEG, PNG, GIF).');

        playerEditHandlePhotoUpload(['delete_photo' => '1'], [
            'photo' => [
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => 'php-temp-file',
                'name' => 'new-photo.txt',
                'type' => 'text/plain',
                'size' => 100,
            ],
        ], 'existing-photo.jpg', __DIR__ . '/fixtures/');
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

    public function testHandleDocumentUploadRejectsOversizedFile(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Ukuran file terlalu besar. Maksimal 5MB.');

        playerEditHandleDocumentUpload([], [
            'kk_image' => [
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => 'php-temp-file',
                'name' => 'new-kk.jpg',
                'type' => 'image/jpeg',
                'size' => 6 * 1024 * 1024,
            ],
        ], 'kk_image', 'kk', 'existing-kk.jpg', __DIR__ . '/fixtures/');
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

    public function testUpdatePlayerRollsBackTransactionWhenUploadedFileFailsValidation(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            playerEditUpdatePlayer(
                $pdo,
                1,
                $this->validEditPost(),
                [
                    'photo' => [
                        'error' => UPLOAD_ERR_OK,
                        'tmp_name' => 'php-temp-file',
                        'name' => 'new-photo.txt',
                        'type' => 'text/plain',
                        'size' => 100,
                    ],
                ],
                [
                    'photo' => 'existing-photo.jpg',
                    'kk_image' => 'existing-kk.jpg',
                ]
            );

            $this->fail('Expected invalid upload to abort the update.');
        } catch (Exception $e) {
            $this->assertSame('Format file tidak didukung. Harap upload file gambar (JPEG, PNG, GIF).', $e->getMessage());
        }

        $this->assertFalse($pdo->inTransaction());
    }

    private function validEditPost(): array
    {
        return [
            'name' => 'ITEST Edit Player',
            'birth_place' => 'Jakarta',
            'birth_date' => '2010-01-01',
            'sport_type' => 'Futsal U-16',
            'gender' => 'Laki-laki',
            'nik' => '1234567890123456',
            'position_detail' => 'Pivot',
        ];
    }
}
