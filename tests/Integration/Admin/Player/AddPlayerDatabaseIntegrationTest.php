<?php

use PHPUnit\Framework\TestCase;

final class AddPlayerDatabaseIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'futscore_test';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        if ($name !== 'futscore_test') {
            $this->fail("Unsafe DB_NAME '{$name}'. Integration tests must run on futscore_test.");
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testCanInsertPlayerDataIntoDedicatedTestDatabase(): void
    {
        $nik = $this->randomNik();
        $input = $this->validInput('ITEST Budi Integrasi', $nik);
        $slug = playerAddGenerateSlug($input['name'], 1700000000);
        $params = playerAddBuildInsertParams($input, [], $slug);

        $stmt = $this->pdo->prepare(playerAddInsertSql());
        $stmt->execute($params);

        $stmt = $this->pdo->prepare('SELECT name, nik, gender, status FROM players WHERE nik = :nik LIMIT 1');
        $stmt->execute([':nik' => $nik]);
        $saved = $stmt->fetch();

        $this->assertSame('ITEST Budi Integrasi', $saved['name']);
        $this->assertSame($nik, $saved['nik']);
        $this->assertSame('L', $saved['gender']);
        $this->assertSame('inactive', $saved['status']);
    }

    public function testDuplicateNameMapsToFriendlyErrorMessage(): void
    {
        $inputA = $this->validInput('ITEST Nama Sama', $this->randomNik());
        $inputB = $this->validInput('ITEST Nama Sama', $this->randomNik());

        $this->insertPlayer($inputA, 1700000000);

        try {
            $this->insertPlayer($inputB, 1700000001);
            $this->fail('Expected duplicate name insert to fail.');
        } catch (PDOException $e) {
            $mapped = playerAddMapInsertError($e);
            $this->assertSame('Nama pemain sudah terdaftar. Gunakan nama yang berbeda.', $mapped);
        }
    }

    public function testDuplicateNikMapsToFriendlyErrorMessage(): void
    {
        $nik = $this->randomNik();
        $inputA = $this->validInput('ITEST Nama Satu', $nik);
        $inputB = $this->validInput('ITEST Nama Dua', $nik);

        $this->insertPlayer($inputA);

        try {
            $this->insertPlayer($inputB);
            $this->fail('Expected duplicate NIK insert to fail.');
        } catch (PDOException $e) {
            $mapped = playerAddMapInsertError($e);
            $this->assertSame('NIK sudah terdaftar. Gunakan NIK yang berbeda.', $mapped);
        }
    }

    private function insertPlayer(array $input, ?int $timestamp = null): void
    {
        $slug = playerAddGenerateSlug($input['name'], $timestamp ?? 1700000000);
        $params = playerAddBuildInsertParams($input, [], $slug);

        $stmt = $this->pdo->prepare(playerAddInsertSql());
        $stmt->execute($params);
    }

    private function validInput(string $name, string $nik): array
    {
        return [
            'name' => $name,
            'place_of_birth' => 'Jakarta',
            'date_of_birth' => '2010-01-01',
            'sport' => 'Futsal U-16',
            'gender' => 'Laki-laki',
            'nik' => $nik,
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
            'team_id' => '1',
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

    private function randomNik(): string
    {
        $nik = '';
        for ($i = 0; $i < 16; $i++) {
            $nik .= (string) random_int(0, 9);
        }
        return $nik;
    }
}
