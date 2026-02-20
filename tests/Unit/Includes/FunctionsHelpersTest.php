<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FunctionsHelpersTest extends TestCase
{
    private array $serverBackup = [];
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        $this->sessionBackup = $_SESSION;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_SESSION = $this->sessionBackup;
    }

    public function testFormatDateHandlesUnavailableAndInvalidInput(): void
    {
        $this->assertSame('Tanggal tidak tersedia', formatDate(''));
        $this->assertSame('Tanggal tidak tersedia', formatDate('0000-00-00'));
        $this->assertSame('Tanggal tidak tersedia', formatDate('0000-00-00 00:00:00'));
        $this->assertSame('Tanggal tidak valid', formatDate('bukan-tanggal'));
    }

    public function testFormatDateFormatsReadableDate(): void
    {
        $this->assertSame('05 Jan 2024', formatDate('2024-01-05'));
    }

    public function testFormatDateTimeFormatsReadableDateTime(): void
    {
        $this->assertSame('05 Jan 2024, 14:30', formatDateTime('2024-01-05 14:30:00'));
    }

    public function testCreateSlugNormalizesAndTransliteratesText(): void
    {
        $this->assertSame('cafe-a-la-mode', createSlug('  Cafe a la mode!  '));
        $this->assertSame('n-a', createSlug('---***---'));
    }

    public function testTruncateTextKeepsWordBoundary(): void
    {
        $text = 'Ini adalah contoh kalimat panjang untuk pengujian';
        $this->assertSame('Ini adalah contoh...', truncateText($text, 25));
        $this->assertSame('Pendek', truncateText('Pendek', 20));
    }

    #[DataProvider('monthProvider')]
    public function testMonthHelpersReturnExpectedLabels(int $month, string $long, string $short): void
    {
        $this->assertSame($long, getIndonesianMonth($month));
        $this->assertSame($short, getShortIndonesianMonth($month));
    }

    #[DataProvider('dayProvider')]
    public function testDayHelpersReturnExpectedLabels(int $day, string $long, string $short): void
    {
        $this->assertSame($long, getIndonesianDay($day));
        $this->assertSame($short, getShortIndonesianDay($day));
    }

    public function testFormatIndonesianDateAndDateTimeCanIncludeDayName(): void
    {
        $this->assertSame('Kamis, 05 Januari 2023', formatIndonesianDate('2023-01-05', true));
        $this->assertSame('Kamis, 05 Januari 2023 14:30', formatIndonesianDateTime('2023-01-05 14:30:00', true));
    }

    public function testFormatIndonesianDateReturnsFallbackForInvalidInput(): void
    {
        $this->assertSame('Tanggal tidak tersedia', formatIndonesianDate(''));
        $this->assertSame('Tanggal tidak valid', formatIndonesianDate('abc'));
    }

    public function testCalculateAgeHandlesValidAndEmptyDate(): void
    {
        $birthDate = (new DateTime('today'))->modify('-20 years')->format('Y-m-d');
        $this->assertSame(20, calculateAge($birthDate));
        $this->assertSame('N/A', calculateAge('0000-00-00'));
    }

    #[DataProvider('timeAgoProvider')]
    public function testTimeAgoUsesExpectedBuckets(int $secondsAgo, string $expected): void
    {
        $datetime = date('Y-m-d H:i:s', time() - $secondsAgo);
        $this->assertSame($expected, timeAgo($datetime));
    }

    public function testRandomStringAndUniqueFilenameHaveExpectedFormat(): void
    {
        $random = generateRandomString(24);
        $this->assertSame(24, strlen($random));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{24}$/', $random);

        $filename = generateUniqueFilename('Photo.PNG');
        $this->assertMatchesRegularExpression('/^file_\d{10}_[A-Za-z0-9]{6}\.png$/', $filename);
    }

    public function testUrlAndImageHelpersValidateExpectedInput(): void
    {
        $this->assertSame('https://example.com/path', isValidUrl('https://example.com/path'));
        $this->assertFalse(isValidUrl('bukan-url'));

        $this->assertSame('jpg', getFileExtension('foto.JPG'));
        $this->assertTrue(isValidImage('foto.webp'));
        $this->assertFalse(isValidImage('dokumen.pdf'));
    }

    public function testNumberAndFileSizeFormattingHelpers(): void
    {
        $this->assertSame('1.234.567', formatNumber(1234567));
        $this->assertSame('512 bytes', formatFileSize(512));
        $this->assertSame('2.00 KB', formatFileSize(2048));
        $this->assertSame('2.00 MB', formatFileSize(2 * 1048576));
        $this->assertSame('2.00 GB', formatFileSize(2 * 1073741824));
    }

    public function testInputAndValidationHelpers(): void
    {
        $this->assertSame('&lt;b&gt;Halo&lt;/b&gt;', cleanInput('  <b>Halo</b>  '));
        $this->assertSame('O&#039;Reilly', cleanInput('  O\'Reilly  '));

        $errors = validateRequired(['name', 'email'], ['name' => 'Budi']);
        $this->assertSame(['email' => 'Field email harus diisi'], $errors);

        $this->assertNull(validateEmail('budi@example.com'));
        $this->assertSame('Format email tidak valid', validateEmail('budi@'));

        $this->assertNull(validatePhone('+62 812-3456-7890'));
        $this->assertSame('Format nomor telepon tidak valid', validatePhone('12345'));

        $this->assertNull(validateNumeric('12', 'umur'));
        $this->assertSame('Field umur harus berupa angka', validateNumeric('dua belas', 'umur'));

        $this->assertNull(validateDate('2024-02-29'));
        $this->assertSame('Format tanggal tidak valid', validateDate('2024-02-30'));
    }

    public function testServerBasedHelpersReadCurrentRequestState(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/berita?id=3';
        $this->assertSame('https://example.test/berita?id=3', getCurrentUrl());

        $_SERVER['HTTPS'] = 'off';
        $_SERVER['SCRIPT_NAME'] = '/futscore/news.php';
        $this->assertSame('http://example.test/futscore', getBaseUrl());

        $_SERVER['HTTP_CLIENT_IP'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.2';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.3';
        $this->assertSame('10.0.0.1', getClientIp());

        unset($_SERVER['HTTP_CLIENT_IP']);
        $this->assertSame('10.0.0.2', getClientIp());

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->assertSame('10.0.0.3', getClientIp());

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue(isAjaxRequest());

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->assertFalse(isAjaxRequest());
    }

    public function testFlashMessageHelpersGetUnsetAndRenderMessage(): void
    {
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'Data tersimpan',
        ];

        $first = getFlashMessage();
        $this->assertSame(['type' => 'success', 'message' => 'Data tersimpan'], $first);
        $this->assertNull(getFlashMessage());

        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Gagal menyimpan',
        ];

        $html = displayFlashMessage();
        $this->assertSame("<div class='alert alert-danger'>Gagal menyimpan</div>", $html);
        $this->assertNull(getFlashMessage());
    }

    public function testPaginationAndBreadcrumbHelpersBuildExpectedMarkup(): void
    {
        $links = getPaginationLinks(100, 10, 5, '/news?page={page}');
        $this->assertSame('&laquo;', $links[0]['label']);
        $this->assertSame('/news?page=4', $links[0]['url']);

        $active = array_values(array_filter($links, static fn (array $link): bool => $link['page'] === 5));
        $this->assertCount(1, $active);
        $this->assertTrue($active[0]['active']);

        $ellipsis = array_values(array_filter($links, static fn (array $link): bool => $link['label'] === '...'));
        $this->assertNotEmpty($ellipsis);

        $last = $links[count($links) - 1];
        $this->assertSame('&raquo;', $last['label']);
        $this->assertSame('/news?page=6', $last['url']);

        $breadcrumb = generateBreadcrumb([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Berita', 'url' => '/news'],
            ['label' => 'Detail', 'url' => '/news/1'],
        ]);

        $this->assertStringContainsString('<nav aria-label="breadcrumb"><ol class="breadcrumb">', $breadcrumb);
        $this->assertStringContainsString('<li class="breadcrumb-item"><a href="/">Home</a></li>', $breadcrumb);
        $this->assertStringContainsString('<li class="breadcrumb-item active" aria-current="page">Detail</li>', $breadcrumb);
    }

    public function testContentHelpersHighlightAndBuildSeoMetaTags(): void
    {
        $this->assertSame('Rp 1.500', formatCurrency(1500, 'IDR'));
        $this->assertSame('$1,500.00', formatCurrency(1500, 'USD'));
        $this->assertSame('1,500.00', formatCurrency(1500, 'EUR'));

        $this->assertSame('Hello world...', getExcerpt('<p>Hello <b>world</b> from futscore</p>', 12));

        $highlighted = highlightSearchTerms('Tim juara nasional', 'tim juara');
        $this->assertStringContainsString('<span class="highlight">Tim</span>', $highlighted);
        $this->assertStringContainsString('<span class="highlight">juara</span>', $highlighted);
        $this->assertSame('Tanpa kata kunci', highlightSearchTerms('Tanpa kata kunci', ''));

        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/news/slug';

        $metaTags = getSeoMetaTags('Judul & Tes', 'Desc <ok>', 'k1,k2', '/img.png');

        $this->assertStringContainsString('<title>Judul &amp; Tes</title>', $metaTags);
        $this->assertStringContainsString('<meta name="description" content="Desc &lt;ok&gt;">', $metaTags);
        $this->assertStringContainsString('<meta property="og:url" content="https://example.test/news/slug">', $metaTags);
        $this->assertStringContainsString('<meta name="twitter:image" content="/img.png">', $metaTags);
    }

    public static function monthProvider(): array
    {
        return [
            [1, 'Januari', 'Jan'],
            [5, 'Mei', 'Mei'],
            [12, 'Desember', 'Des'],
        ];
    }

    public static function dayProvider(): array
    {
        return [
            [0, 'Minggu', 'Min'],
            [3, 'Rabu', 'Rab'],
            [6, 'Sabtu', 'Sab'],
        ];
    }

    public static function timeAgoProvider(): array
    {
        return [
            [30, 'baru saja'],
            [120, '2 menit yang lalu'],
            [7200, '2 jam yang lalu'],
            [172800, '2 hari yang lalu'],
        ];
    }
}
