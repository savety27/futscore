<?php

function playerFileHasSuccessfulUpload(array $files, string $fieldName): bool
{
    return isset($files[$fieldName]) && (($files[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK);
}

function playerFileEnsureUploadDirectory(string $uploadDir): void
{
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
}

function playerFileDeleteIfExists(string $uploadDir, ?string $filename): void
{
    if ($filename === null || $filename === '') {
        return;
    }

    $filePath = $uploadDir . $filename;
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

function playerFileValidateImageUpload(array $file): ?string
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $maxSize = 5 * 1024 * 1024;

    $type = (string)($file['type'] ?? '');
    $size = (int)($file['size'] ?? 0);

    if (!in_array($type, $allowedTypes, true)) {
        return 'Format file tidak didukung. Harap upload file gambar (JPEG, PNG, GIF).';
    }

    if ($size > $maxSize) {
        return 'Ukuran file terlalu besar. Maksimal 5MB.';
    }

    return null;
}

function playerFileMoveUploaded(array $file, string $uploadDir, string $prefix): ?string
{
    $fileExtension = pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION);
    $newFilename = $prefix . time() . '_' . uniqid();
    if ($fileExtension !== '') {
        $newFilename .= '.' . $fileExtension;
    }

    $targetPath = $uploadDir . $newFilename;
    if (move_uploaded_file((string)($file['tmp_name'] ?? ''), $targetPath)) {
        return $newFilename;
    }

    return null;
}

function playerFileMoveUploadedOrFail(array $file, string $uploadDir, string $prefix): string
{
    $newFilename = playerFileMoveUploaded($file, $uploadDir, $prefix);
    if ($newFilename === null) {
        throw new Exception('Gagal mengupload file.');
    }

    return $newFilename;
}
