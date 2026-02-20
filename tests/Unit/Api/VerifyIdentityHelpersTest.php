<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VerifyIdentityHelpersTest extends TestCase
{
    public function testVerifyNikRejectsInvalidFormat(): void
    {
        $result = verifyNIK('12ab', $this->provinsiCodes(), new FakeVerifyConnection(), 0);

        $this->assertFalse($result['verified']);
        $this->assertSame('format', $result['details']['step']);
    }

    #[DataProvider('nikStructuralInvalidProvider')]
    public function testVerifyNikRejectsInvalidStructuralSegments(string $nik, string $expectedStep): void
    {
        $result = verifyNIK($nik, $this->provinsiCodes(), new FakeVerifyConnection(), 0);

        $this->assertFalse($result['verified']);
        $this->assertSame($expectedStep, $result['details']['step']);
    }

    public function testVerifyNikRejectsDuplicateFromDatabase(): void
    {
        $conn = new FakeVerifyConnection(['id' => 9, 'name' => 'Rina']);

        $result = verifyNIK($this->validNikMale(), $this->provinsiCodes(), $conn, 0);

        $this->assertFalse($result['verified']);
        $this->assertSame('duplicate', $result['details']['step']);
        $this->assertSame('Rina', $result['details']['existing_player']);
    }

    public function testVerifyNikUsesExcludePlayerIdInDuplicateQuery(): void
    {
        $nik = $this->validNikMale();
        $conn = new FakeVerifyConnection();

        $result = verifyNIK($nik, $this->provinsiCodes(), $conn, 77);

        $this->assertTrue($result['verified']);
        $this->assertStringContainsString('AND id != ?', (string)$conn->preparedSql);
        $this->assertSame([$nik, 77], $conn->executedParams);
    }

    public function testVerifyNikContinuesWhenDuplicateCheckThrows(): void
    {
        $conn = new FakeVerifyConnection(null, true, false);

        $result = verifyNIK($this->validNikMale(), $this->provinsiCodes(), $conn, 0);

        $this->assertTrue($result['verified']);
        $this->assertSame('Laki-laki', $result['details']['jenis_kelamin']);
    }

    #[DataProvider('nikSuccessProvider')]
    public function testVerifyNikSuccessDecodesGenderAndBirthDate(
        string $nik,
        string $expectedGender,
        string $expectedBirthDate
    ): void {
        $result = verifyNIK($nik, $this->provinsiCodes(), new FakeVerifyConnection(), 0);

        $this->assertTrue($result['verified']);
        $this->assertSame('DKI Jakarta', $result['details']['provinsi']);
        $this->assertSame($expectedGender, $result['details']['jenis_kelamin']);
        $this->assertSame($expectedBirthDate, $result['details']['tanggal_lahir']);
    }

    public function testVerifyNisnRejectsInvalidFormatWithDigitCount(): void
    {
        $result = verifyNISN('12-34x', new FakeVerifyConnection(), 0);

        $this->assertFalse($result['verified']);
        $this->assertSame('format', $result['details']['step']);
        $this->assertSame(
            'NISN harus terdiri dari tepat 10 digit angka (saat ini: 4 digit)',
            $result['message']
        );
    }

    #[DataProvider('nisnPatternInvalidProvider')]
    public function testVerifyNisnRejectsInvalidPatterns(string $nisn, string $expectedStep): void
    {
        $result = verifyNISN($nisn, new FakeVerifyConnection(), 0);

        $this->assertFalse($result['verified']);
        $this->assertSame($expectedStep, $result['details']['step']);
    }

    public function testVerifyNisnRejectsInvalidBirthYearCode(): void
    {
        $result = verifyNISN('5003214567', new FakeVerifyConnection(), 0);

        $this->assertFalse($result['verified']);
        $this->assertSame('tahun_lahir', $result['details']['step']);
    }

    public function testVerifyNisnRejectsZeroMiddleCode(): void
    {
        $result = verifyNISN('0100004567', new FakeVerifyConnection(), 0);

        $this->assertFalse($result['verified']);
        $this->assertSame('kode_tengah', $result['details']['step']);
    }

    public function testVerifyNisnRejectsZeroSequenceNumber(): void
    {
        $result = verifyNISN('0103210000', new FakeVerifyConnection(), 0);

        $this->assertFalse($result['verified']);
        $this->assertSame('nomor_urut', $result['details']['step']);
    }

    public function testVerifyNisnRejectsDuplicateFromDatabase(): void
    {
        $nisn = $this->buildNisnForYear((int)date('Y') - 10);
        $conn = new FakeVerifyConnection(['id' => 4, 'name' => 'Doni']);

        $result = verifyNISN($nisn, $conn, 0);

        $this->assertFalse($result['verified']);
        $this->assertSame('duplicate', $result['details']['step']);
        $this->assertSame('Doni', $result['details']['existing_player']);
    }

    public function testVerifyNisnUsesExcludePlayerIdInDuplicateQuery(): void
    {
        $nisn = $this->buildNisnForYear((int)date('Y') - 10);
        $conn = new FakeVerifyConnection();

        $result = verifyNISN($nisn, $conn, 31);

        $this->assertTrue($result['verified']);
        $this->assertStringContainsString('AND id != ?', (string)$conn->preparedSql);
        $this->assertSame([$nisn, 31], $conn->executedParams);
    }

    public function testVerifyNisnContinuesWhenDuplicateCheckThrows(): void
    {
        $nisn = $this->buildNisnForYear((int)date('Y') - 10);
        $conn = new FakeVerifyConnection(null, false, true);

        $result = verifyNISN($nisn, $conn, 0);

        $this->assertTrue($result['verified']);
    }

    #[DataProvider('nisnJenjangProvider')]
    public function testVerifyNisnSuccessMapsEducationLevel(int $birthYear, string $expectedJenjang): void
    {
        $nisn = $this->buildNisnForYear($birthYear);
        $result = verifyNISN($nisn, new FakeVerifyConnection(), 0);

        $expectedUsia = ((int)date('Y') - $birthYear) . ' Tahun';

        $this->assertTrue($result['verified']);
        $this->assertSame((string)$birthYear, $result['details']['tahun_lahir']);
        $this->assertSame($expectedUsia, $result['details']['usia']);
        $this->assertSame($expectedJenjang, $result['details']['perkiraan_jenjang']);
    }

    public static function nikStructuralInvalidProvider(): array
    {
        return [
            'invalid province' => ['9701011501100001', 'provinsi'],
            'invalid kab kota' => ['3100011501100001', 'kab_kota'],
            'invalid kecamatan' => ['3101001501100001', 'kecamatan'],
            'invalid encoded day' => ['3101010010100001', 'tanggal'],
            'invalid month' => ['3101011513100001', 'bulan'],
            'invalid checkdate' => ['3101013104100001', 'checkdate'],
            'invalid urutan' => ['3101011501100000', 'urutan'],
        ];
    }

    public static function nikSuccessProvider(): array
    {
        return [
            'male nik' => ['3101011501100001', 'Laki-laki', '15-01-2010'],
            'female nik' => ['3101014501100001', 'Perempuan', '05-01-2010'],
        ];
    }

    public static function nisnPatternInvalidProvider(): array
    {
        return [
            'same digit pattern' => ['1111111111', 'pattern_same'],
            'ascending sequential pattern' => ['1234567890', 'pattern_sequential'],
            'descending sequential pattern' => ['9876543210', 'pattern_sequential'],
            'repeated chunk pattern' => ['1212121212', 'pattern_repeated'],
        ];
    }

    public static function nisnJenjangProvider(): array
    {
        $tahunSekarang = (int)date('Y');

        return [
            'paud tk' => [$tahunSekarang - 4, 'PAUD/TK (Belum Masuk SD)'],
            'sd' => [$tahunSekarang - 10, 'SD/MI (Sekolah Dasar)'],
            'smp' => [$tahunSekarang - 14, 'SMP/MTs (Sekolah Menengah Pertama)'],
            'sma' => [$tahunSekarang - 17, 'SMA/SMK/MA (Sekolah Menengah Atas)'],
            'mahasiswa' => [$tahunSekarang - 20, 'Mahasiswa / Kuliah'],
            'alumni with 19xx decode' => [1999, 'Alumni / Umum'],
        ];
    }

    private function provinsiCodes(): array
    {
        return [
            '31' => 'DKI Jakarta',
        ];
    }

    private function validNikMale(): string
    {
        return '3101011501100001';
    }

    private function buildNisnForYear(int $year, string $middle = '321', string $order = '4567'): string
    {
        if ($year >= 2000) {
            $kodeTahun = str_pad((string)($year - 2000), 3, '0', STR_PAD_LEFT);
        } else {
            $kodeTahun = str_pad((string)($year - 1000), 3, '0', STR_PAD_LEFT);
        }

        return $kodeTahun . $middle . $order;
    }
}

final class FakeVerifyConnection
{
    public ?string $preparedSql = null;
    public ?array $executedParams = null;

    private ?array $row;
    private bool $throwOnPrepare;
    private bool $throwOnExecute;

    public function __construct(?array $row = null, bool $throwOnPrepare = false, bool $throwOnExecute = false)
    {
        $this->row = $row;
        $this->throwOnPrepare = $throwOnPrepare;
        $this->throwOnExecute = $throwOnExecute;
    }

    public function prepare(string $sql): FakeVerifyStatement
    {
        if ($this->throwOnPrepare) {
            throw new PDOException('Prepare failed');
        }

        $this->preparedSql = $sql;

        return new FakeVerifyStatement($this, $this->row, $this->throwOnExecute);
    }

    public function setExecutedParams(array $params): void
    {
        $this->executedParams = $params;
    }
}

final class FakeVerifyStatement
{
    private FakeVerifyConnection $connection;
    private ?array $row;
    private bool $throwOnExecute;

    public function __construct(FakeVerifyConnection $connection, ?array $row, bool $throwOnExecute)
    {
        $this->connection = $connection;
        $this->row = $row;
        $this->throwOnExecute = $throwOnExecute;
    }

    public function execute(array $params): void
    {
        if ($this->throwOnExecute) {
            throw new PDOException('Execute failed');
        }

        $this->connection->setExecutedParams($params);
    }

    public function fetch(int $mode = PDO::FETCH_ASSOC): ?array
    {
        return $this->row;
    }
}
