<?php

use PHPUnit\Framework\TestCase;

final class PerangkatSaveDatabaseIntegrationTest extends TestCase
{
    private PDO $pdo;
    private int $slugSeed = 1800000000;

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

    public function testCanInsertPerangkatDataIntoDedicatedTestDatabase(): void
    {
        $noKtp = $this->randomNik();
        $perangkatId = perangkatCreateSave(
            $this->pdo,
            $this->validPerangkatInput('ITEST Wasit Satu', $noKtp),
            'photo-1.jpg',
            'ktp-1.jpg',
            [
                [
                    'name' => 'Lisensi Nasional',
                    'file' => 'license-1.pdf',
                    'authority' => 'PSSI',
                    'date' => '2026-01-10',
                ],
            ]
        );

        $stmt = $this->pdo->prepare('SELECT name, no_ktp, ktp_photo FROM perangkat WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $perangkatId]);
        $saved = $stmt->fetch();

        $this->assertSame('ITEST Wasit Satu', $saved['name']);
        $this->assertSame($noKtp, $saved['no_ktp']);
        $this->assertSame('ktp-1.jpg', $saved['ktp_photo']);

        $licenseStmt = $this->pdo->prepare('SELECT COUNT(*) FROM perangkat_licenses WHERE perangkat_id = :id');
        $licenseStmt->execute([':id' => $perangkatId]);
        $this->assertSame(1, (int)$licenseStmt->fetchColumn());
    }

    public function testDuplicateNoKtpMapsToFriendlyErrorMessage(): void
    {
        $noKtp = $this->randomNik();

        perangkatCreateSave(
            $this->pdo,
            $this->validPerangkatInput('ITEST Wasit A', $noKtp),
            'photo-a.jpg',
            'ktp-a.jpg'
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No. KTP sudah terdaftar');

        perangkatCreateSave(
            $this->pdo,
            $this->validPerangkatInput('ITEST Wasit B', $noKtp),
            'photo-b.jpg',
            'ktp-b.jpg'
        );
    }

    public function testNoKtpAlreadyRegisteredAsPlayerIsRejected(): void
    {
        $noKtp = $this->randomNik();
        $this->insertPlayer('ITEST Pemain Satu', $noKtp);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No. KTP sudah terdaftar sebagai pemain.');

        perangkatCreateSave(
            $this->pdo,
            $this->validPerangkatInput('ITEST Wasit Konflik', $noKtp),
            'photo-c.jpg',
            'ktp-c.jpg'
        );
    }

    public function testUpdateToExistingPerangkatNoKtpMapsToFriendlyErrorMessage(): void
    {
        $existingNoKtp = $this->randomNik();

        perangkatCreateSave(
            $this->pdo,
            $this->validPerangkatInput('ITEST Wasit Existing', $existingNoKtp),
            'photo-a.jpg',
            'ktp-a.jpg'
        );
        $perangkatId = perangkatCreateSave(
            $this->pdo,
            $this->validPerangkatInput('ITEST Wasit Update', $this->randomNik()),
            'photo-b.jpg',
            'ktp-b.jpg'
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No. KTP sudah terdaftar');

        perangkatUpdateSave(
            $this->pdo,
            $perangkatId,
            $this->validPerangkatInput('ITEST Wasit Update', $existingNoKtp),
            'photo-b.jpg',
            'ktp-b.jpg'
        );
    }

    public function testUpdateToPlayerNikMapsToFriendlyErrorMessage(): void
    {
        $perangkatId = perangkatCreateSave(
            $this->pdo,
            $this->validPerangkatInput('ITEST Wasit Edit', $this->randomNik()),
            'photo-edit.jpg',
            'ktp-edit.jpg'
        );
        $playerNik = $this->randomNik();
        $this->insertPlayer('ITEST Pemain Dua', $playerNik);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No. KTP sudah terdaftar sebagai pemain.');

        perangkatUpdateSave(
            $this->pdo,
            $perangkatId,
            $this->validPerangkatInput('ITEST Wasit Edit', $playerNik),
            'photo-edit.jpg',
            'ktp-edit.jpg'
        );
    }

    private function insertPlayer(string $name, string $nik, string $teamId = '1'): int
    {
        $input = [
            'name' => $name,
            'birth_place' => 'Jakarta',
            'birth_date' => '2010-01-01',
            'sport_type' => 'Futsal U-16',
            'gender' => 'Laki-laki',
            'nik' => $nik,
            'nisn' => '1234567890',
            'height' => '170',
            'weight' => '60',
            'email' => 'player@example.com',
            'phone' => '08123456789',
            'nationality' => 'Indonesia',
            'street' => 'Jalan Mawar',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '12345',
            'country' => 'Indonesia',
            'team_id' => $teamId,
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

        $slug = playerAddGenerateSlug($name, $this->slugSeed++);
        $params = playerAddBuildInsertParams($input, [
            'photo_file' => 'player-photo.jpg',
            'ktp_file' => 'player-ktp.jpg',
            'kk_file' => 'player-kk.jpg',
            'akte_file' => 'player-akte.jpg',
            'ijazah_file' => 'player-ijazah.jpg',
        ], $slug);

        $stmt = $this->pdo->prepare(playerAddInsertSql());
        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    private function validPerangkatInput(string $name, string $noKtp): array
    {
        return [
            'name' => $name,
            'no_ktp' => $noKtp,
            'birth_place' => 'Batam',
            'date_of_birth' => '1990-01-01',
            'gender' => 'Laki-laki',
            'email' => 'wasit@example.com',
            'phone' => '08123456789',
            'address' => 'Jalan Stadion',
            'city' => 'Batam',
            'province' => 'Kepulauan Riau',
            'postal_code' => '29411',
            'country' => 'Indonesia',
            'is_active' => 1,
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
