<?php

use PHPUnit\Framework\TestCase;

final class EditPlayerDatabaseIntegrationTest extends TestCase
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

    public function testCanUpdatePlayerDataWithEditSqlAndParams(): void
    {
        $insertedId = $this->insertPlayer('ITEST Edit Asal', $this->randomNik(), 1700000000);

        $editInput = $this->validEditInput('ITEST Edit Akhir', $this->randomNik());
        $params = playerEditBuildUpdateParams($editInput, [
            'photo' => 'new-photo.jpg',
            'ktp_image' => 'new-ktp.jpg',
            'kk_image' => 'new-kk.jpg',
            'birth_cert_image' => 'new-birth.jpg',
            'diploma_image' => 'new-diploma.jpg',
        ], $insertedId);

        $stmt = $this->pdo->prepare(playerEditUpdateSql());
        $stmt->execute($params);

        $stmt = $this->pdo->prepare('SELECT name, nik, gender, sport_type, status, kk_image FROM players WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $insertedId]);
        $saved = $stmt->fetch();

        $this->assertSame('ITEST Edit Akhir', $saved['name']);
        $this->assertSame($editInput['nik'], $saved['nik']);
        $this->assertSame('L', $saved['gender']);
        $this->assertSame('Futsal U-18', $saved['sport_type']);
        $this->assertSame('active', $saved['status']);
        $this->assertSame('new-kk.jpg', $saved['kk_image']);
    }

    public function testDuplicateNameMapsToFriendlyUpdateError(): void
    {
        $idA = $this->insertPlayer('ITEST Nama Tetap', $this->randomNik(), 1700000000);
        $idB = $this->insertPlayer('ITEST Nama Update', $this->randomNik(), 1700000001);

        $editInput = $this->validEditInput('ITEST Nama Tetap', $this->randomNik());
        $params = playerEditBuildUpdateParams($editInput, [], $idB);

        try {
            $stmt = $this->pdo->prepare(playerEditUpdateSql());
            $stmt->execute($params);
            $this->fail('Expected duplicate name update to fail.');
        } catch (PDOException $e) {
            $mapped = playerEditMapUpdateError($e);
            $this->assertSame('Error: Nama pemain sudah terdaftar. Gunakan nama yang berbeda.', $mapped);
        }

        $this->assertIsInt($idA);
    }

    public function testDuplicateNikMapsToFriendlyUpdateError(): void
    {
        $nik = $this->randomNik();
        $this->insertPlayer('ITEST NIK Satu', $nik, 1700000000);
        $idB = $this->insertPlayer('ITEST NIK Dua', $this->randomNik(), 1700000001);

        $editInput = $this->validEditInput('ITEST NIK Dua', $nik);
        $params = playerEditBuildUpdateParams($editInput, [], $idB);

        try {
            $stmt = $this->pdo->prepare(playerEditUpdateSql());
            $stmt->execute($params);
            $this->fail('Expected duplicate NIK update to fail.');
        } catch (PDOException $e) {
            $mapped = playerEditMapUpdateError($e);
            $this->assertSame('Error: NIK sudah terdaftar. Gunakan NIK yang berbeda.', $mapped);
        }
    }

    private function insertPlayer(string $name, string $nik, int $timestamp): int
    {
        $input = $this->validAddInput($name, $nik);
        $slug = playerAddGenerateSlug($name, $timestamp);
        $params = playerAddBuildInsertParams($input, [
            'photo_file' => 'orig-photo.jpg',
            'ktp_file' => 'orig-ktp.jpg',
            'kk_file' => 'orig-kk.jpg',
            'akte_file' => 'orig-birth.jpg',
            'ijazah_file' => 'orig-diploma.jpg',
        ], $slug);

        $stmt = $this->pdo->prepare(playerAddInsertSql());
        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    private function validAddInput(string $name, string $nik): array
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
            'position_detail' => '',
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

    private function validEditInput(string $name, string $nik): array
    {
        return [
            'name' => $name,
            'birth_place' => 'Bandung',
            'birth_date' => '2011-02-02',
            'sport_type' => 'Futsal U-18',
            'gender' => 'Laki-laki',
            'nik' => $nik,
            'nisn' => '0987654321',
            'height' => '172',
            'weight' => '63',
            'email' => 'edit@example.com',
            'phone' => '08999999999',
            'nationality' => 'Indonesia',
            'street' => 'Jalan Melati',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40211',
            'country' => 'Indonesia',
            'team_id' => '1',
            'jersey_number' => '9',
            'dominant_foot' => 'Kanan',
            'position' => 'FW',
            'position_detail' => 'Target Man',
            'status' => 'active',
            'dribbling' => 8,
            'technique' => 8,
            'speed' => 8,
            'juggling' => 7,
            'shooting' => 9,
            'setplay_position' => 7,
            'passing' => 8,
            'control' => 9,
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
