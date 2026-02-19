<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditHelpersTest extends TestCase
{
    public function testCollectInputTrimsAndCastsValues(): void
    {
        $input = playerEditCollectInput([
            'name' => '  Budi  ',
            'place_of_birth' => '  Jakarta ',
            'sport' => '  Futsal  ',
            'status' => 'on',
            'dribbling' => '7',
            'control' => '8',
        ]);

        $this->assertSame('Budi', $input['name']);
        $this->assertSame('Jakarta', $input['place_of_birth']);
        $this->assertSame('Futsal', $input['sport']);
        $this->assertSame('active', $input['status']);
        $this->assertSame(7, $input['dribbling']);
        $this->assertSame(8, $input['control']);
    }

    public function testCollectInputUsesDefaultsForMissingValues(): void
    {
        $input = playerEditCollectInput([]);

        $this->assertSame('inactive', $input['status']);
        $this->assertSame(5, $input['dribbling']);
        $this->assertSame(5, $input['technique']);
        $this->assertSame(5, $input['speed']);
        $this->assertSame(5, $input['juggling']);
        $this->assertSame(5, $input['shooting']);
        $this->assertSame(5, $input['setplay_position']);
        $this->assertSame(5, $input['passing']);
        $this->assertSame(5, $input['control']);
    }

    public function testValidateInputReturnsNullForValidPayload(): void
    {
        $error = playerEditValidateInput($this->validInput());
        $this->assertNull($error);
    }

    #[DataProvider('requiredFieldProvider')]
    public function testValidateInputRejectsMissingRequiredField(string $field): void
    {
        $input = $this->validInput();
        $input[$field] = '';

        $error = playerEditValidateInput($input);

        $this->assertSame('Semua field yang wajib harus diisi!', $error);
    }

    public function testValidateInputRejectsEmptyNik(): void
    {
        $input = $this->validInput();
        $input['nik'] = '';

        $error = playerEditValidateInput($input);

        $this->assertSame('NIK harus terdiri dari tepat 16 digit angka!', $error);
    }

    #[DataProvider('invalidNikProvider')]
    public function testValidateInputRejectsInvalidNik(string $nik): void
    {
        $input = $this->validInput();
        $input['nik'] = $nik;

        $error = playerEditValidateInput($input);

        $this->assertSame('NIK harus terdiri dari tepat 16 digit angka!', $error);
    }

    #[DataProvider('invalidNisnProvider')]
    public function testValidateInputRejectsInvalidNisn(string $nisn): void
    {
        $input = $this->validInput();
        $input['nisn'] = $nisn;

        $error = playerEditValidateInput($input);

        $this->assertSame('NISN harus terdiri dari tepat 10 digit angka!', $error);
    }

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

    public function testBuildUpdateParamsMapsRequiredAndDerivedValues(): void
    {
        $input = $this->validInput();
        $params = playerEditBuildUpdateParams($input, [], 42);

        $this->assertSame(42, $params[':id']);
        $this->assertSame('12', $params[':team_id']);
        $this->assertSame('Budi', $params[':name']);
        $this->assertSame('L', $params[':gender']);
        $this->assertSame('Forward', $params[':position_detail']);
        $this->assertSame('inactive', $params[':status']);
    }

    public function testBuildUpdateParamsConvertsEmptyOptionalFields(): void
    {
        $input = $this->validInput();
        $input['height'] = '';
        $input['weight'] = '';
        $input['nisn'] = '';
        $input['email'] = '';
        $input['phone'] = '';
        $input['nationality'] = '';
        $input['address'] = '';
        $input['city'] = '';
        $input['province'] = '';
        $input['postal_code'] = '';
        $input['country'] = '';

        $params = playerEditBuildUpdateParams($input, [], 42);

        $this->assertNull($params[':height']);
        $this->assertNull($params[':weight']);
        $this->assertNull($params[':nisn']);
        $this->assertNull($params[':email']);
        $this->assertNull($params[':phone']);
        $this->assertNull($params[':street']);
        $this->assertNull($params[':city']);
        $this->assertNull($params[':province']);
        $this->assertNull($params[':postal_code']);
        $this->assertSame('Indonesia', $params[':nationality']);
        $this->assertSame('Indonesia', $params[':country']);
    }

    public function testBuildUpdateParamsUsesUploadedFileDefaultsAndOverrides(): void
    {
        $input = $this->validInput();

        $defaultParams = playerEditBuildUpdateParams($input, [], 42);
        $this->assertNull($defaultParams[':photo']);
        $this->assertNull($defaultParams[':ktp_image']);
        $this->assertNull($defaultParams[':kk_image']);
        $this->assertNull($defaultParams[':birth_cert_image']);
        $this->assertNull($defaultParams[':diploma_image']);

        $params = playerEditBuildUpdateParams($input, [
            'photo_file' => 'photo.jpg',
            'ktp_file' => 'ktp.jpg',
            'kk_file' => 'kk.jpg',
            'akte_file' => 'akte.jpg',
            'ijazah_file' => 'ijazah.jpg',
        ], 42);

        $this->assertSame('photo.jpg', $params[':photo']);
        $this->assertSame('ktp.jpg', $params[':ktp_image']);
        $this->assertSame('kk.jpg', $params[':kk_image']);
        $this->assertSame('akte.jpg', $params[':birth_cert_image']);
        $this->assertSame('ijazah.jpg', $params[':diploma_image']);
    }

    public function testUpdateSqlPlaceholdersStayInSyncWithBuildUpdateParams(): void
    {
        $sql = playerEditUpdateSql();
        $params = playerEditBuildUpdateParams($this->validInput(), [], 42);

        preg_match_all('/=\s*(:[a-z_]+)/', $sql, $matches);
        $sqlPlaceholders = array_values(array_unique($matches[1]));
        $paramKeys = array_keys($params);

        sort($sqlPlaceholders);
        sort($paramKeys);

        $this->assertSame($paramKeys, $sqlPlaceholders);
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

    public static function requiredFieldProvider(): array
    {
        return [
            ['name'],
            ['place_of_birth'],
            ['date_of_birth'],
            ['sport'],
            ['gender'],
            ['team_id'],
            ['jersey_number'],
            ['dominant_foot'],
            ['position'],
        ];
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

    public static function invalidNisnProvider(): array
    {
        return [
            ['123'],
            ['123456789'],
            ['12345678901'],
            ['12345678AB'],
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

    private function validInput(): array
    {
        return [
            'name' => 'Budi',
            'place_of_birth' => 'Jakarta',
            'date_of_birth' => '2010-01-01',
            'sport' => 'Futsal U-16',
            'gender' => 'Laki-laki',
            'nik' => '1234567890123456',
            'nisn' => '1234567890',
            'height' => '170',
            'weight' => '60',
            'email' => 'budi@example.com',
            'phone' => '08123456789',
            'nationality' => 'Indonesia',
            'address' => 'Jalan Mawar',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '12345',
            'country' => 'Indonesia',
            'team_id' => '12',
            'jersey_number' => '10',
            'dominant_foot' => 'Kanan',
            'position' => 'Forward',
            'status' => 'inactive',
            'dribbling' => 6,
            'technique' => 7,
            'speed' => 8,
            'juggling' => 6,
            'shooting' => 7,
            'setplay_position' => 5,
            'passing' => 7,
            'control' => 8,
        ];
    }
}
