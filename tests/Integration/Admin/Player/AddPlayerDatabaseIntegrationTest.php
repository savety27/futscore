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

        $this->pdo->exec('DELETE FROM players');
    }

    public function testCanInsertPlayerDataIntoDedicatedTestDatabase(): void
    {
        $input = $this->validInput('Budi Integrasi', '1111222233334444');
        $slug = playerAddGenerateSlug($input['name'], 1700000000);
        $params = playerAddBuildInsertParams($input, [], $slug);

        $stmt = $this->pdo->prepare($this->insertSql());
        $stmt->execute($params);

        $saved = $this->pdo->query("SELECT name, nik, gender, status FROM players LIMIT 1")->fetch();

        $this->assertSame('Budi Integrasi', $saved['name']);
        $this->assertSame('1111222233334444', $saved['nik']);
        $this->assertSame('L', $saved['gender']);
        $this->assertSame('inactive', $saved['status']);
    }

    public function testDuplicateNameMapsToFriendlyErrorMessage(): void
    {
        $inputA = $this->validInput('Nama Sama', '1111222233334444');
        $inputB = $this->validInput('Nama Sama', '5555666677778888');

        $this->insertPlayer($inputA);

        try {
            $this->insertPlayer($inputB);
            $this->fail('Expected duplicate name insert to fail.');
        } catch (PDOException $e) {
            $mapped = playerAddMapInsertError($e);
            $this->assertSame('Nama pemain sudah terdaftar. Gunakan nama yang berbeda.', $mapped);
        }
    }

    public function testDuplicateNikMapsToFriendlyErrorMessage(): void
    {
        $inputA = $this->validInput('Nama Satu', '1111222233334444');
        $inputB = $this->validInput('Nama Dua', '1111222233334444');

        $this->insertPlayer($inputA);

        try {
            $this->insertPlayer($inputB);
            $this->fail('Expected duplicate NIK insert to fail.');
        } catch (PDOException $e) {
            $mapped = playerAddMapInsertError($e);
            $this->assertSame('NIK sudah terdaftar. Gunakan NIK yang berbeda.', $mapped);
        }
    }

    private function insertPlayer(array $input): void
    {
        $slug = playerAddGenerateSlug($input['name'], 1700000000);
        $params = playerAddBuildInsertParams($input, [], $slug);

        $stmt = $this->pdo->prepare($this->insertSql());
        $stmt->execute($params);
    }

    private function insertSql(): string
    {
        return "INSERT INTO players (
            team_id, name, slug, position, jersey_number, birth_date, height, weight,
            birth_place, gender, nisn, nik, sport_type, email, phone, nationality,
            street, city, province, postal_code, country, dominant_foot, position_detail,
            dribbling, technique, speed, juggling, shooting, setplay_position, passing, control,
            photo, ktp_image, kk_image, birth_cert_image, diploma_image,
            created_at, updated_at, status
        ) VALUES (
            :team_id, :name, :slug, :position, :jersey_number, :birth_date, :height, :weight,
            :birth_place, :gender, :nisn, :nik, :sport_type, :email, :phone, :nationality,
            :street, :city, :province, :postal_code, :country, :dominant_foot, :position_detail,
            :dribbling, :technique, :speed, :juggling, :shooting, :setplay_position, :passing, :control,
            :photo, :ktp_image, :kk_image, :birth_cert_image, :diploma_image,
            NOW(), NOW(), :status
        )";
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
}
