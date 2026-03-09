<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/add_helpers.php';
require_once __DIR__ . '/file_upload_helpers.php';

function playerAddCreatePlayer(PDO $conn, array $post, array $files, ?callable $uploadResolver = null): int
{
    $input = playerAddCollectInput($post);
    $validationError = playerAddValidateInput($input);
    if ($validationError !== null) {
        throw new Exception($validationError);
    }

    $kkError = playerAddValidateKkUpload($files);
    if ($kkError !== null) {
        throw new Exception($kkError);
    }

    $uploadDir = __DIR__ . '/../../images/players/';
    if ($uploadResolver === null) {
        $uploadResolver = 'playerAddResolveUploadedFiles';
    }

    $startedTransaction = false;
    $uploadedFiles = [];
    if (!$conn->inTransaction()) {
        $conn->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $uploadedFiles = $uploadResolver($files, $uploadDir);
        $stmt = $conn->prepare(playerAddInsertSql());
        $baseSlug = playerAddGenerateSlug($input['name']);
        $maxInsertAttempts = 4;

        for ($attempt = 1; $attempt <= $maxInsertAttempts; $attempt++) {
            $slug = $attempt === 1
                ? $baseSlug
                : playerAddBuildCollisionRetrySlug($baseSlug, $attempt);

            try {
                $stmt->execute(playerAddBuildInsertParams($input, $uploadedFiles, $slug));

                $playerId = (int)$conn->lastInsertId();
                if ($startedTransaction) {
                    $conn->commit();
                }

                return $playerId;
            } catch (PDOException $e) {
                if ($attempt < $maxInsertAttempts && playerAddIsDuplicateSlugError($e)) {
                    continue;
                }

                throw $e;
            }
        }
    } catch (PDOException $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        playerAddCleanupUploadedFiles($uploadedFiles, $uploadDir);
        throw new Exception(playerAddMapInsertError($e), 0, $e);
    } catch (Exception $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        playerAddCleanupUploadedFiles($uploadedFiles, $uploadDir);
        throw $e;
    }
}

function playerAddCleanupUploadedFiles(array $uploadedFiles, string $uploadDir): void
{
    $filenames = [];
    foreach ($uploadedFiles as $uploadedFile) {
        if (!is_string($uploadedFile) || $uploadedFile === '') {
            continue;
        }

        $filenames[] = $uploadedFile;
    }

    playerFileDeleteManyIfExists($uploadDir, $filenames);
}

function playerAddValidateKkUpload(array $files): ?string
{
    if (!playerFileHasSuccessfulUpload($files, 'kk_file')) {
        return 'File Kartu Keluarga (KK) wajib diupload!';
    }

    return null;
}

function playerAddResolveUploadedFiles(array $files, string $uploadDir): array
{
    playerFileEnsureUploadDirectory($uploadDir);

    return [
        'photo' => playerAddUploadOptionalImage($files, 'photo_file', 'player_', $uploadDir),
        'ktp_image' => playerAddUploadOptionalImage($files, 'ktp_file', 'ktp_', $uploadDir),
        'kk_image' => playerAddUploadRequiredImage($files, 'kk_file', 'kk_', $uploadDir),
        'birth_cert_image' => playerAddUploadOptionalImage($files, 'akte_file', 'akte_', $uploadDir),
        'diploma_image' => playerAddUploadOptionalImage($files, 'ijazah_file', 'ijazah_', $uploadDir),
    ];
}

function playerAddUploadOptionalImage(array $files, string $fieldName, string $prefix, string $uploadDir): string
{
    if (!playerFileHasSuccessfulUpload($files, $fieldName)) {
        return '';
    }

    return playerAddUploadImage($files[$fieldName], $uploadDir, $prefix);
}

function playerAddUploadRequiredImage(array $files, string $fieldName, string $prefix, string $uploadDir): string
{
    if (!playerFileHasSuccessfulUpload($files, $fieldName)) {
        throw new Exception('File Kartu Keluarga (KK) wajib diupload!');
    }

    return playerAddUploadImage($files[$fieldName], $uploadDir, $prefix);
}

function playerAddUploadImage(array $file, string $uploadDir, string $prefix): string
{
    return playerFileValidateAndMoveUploadedOrFail($file, $uploadDir, $prefix);
}
