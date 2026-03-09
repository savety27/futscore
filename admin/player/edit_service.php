<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/edit_helpers.php';
require_once __DIR__ . '/file_upload_helpers.php';

function playerEditUpdatePlayer(PDO $conn, int $playerId, array $post, array $files, array $existingPlayer): void
{
    $startedTransaction = false;
    if (!$conn->inTransaction()) {
        $conn->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $input = playerEditCollectInput($post);
        $validationError = playerEditValidateInput($input);
        if ($validationError !== null) {
            throw new Exception($validationError);
        }

        $kkHasExistingFile = !empty($existingPlayer['kk_image']);
        $kkNewFileUploaded = isset($files['kk_image']) && $files['kk_image']['error'] === UPLOAD_ERR_OK;
        $kkDeleteChecked = isset($post['delete_kk_image']) && $post['delete_kk_image'] == '1';
        $kkError = playerEditValidateKkImage($kkHasExistingFile, $kkNewFileUploaded, $kkDeleteChecked);
        if ($kkError !== null) {
            throw new Exception($kkError);
        }

        $uploadDir = __DIR__ . '/../../images/players/';
        playerEditEnsureUploadDirectory($uploadDir);

        $resolvedFiles = [
            'photo' => playerEditHandlePhotoUpload($post, $files, $existingPlayer['photo'] ?? null, $uploadDir),
            'ktp_image' => playerEditHandleDocumentUpload($post, $files, 'ktp_image', 'ktp', $existingPlayer['ktp_image'] ?? null, $uploadDir),
            'kk_image' => playerEditHandleDocumentUpload($post, $files, 'kk_image', 'kk', $existingPlayer['kk_image'] ?? null, $uploadDir),
            'birth_cert_image' => playerEditHandleDocumentUpload($post, $files, 'birth_cert_image', 'akte', $existingPlayer['birth_cert_image'] ?? null, $uploadDir),
            'diploma_image' => playerEditHandleDocumentUpload($post, $files, 'diploma_image', 'ijazah', $existingPlayer['diploma_image'] ?? null, $uploadDir),
        ];
        $filesToDeleteAfterCommit = playerEditCollectObsoleteFiles($existingPlayer, $resolvedFiles);

        $stmt = $conn->prepare(playerEditUpdateSql());
        $stmt->execute(playerEditBuildUpdateParams($input, $resolvedFiles, $playerId));

        if ($startedTransaction) {
            $conn->commit();
        }

        playerFileDeleteManyIfExists($uploadDir, $filesToDeleteAfterCommit);
    } catch (PDOException $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        playerFileDeleteManyIfExists($uploadDir ?? (__DIR__ . '/../../images/players/'), playerEditCollectNewUploads($existingPlayer, $resolvedFiles ?? []));
        throw new Exception(playerEditMapUpdateError($e), 0, $e);
    } catch (Exception $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        playerFileDeleteManyIfExists($uploadDir ?? (__DIR__ . '/../../images/players/'), playerEditCollectNewUploads($existingPlayer, $resolvedFiles ?? []));
        throw $e;
    }
}

function playerEditCollectObsoleteFiles(array $existingPlayer, array $resolvedFiles): array
{
    $fields = ['photo', 'ktp_image', 'kk_image', 'birth_cert_image', 'diploma_image'];
    $obsoleteFiles = [];

    foreach ($fields as $field) {
        $existingValue = trim((string)($existingPlayer[$field] ?? ''));
        $resolvedValue = trim((string)($resolvedFiles[$field] ?? ''));

        if ($existingValue !== '' && $resolvedValue !== $existingValue) {
            $obsoleteFiles[] = $existingValue;
        }
    }

    return array_values(array_unique($obsoleteFiles));
}

function playerEditCollectNewUploads(array $existingPlayer, array $resolvedFiles): array
{
    $fields = ['photo', 'ktp_image', 'kk_image', 'birth_cert_image', 'diploma_image'];
    $newUploads = [];

    foreach ($fields as $field) {
        $existingValue = trim((string)($existingPlayer[$field] ?? ''));
        $resolvedValue = trim((string)($resolvedFiles[$field] ?? ''));

        if ($resolvedValue !== '' && $resolvedValue !== $existingValue) {
            $newUploads[] = $resolvedValue;
        }
    }

    return array_values(array_unique($newUploads));
}

function playerEditEnsureUploadDirectory(string $uploadDir): void
{
    playerFileEnsureUploadDirectory($uploadDir);
}

function playerEditHandlePhotoUpload(array $post, array $files, ?string $existingPhoto, string $uploadDir): ?string
{
    if (isset($files['photo']) && $files['photo']['error'] === UPLOAD_ERR_OK) {
        $newFilename = playerFileMoveUploaded($files['photo'], $uploadDir, 'player_');
        if ($newFilename !== null) {
            return $newFilename;
        }

        return $existingPhoto;
    }

    if (isset($post['delete_photo']) && $post['delete_photo'] === '1') {
        return null;
    }

    return $existingPhoto;
}

function playerEditHandleDocumentUpload(
    array $post,
    array $files,
    string $fieldName,
    string $type,
    ?string $existingFile,
    string $uploadDir
): ?string {
    if (isset($files[$fieldName]) && $files[$fieldName]['error'] === UPLOAD_ERR_OK) {
        $newFilename = playerFileMoveUploaded($files[$fieldName], $uploadDir, $type . '_');
        if ($newFilename !== null) {
            return $newFilename;
        }
    }

    if (isset($post['delete_' . $fieldName])) {
        return null;
    }

    return $existingFile;
}
