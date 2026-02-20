<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditHelpersTest extends TestCase
{
    public function testCollectInputTrimsAndCastsValues(): void
    {
        $input = playerEditCollectInput([
            'name' => '  Budi  ',
            'birth_place' => '  Jakarta ',
            'sport_type' => '  Futsal  ',
            'position_detail' => '  Winger Kiri  ',
            'status' => 'on',
            'dribbling' => '7',
            'control' => '8',
            'team_id' => '12',
            'jersey_number' => '10',
        ]);

        $this->assertSame('Budi', $input['name']);
        $this->assertSame('Jakarta', $input['birth_place']);
        $this->assertSame('Futsal', $input['sport_type']);
        $this->assertSame('Winger Kiri', $input['position_detail']);
        $this->assertSame('active', $input['status']);
        $this->assertSame(7, $input['dribbling']);
        $this->assertSame(8, $input['control']);
        $this->assertSame('12', $input['team_id']);
        $this->assertSame('10', $input['jersey_number']);
    }

    public function testCollectInputUsesDefaultsForMissingValues(): void
    {
        $input = playerEditCollectInput([]);

        $this->assertSame('', $input['position_detail']);
        $this->assertSame('inactive', $input['status']);
        $this->assertSame('Indonesia', $input['nationality']);
        $this->assertSame('Indonesia', $input['country']);
        $this->assertNull($input['team_id']);
        $this->assertNull($input['jersey_number']);
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

    #[DataProvider('invalidNikProvider')]
    public function testValidateInputRejectsInvalidNik(string $nik): void
    {
        $input = $this->validInput();
        $input['nik'] = $nik;

        $error = playerEditValidateInput($input);

        $this->assertSame('NIK harus terdiri dari tepat 16 digit angka!', $error);
    }

    public function testValidateInputRejectsTooLongPositionDetail(): void
    {
        $input = $this->validInput();
        $input['position_detail'] = str_repeat('a', 101);

        $error = playerEditValidateInput($input);

        $this->assertSame('Detail posisi maksimal 100 karakter!', $error);
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
        $params = playerEditBuildUpdateParams($input, [
            'photo' => 'player.jpg',
            'ktp_image' => 'ktp.jpg',
            'kk_image' => 'kk.jpg',
            'birth_cert_image' => 'birth-cert.jpg',
            'diploma_image' => 'diploma.jpg',
        ], 42);

        $this->assertSame(42, $params[':id']);
        $this->assertSame('12', $params[':team_id']);
        $this->assertSame('Budi', $params[':name']);
        $this->assertSame('L', $params[':gender']);
        $this->assertSame('Futsal U-16', $params[':sport_type']);
        $this->assertSame('Second Striker', $params[':position_detail']);
        $this->assertSame('inactive', $params[':status']);
        $this->assertSame('player.jpg', $params[':photo']);
        $this->assertSame('ktp.jpg', $params[':ktp_image']);
        $this->assertSame('kk.jpg', $params[':kk_image']);
        $this->assertSame('birth-cert.jpg', $params[':birth_cert_image']);
        $this->assertSame('diploma.jpg', $params[':diploma_image']);
    }

    public function testBuildUpdateParamsConvertsOptionalFieldsAndDefaults(): void
    {
        $input = $this->validInput();
        $input['height'] = '';
        $input['weight'] = '';
        $input['nisn'] = '';
        $input['email'] = '';
        $input['phone'] = '';
        $input['nationality'] = '';
        $input['street'] = '';
        $input['city'] = '';
        $input['province'] = '';
        $input['postal_code'] = '';
        $input['country'] = '';
        $input['position_detail'] = '';

        $params = playerEditBuildUpdateParams($input, [], 42);

        $this->assertSame(0, $params[':height']);
        $this->assertSame(0, $params[':weight']);
        $this->assertNull($params[':nisn']);
        $this->assertNull($params[':email']);
        $this->assertNull($params[':phone']);
        $this->assertNull($params[':street']);
        $this->assertNull($params[':city']);
        $this->assertNull($params[':province']);
        $this->assertNull($params[':postal_code']);
        $this->assertNull($params[':position_detail']);
        $this->assertSame('Indonesia', $params[':nationality']);
        $this->assertSame('Indonesia', $params[':country']);
    }

    public function testBuildUpdateParamsUsesNullForFilesWhenNotProvided(): void
    {
        $params = playerEditBuildUpdateParams($this->validInput(), [], 42);

        $this->assertNull($params[':photo']);
        $this->assertNull($params[':ktp_image']);
        $this->assertNull($params[':kk_image']);
        $this->assertNull($params[':birth_cert_image']);
        $this->assertNull($params[':diploma_image']);
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

    private function validInput(): array
    {
        return [
            'name' => 'Budi',
            'birth_place' => 'Jakarta',
            'birth_date' => '2010-01-01',
            'sport_type' => 'Futsal U-16',
            'gender' => 'Laki-laki',
            'nik' => '1234567890123456',
            'nisn' => '1234567890',
            'height' => '170',
            'weight' => '60',
            'email' => 'budi@example.com',
            'phone' => '08123456789',
            'nationality' => 'Indonesia',
            'street' => 'Jalan Mawar',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '12345',
            'country' => 'Indonesia',
            'team_id' => '12',
            'jersey_number' => '10',
            'dominant_foot' => 'Kanan',
            'position' => 'Forward',
            'position_detail' => 'Second Striker',
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
