<?php

use PHPUnit\Framework\TestCase;

final class EditServiceTest extends TestCase
{
    public function testHandlePhotoUploadKeepsExistingWhenNoUpload(): void
    {
        $resolved = playerEditHandlePhotoUpload([], 'existing-photo.jpg', __DIR__ . '/fixtures/');
        $this->assertSame('existing-photo.jpg', $resolved);
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
}
