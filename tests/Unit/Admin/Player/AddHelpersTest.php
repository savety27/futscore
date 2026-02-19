<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddHelpersTest extends TestCase
{
    public function testCollectInputTrimsAndCastsValues(): void
    {
        $input = playerAddCollectInput([
            'name' => '  Budi  ',
            'place_of_birth' => '  Jakarta ',
            'sport' => '  Futsal  ',
            'position_detail' => '  Winger Kiri  ',
            'status' => 'on',
            'dribbling' => '7',
            'control' => '8'
        ]);

        $this->assertSame('Budi', $input['name']);
        $this->assertSame('Jakarta', $input['place_of_birth']);
        $this->assertSame('Futsal', $input['sport']);
        $this->assertSame('Winger Kiri', $input['position_detail']);
        $this->assertSame('active', $input['status']);
        $this->assertSame(7, $input['dribbling']);
        $this->assertSame(8, $input['control']);
    }

    public function testCollectInputUsesDefaultsForMissingValues(): void
    {
        $input = playerAddCollectInput([]);

        $this->assertSame('', $input['position_detail']);
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
        $error = playerAddValidateInput($this->validInput());
        $this->assertNull($error);
    }

    #[DataProvider('requiredFieldProvider')]
    public function testValidateInputRejectsMissingRequiredField(string $field): void
    {
        $input = $this->validInput();
        $input[$field] = '';

        $error = playerAddValidateInput($input);

        $this->assertSame('Semua field yang wajib harus diisi!', $error);
    }

    #[DataProvider('invalidNikProvider')]
    public function testValidateInputRejectsInvalidNik(string $nik): void
    {
        $input = $this->validInput();
        $input['nik'] = $nik;

        $error = playerAddValidateInput($input);

        $this->assertSame('NIK harus terdiri dari tepat 16 digit angka!', $error);
    }

    #[DataProvider('invalidNisnProvider')]
    public function testValidateInputRejectsInvalidNisn(string $nisn): void
    {
        $input = $this->validInput();
        $input['nisn'] = $nisn;

        $error = playerAddValidateInput($input);

        $this->assertSame('NISN harus terdiri dari tepat 10 digit angka!', $error);
    }

    public function testValidateInputRejectsTooLongPositionDetail(): void
    {
        $input = $this->validInput();
        $input['position_detail'] = str_repeat('a', 101);

        $error = playerAddValidateInput($input);

        $this->assertSame('Detail posisi maksimal 100 karakter!', $error);
    }

    public function testValidateInputRejectsInvalidGender(): void
    {
        $input = $this->validInput();
        $input['gender'] = 'Other';

        $error = playerAddValidateInput($input);

        $this->assertSame('Jenis kelamin tidak valid!', $error);
    }

    public function testMapGenderForDb(): void
    {
        $this->assertSame('L', playerAddMapGenderForDb('Laki-laki'));
        $this->assertSame('P', playerAddMapGenderForDb('Perempuan'));
        $this->assertSame('', playerAddMapGenderForDb(''));
        $this->assertSame('', playerAddMapGenderForDb('Other'));
    }

    public function testGenerateSlugWithProvidedTimestamp(): void
    {
        $slug = playerAddGenerateSlug('  Muhammad Budi!@#  ', 1700000000);

        $this->assertSame('muhammad-budi-1700000000', $slug);
    }

    public function testGenerateSlugNormalizesMultipleHyphens(): void
    {
        $slug = playerAddGenerateSlug('A---B   C', 1700000000);

        $this->assertSame('a-b-c-1700000000', $slug);
    }

    public function testGenerateSlugWithOnlySymbolsKeepsTimestampSuffix(): void
    {
        $slug = playerAddGenerateSlug('---', 1700000000);

        $this->assertSame('-1700000000', $slug);
    }

    public function testBuildInsertParamsMapsRequiredAndDerivedValues(): void
    {
        $input = $this->validInput();
        $params = playerAddBuildInsertParams($input, [], 'my-slug-1');

        $this->assertSame('12', $params[':team_id']);
        $this->assertSame('Budi', $params[':name']);
        $this->assertSame('my-slug-1', $params[':slug']);
        $this->assertSame('L', $params[':gender']);
        $this->assertSame('Second Striker', $params[':position_detail']);
        $this->assertSame('inactive', $params[':status']);
    }

    public function testBuildInsertParamsConvertsEmptyOptionalFields(): void
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
        $input['position_detail'] = '';

        $params = playerAddBuildInsertParams($input, [], 'my-slug-2');

        $this->assertNull($params[':height']);
        $this->assertNull($params[':weight']);
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

    public function testBuildInsertParamsUsesUploadedFileDefaultsAndOverrides(): void
    {
        $input = $this->validInput();

        $defaultParams = playerAddBuildInsertParams($input, [], 'slug-defaults');
        $this->assertSame('', $defaultParams[':photo']);
        $this->assertSame('', $defaultParams[':ktp_image']);
        $this->assertSame('', $defaultParams[':kk_image']);
        $this->assertSame('', $defaultParams[':birth_cert_image']);
        $this->assertSame('', $defaultParams[':diploma_image']);

        $params = playerAddBuildInsertParams($input, [
            'photo_file' => 'photo.jpg',
            'ktp_file' => 'ktp.jpg',
            'kk_file' => 'kk.jpg',
            'akte_file' => 'akte.jpg',
            'ijazah_file' => 'ijazah.jpg',
        ], 'slug-custom');

        $this->assertSame('photo.jpg', $params[':photo']);
        $this->assertSame('ktp.jpg', $params[':ktp_image']);
        $this->assertSame('kk.jpg', $params[':kk_image']);
        $this->assertSame('akte.jpg', $params[':birth_cert_image']);
        $this->assertSame('ijazah.jpg', $params[':diploma_image']);
    }

    public function testInsertSqlPlaceholdersStayInSyncWithBuildInsertParams(): void
    {
        $sql = playerAddInsertSql();
        $params = playerAddBuildInsertParams($this->validInput(), [], 'my-slug-3');

        preg_match_all('/:[a-z_]+/', $sql, $matches);
        $sqlPlaceholders = array_values(array_unique($matches[0]));
        $paramKeys = array_keys($params);

        sort($sqlPlaceholders);
        sort($paramKeys);

        $this->assertSame($paramKeys, $sqlPlaceholders);
    }

    #[DataProvider('duplicateErrorProvider')]
    public function testMapInsertErrorForDuplicateKeys(array $errorInfo, string $message, string $expected): void
    {
        $e = new PDOException($message);
        $e->errorInfo = $errorInfo;

        $mapped = playerAddMapInsertError($e);

        $this->assertSame($expected, $mapped);
    }

    public function testMapInsertErrorForNonDuplicateKeepsRawMessage(): void
    {
        $e = new PDOException('Some SQL error');
        $e->errorInfo = ['HY000', 1234, 'Some SQL error'];

        $mapped = playerAddMapInsertError($e);

        $this->assertSame('Terjadi kesalahan: Some SQL error', $mapped);
    }

    public static function requiredFieldProvider(): array
    {
        return [
            ['name'],
            ['place_of_birth'],
            ['date_of_birth'],
            ['sport'],
            ['gender'],
            ['nik'],
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

    public static function duplicateErrorProvider(): array
    {
        return [
            [
                ['23000', 1062, "Duplicate entry for key 'uq_players_name'"],
                "Duplicate entry for key 'uq_players_name'",
                'Nama pemain sudah terdaftar. Gunakan nama yang berbeda.'
            ],
            [
                ['23000', 1062, "Duplicate entry for key 'players_nik_unique'"],
                "Duplicate entry for key 'players_nik_unique'",
                'NIK sudah terdaftar. Gunakan NIK yang berbeda.'
            ],
            [
                ['23000', 1062, 'Duplicate entry'],
                'Duplicate entry',
                'Data duplikat terdeteksi. Periksa kembali input Anda.'
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
