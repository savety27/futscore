<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PerangkatSaveServiceTest extends TestCase
{
    #[DataProvider('duplicateErrorProvider')]
    public function testMapSaveErrorForDuplicateKeys(array $errorInfo, string $message, string $expected): void
    {
        $e = new PDOException($message);
        $e->errorInfo = $errorInfo;

        $mapped = perangkatMapSaveError($e);

        $this->assertSame($expected, $mapped);
    }

    public function testMapSaveErrorForNonDuplicateReturnsGenericMessage(): void
    {
        $e = new PDOException('Some SQL error');
        $e->errorInfo = ['HY000', 1234, 'Some SQL error'];

        $mapped = perangkatMapSaveError($e);

        $this->assertSame('Data gagal disimpan. Silakan periksa input lalu coba lagi.', $mapped);
    }

    public static function duplicateErrorProvider(): array
    {
        return [
            [
                ['23000', 1062, "Duplicate entry for key 'uq_perangkat_no_ktp'"],
                "Duplicate entry for key 'uq_perangkat_no_ktp'",
                'No. KTP sudah terdaftar',
            ],
            [
                ['23000', 1062, "Duplicate entry for key 'uq_nik_registry_nik'"],
                "Duplicate entry for key 'uq_nik_registry_nik'",
                'No. KTP sudah terdaftar sebagai pemain.',
            ],
            [
                ['23000', 1062, 'Duplicate entry'],
                'Duplicate entry',
                'Data duplikat terdeteksi. Periksa kembali input Anda.',
            ],
        ];
    }
}
