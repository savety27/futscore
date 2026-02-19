<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditHelpersTest extends TestCase
{
    public function testValidateNikReturnsNullForValidNik(): void
    {
        $this->assertNull(playerEditValidateNik('1234567890123456'));
    }

    #[DataProvider('invalidNikProvider')]
    public function testValidateNikRejectsInvalidNik(string $nik): void
    {
        $error = playerEditValidateNik($nik);

        $this->assertSame('NIK harus terdiri dari tepat 16 digit angka!', $error);
    }

    #[DataProvider('kkValidationProvider')]
    public function testValidateKkImageRules(
        bool $hasExistingFile,
        bool $newFileUploaded,
        bool $deleteChecked,
        ?string $expectedError
    ): void {
        $error = playerEditValidateKkImage($hasExistingFile, $newFileUploaded, $deleteChecked);

        $this->assertSame($expectedError, $error);
    }

    public function testMapGenderForDb(): void
    {
        $this->assertSame('L', playerEditMapGenderForDb('Laki-laki'));
        $this->assertSame('P', playerEditMapGenderForDb('Perempuan'));
        $this->assertSame('', playerEditMapGenderForDb(''));
        $this->assertSame('', playerEditMapGenderForDb('Other'));
    }

    #[DataProvider('duplicateErrorProvider')]
    public function testMapUpdateErrorForDuplicateKeys(array $errorInfo, string $message, string $expected): void
    {
        $e = new PDOException($message);
        $e->errorInfo = $errorInfo;

        $mapped = playerEditMapUpdateError($e);

        $this->assertSame($expected, $mapped);
    }

    public function testMapUpdateErrorForNonDuplicateKeepsRawMessage(): void
    {
        $e = new PDOException('Some SQL error');
        $e->errorInfo = ['HY000', 1234, 'Some SQL error'];

        $mapped = playerEditMapUpdateError($e);

        $this->assertSame('Error: Some SQL error', $mapped);
    }

    public static function invalidNikProvider(): array
    {
        return [
            ['123'],
            ['123456789012345'],
            ['12345678901234567'],
            ['12345678901234AB'],
        ];
    }

    public static function kkValidationProvider(): array
    {
        return [
            [true, false, false, null],
            [false, true, false, null],
            [true, true, true, null],
            [true, true, false, null],
            [true, false, true, 'File Kartu Keluarga (KK) wajib diupload!'],
            [false, false, false, 'File Kartu Keluarga (KK) wajib diupload!'],
            [false, false, true, 'File Kartu Keluarga (KK) wajib diupload!'],
        ];
    }

    public static function duplicateErrorProvider(): array
    {
        return [
            [
                ['23000', 1062, "Duplicate entry for key 'uq_players_name'"],
                "Duplicate entry for key 'uq_players_name'",
                'Error: Nama pemain sudah terdaftar. Gunakan nama yang berbeda.',
            ],
            [
                ['23000', 1062, "Duplicate entry for key 'players_nik_unique'"],
                "Duplicate entry for key 'players_nik_unique'",
                'Error: NIK sudah terdaftar. Gunakan NIK yang berbeda.',
            ],
            [
                ['23000', 1062, 'Duplicate entry'],
                'Duplicate entry',
                'Error: Data duplikat terdeteksi. Periksa kembali input Anda.',
            ],
        ];
    }
}
