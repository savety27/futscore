<?php
require_once __DIR__ . '/includes/auth_guard.php';

function perangkatCreateSave(
    PDO $conn,
    array $formData,
    ?string $photoPath,
    string $ktpPhotoPath,
    array $licenses = []
): int {
    $startedTransaction = false;
    if (!$conn->inTransaction()) {
        $conn->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $stmt = $conn->prepare(
            "INSERT INTO perangkat (
                name, no_ktp, birth_place, age, gender, email, phone,
                address, city, province, postal_code, country, photo, ktp_photo, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $formData['name'],
            $formData['no_ktp'],
            $formData['birth_place'] !== '' ? $formData['birth_place'] : null,
            $formData['date_of_birth'],
            $formData['gender'] !== '' ? $formData['gender'] : null,
            $formData['email'] !== '' ? $formData['email'] : null,
            $formData['phone'] !== '' ? $formData['phone'] : null,
            $formData['address'] !== '' ? $formData['address'] : null,
            $formData['city'] !== '' ? $formData['city'] : null,
            $formData['province'] !== '' ? $formData['province'] : null,
            $formData['postal_code'] !== '' ? $formData['postal_code'] : null,
            $formData['country'] !== '' ? $formData['country'] : null,
            $photoPath,
            $ktpPhotoPath,
            $formData['is_active'],
        ]);

        $perangkatId = (int)$conn->lastInsertId();
        if (!empty($licenses)) {
            $stmt = $conn->prepare(
                "INSERT INTO perangkat_licenses (perangkat_id, license_name, license_file, issuing_authority, issue_date, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            foreach ($licenses as $license) {
                $stmt->execute([
                    $perangkatId,
                    trim((string)($license['name'] ?? '')) !== '' ? trim((string)$license['name']) : 'Lisensi',
                    (string)($license['file'] ?? ''),
                    trim((string)($license['authority'] ?? '')) !== '' ? trim((string)$license['authority']) : null,
                    trim((string)($license['date'] ?? '')) !== '' ? trim((string)$license['date']) : null,
                ]);
            }
        }

        if ($startedTransaction) {
            $conn->commit();
        }

        return $perangkatId;
    } catch (PDOException $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        throw new Exception(perangkatMapSaveError($e), 0, $e);
    } catch (Exception $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        throw $e;
    }
}

function perangkatUpdateSave(
    PDO $conn,
    int $staffId,
    array $formData,
    ?string $photoPath,
    string $ktpPhotoPath,
    array $updatedExistingLicenses = [],
    array $newLicenses = [],
    array $removedLicenseIds = []
): array {
    $startedTransaction = false;
    if (!$conn->inTransaction()) {
        $conn->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $stmt = $conn->prepare(
            "UPDATE perangkat SET
                name = ?, no_ktp = ?, birth_place = ?, age = ?, gender = ?, email = ?, phone = ?,
                address = ?, city = ?, province = ?, postal_code = ?, country = ?, photo = ?, ktp_photo = ?, is_active = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([
            $formData['name'],
            $formData['no_ktp'],
            $formData['birth_place'] !== '' ? $formData['birth_place'] : null,
            $formData['date_of_birth'],
            $formData['gender'] !== '' ? $formData['gender'] : null,
            $formData['email'] !== '' ? $formData['email'] : null,
            $formData['phone'] !== '' ? $formData['phone'] : null,
            $formData['address'] !== '' ? $formData['address'] : null,
            $formData['city'] !== '' ? $formData['city'] : null,
            $formData['province'] !== '' ? $formData['province'] : null,
            $formData['postal_code'] !== '' ? $formData['postal_code'] : null,
            $formData['country'] !== '' ? $formData['country'] : null,
            $photoPath,
            $ktpPhotoPath,
            $formData['is_active'],
            $staffId,
        ]);

        $licenseFilesToDelete = [];
        if (!empty($removedLicenseIds)) {
            $placeholders = implode(',', array_fill(0, count($removedLicenseIds), '?'));
            $params = array_merge([$staffId], $removedLicenseIds);

            $stmt = $conn->prepare(
                "SELECT id, license_file
                 FROM perangkat_licenses
                 WHERE perangkat_id = ? AND id IN ($placeholders)"
            );
            $stmt->execute($params);
            $licensesToDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($licensesToDelete)) {
                foreach ($licensesToDelete as $licenseRow) {
                    $fileName = basename((string)($licenseRow['license_file'] ?? ''));
                    if ($fileName !== '') {
                        $licenseFilesToDelete[] = $fileName;
                    }
                }

                $stmt = $conn->prepare(
                    "DELETE FROM perangkat_licenses
                     WHERE perangkat_id = ? AND id IN ($placeholders)"
                );
                $stmt->execute($params);
            }
        }

        if (!empty($updatedExistingLicenses)) {
            $stmt = $conn->prepare(
                "UPDATE perangkat_licenses
                 SET license_name = ?, issuing_authority = ?, issue_date = ?
                 WHERE perangkat_id = ? AND id = ?"
            );
            foreach ($updatedExistingLicenses as $licenseId => $licenseData) {
                $stmt->execute([
                    trim((string)($licenseData['name'] ?? '')) !== '' ? trim((string)$licenseData['name']) : 'Lisensi',
                    trim((string)($licenseData['authority'] ?? '')) !== '' ? trim((string)$licenseData['authority']) : null,
                    trim((string)($licenseData['date'] ?? '')) !== '' ? trim((string)$licenseData['date']) : null,
                    $staffId,
                    (int)$licenseId,
                ]);
            }
        }

        if (!empty($newLicenses)) {
            $stmt = $conn->prepare(
                "INSERT INTO perangkat_licenses (perangkat_id, license_name, license_file, issuing_authority, issue_date, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            foreach ($newLicenses as $license) {
                $stmt->execute([
                    $staffId,
                    trim((string)($license['name'] ?? '')) !== '' ? trim((string)$license['name']) : 'Lisensi',
                    (string)($license['file'] ?? ''),
                    trim((string)($license['authority'] ?? '')) !== '' ? trim((string)$license['authority']) : null,
                    trim((string)($license['date'] ?? '')) !== '' ? trim((string)$license['date']) : null,
                ]);
            }
        }

        if ($startedTransaction) {
            $conn->commit();
        }

        return [
            'license_files_to_delete' => array_values(array_unique($licenseFilesToDelete)),
        ];
    } catch (PDOException $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        throw new Exception(perangkatMapSaveError($e), 0, $e);
    } catch (Exception $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }

        throw $e;
    }
}

function perangkatMapSaveError(PDOException $e): string
{
    $driverCode = (int)($e->errorInfo[1] ?? 0);
    $messageLc = strtolower((string)($e->errorInfo[2] ?? '') . ' ' . $e->getMessage());

    if ($driverCode === 1062) {
        if (strpos($messageLc, 'uq_nik_registry_nik') !== false) {
            return 'No. KTP sudah terdaftar sebagai pemain.';
        }

        if (
            strpos($messageLc, 'uq_perangkat_no_ktp') !== false
            || strpos($messageLc, 'no_ktp') !== false
        ) {
            return 'No. KTP sudah terdaftar';
        }

        return 'Data duplikat terdeteksi. Periksa kembali input Anda.';
    }

    return 'Data gagal disimpan. Silakan periksa input lalu coba lagi.';
}

function perangkatDeleteUploadedLicenseFiles(array $licenses, string $uploadDir): void
{
    foreach ($licenses as $license) {
        $fileName = basename((string)($license['file'] ?? ''));
        if ($fileName === '') {
            continue;
        }

        $filePath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}
