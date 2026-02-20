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
        $post = $this->validPostInput('ITEST Budi Integrasi', $nik);

        $playerId = playerAddCreatePlayer(
            $this->pdo,
            $post,
            ['kk_file' => ['error' => UPLOAD_ERR_OK]],
            $this->fakeUploadResolver('photo-a.jpg', 'ktp-a.jpg', 'kk-a.jpg', 'akte-a.jpg', 'ijazah-a.jpg')
        );

        $stmt = $this->pdo->prepare('SELECT name, nik, gender, status, kk_image FROM players WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $playerId]);
        $saved = $stmt->fetch();

        $this->assertSame('ITEST Budi Integrasi', $saved['name']);
        $this->assertSame($nik, $saved['nik']);
        $this->assertSame('L', $saved['gender']);
        $this->assertSame('inactive', $saved['status']);
        $this->assertSame('kk-a.jpg', $saved['kk_image']);
    }

    public function testDuplicateNameMapsToFriendlyErrorMessage(): void
    {
        playerAddCreatePlayer(
            $this->pdo,
            $this->validPostInput('ITEST Nama Sama', $this->randomNik()),
            ['kk_file' => ['error' => UPLOAD_ERR_OK]],
            $this->fakeUploadResolver('photo-1.jpg', 'ktp-1.jpg', 'kk-1.jpg', 'akte-1.jpg', 'ijazah-1.jpg')
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Nama pemain sudah terdaftar. Gunakan nama yang berbeda.');

        playerAddCreatePlayer(
            $this->pdo,
            $this->validPostInput('ITEST Nama Sama', $this->randomNik()),
            ['kk_file' => ['error' => UPLOAD_ERR_OK]],
            $this->fakeUploadResolver('photo-2.jpg', 'ktp-2.jpg', 'kk-2.jpg', 'akte-2.jpg', 'ijazah-2.jpg')
        );
    }

    public function testDuplicateNikMapsToFriendlyErrorMessage(): void
    {
        $nik = $this->randomNik();

        playerAddCreatePlayer(
            $this->pdo,
            $this->validPostInput('ITEST Nama Satu', $nik),
            ['kk_file' => ['error' => UPLOAD_ERR_OK]],
            $this->fakeUploadResolver('photo-1.jpg', 'ktp-1.jpg', 'kk-1.jpg', 'akte-1.jpg', 'ijazah-1.jpg')
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('NIK sudah terdaftar. Gunakan NIK yang berbeda.');

        playerAddCreatePlayer(
            $this->pdo,
            $this->validPostInput('ITEST Nama Dua', $nik),
            ['kk_file' => ['error' => UPLOAD_ERR_OK]],
            $this->fakeUploadResolver('photo-2.jpg', 'ktp-2.jpg', 'kk-2.jpg', 'akte-2.jpg', 'ijazah-2.jpg')
        );
    }

    private function validPostInput(string $name, string $nik): array
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
            'position_detail' => 'Second Striker',
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

    private function fakeUploadResolver(
        string $photo,
        string $ktp,
        string $kk,
        string $birthCert,
        string $diploma
    ): callable {
        return static function (array $files, string $uploadDir) use ($photo, $ktp, $kk, $birthCert, $diploma): array {
            return [
                'photo' => $photo,
                'ktp_image' => $ktp,
                'kk_image' => $kk,
                'birth_cert_image' => $birthCert,
                'diploma_image' => $diploma,
            ];
        };
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
