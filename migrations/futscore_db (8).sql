-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 13 Feb 2026 pada 00.40
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `futscore_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('superadmin','admin','editor','pelatih') DEFAULT 'admin',
  `team_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `team_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'admin@futscore.com', '$2y$10$dzjlNxiMyOK83KloXa.W/eaAoCda5d.WuiMDfR0VCb6yMoivKIkkq', 'Administrator Utama', 'superadmin', NULL, 1, '2026-02-12 22:21:13', '2026-01-27 11:49:53', '2026-02-12 15:21:13'),
(2, 'Pelatih Batam Sport', 'pelatihbatamsport@gmail.com', '$2y$10$RaxOfM.KzQh1gH9hupF34Oj8YFvpwbYFDWdrozVZW1JkhpGnHvlI2', 'Pelatih Batam Sport', 'pelatih', 46, 1, '2026-02-13 06:39:06', '2026-01-29 12:39:47', '2026-02-12 23:39:06'),
(3, 'Pelatih Oxygen', 'pelatihoxygen@gmail.com', '$2y$10$b8tdLz201ryM1kx.NYxdi.ddOdbyo.4mFqU38loCrbQS0h5Yr9wcO', 'Pelatih Oxygen', 'pelatih', 32, 1, '2026-02-06 21:34:32', '2026-01-31 05:02:08', '2026-02-06 14:34:32'),
(5, 'Pelatih Fafage', 'pelatihfafage@gmail.com', '$2y$10$vMf10uEs8gdHRatAxipuouApFClBfx4eZEcvJX.zjGOrdHEWvqjzC', 'Pelatih Fafage', 'pelatih', 19, 1, '2026-02-06 21:35:38', '2026-02-06 14:18:09', '2026-02-06 14:35:38'),
(6, 'Pelatih Kings', 'pelatihkings@gmail.com', '$2y$10$vWCpVOEgTgx3CUwS0jGDyecB7p7DoEx3GLwI7I083.XhnLh4nMp/.', 'Pelatih Kings', 'pelatih', 25, 1, '2026-02-06 21:34:57', '2026-02-06 14:19:01', '2026-02-06 14:34:57'),
(7, 'Pelatih Tiger', 'pelatihtiger@gmail.com', '$2y$10$gEm8jpi7/zGWWcmPVA7EfOzkqQnyWw7nHUCuluYuONwXauzjedWTi', 'Pelatih Tiger', 'pelatih', 37, 1, '2026-02-09 14:18:11', '2026-02-06 14:19:37', '2026-02-09 07:18:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `berita`
--

CREATE TABLE `berita` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `konten` longtext NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `penulis` varchar(100) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `tag` varchar(255) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `berita`
--

INSERT INTO `berita` (`id`, `judul`, `slug`, `konten`, `gambar`, `penulis`, `status`, `tag`, `views`, `created_at`, `updated_at`) VALUES
(1, 'Kasus Bola Viral', 'kasus-bola-viral', '<p style=\"text-align: center; \"><span style=\"font-family: &quot;Arial Black&quot;;\"><b>Kasus Sepak Bola Viral: Gol Kontroversial Picu Kericuhan di Media Sosial</b></span></p><p style=\"text-align: center; \"><span style=\"font-family: &quot;Arial Black&quot;;\"><b><br></b></span></p><p><span style=\"font-family: &quot;Comic Sans MS&quot;;\">Sebuah pertandingan sepak bola yang digelar pada akhir pekan lalu mendadak menjadi perbincangan hangat di media sosial. Laga yang mempertemukan dua tim rival tersebut awalnya berjalan normal dan penuh semangat sportivitas. Namun, suasana berubah drastis setelah terciptanya sebuah gol kontroversial di menit-menit akhir pertandingan.\r\n</span></p><p><span style=\"font-family: &quot;Comic Sans MS&quot;;\">Gol tersebut dicetak oleh penyerang tim tuan rumah pada menit ke-89. Dalam tayangan ulang yang beredar luas di media sosial, terlihat jelas adanya dugaan posisi offside sebelum bola masuk ke gawang. Meski begitu, wasit tetap mengesahkan gol tersebut, yang sekaligus mengubah skor menjadi 2–1 dan memastikan kemenangan tim tuan rumah.\r\n</span></p><p><span style=\"font-family: &quot;Comic Sans MS&quot;;\">Keputusan wasit itu langsung memicu protes keras dari para pemain tim tamu. Beberapa pemain terlihat mengerumuni wasit, menyampaikan ketidakpuasan atas keputusan yang dianggap merugikan. Pelatih tim tamu bahkan sempat masuk ke area lapangan untuk meminta penjelasan, sebelum akhirnya ditenangkan oleh ofisial pertandingan.\r\n</span></p><p><span style=\"font-family: &quot;Comic Sans MS&quot;;\">Tak hanya di lapangan, reaksi keras juga datang dari para suporter. Di tribun penonton, terdengar sorakan dan teriakan protes, meskipun situasi masih dapat dikendalikan oleh petugas keamanan. Setelah peluit panjang dibunyikan, suasana pertandingan tetap tegang, namun tidak terjadi bentrokan fisik yang serius.\r\n</span></p><p><span style=\"font-family: &quot;Comic Sans MS&quot;;\">Video cuplikan gol tersebut kemudian tersebar luas di berbagai platform media sosial seperti Instagram, TikTok, dan X. Dalam hitungan jam, video itu telah ditonton ratusan ribu kali dan menuai ribuan komentar. Banyak warganet menilai keputusan wasit tidak adil dan meminta pihak penyelenggara pertandingan untuk melakukan evaluasi.\r\n</span></p><p><span style=\"font-family: &quot;Comic Sans MS&quot;;\">Sebagian netizen juga menyoroti absennya teknologi Video Assistant Referee (VAR) dalam pertandingan tersebut. Menurut mereka, penggunaan VAR seharusnya bisa membantu wasit mengambil keputusan yang lebih akurat, terutama pada momen krusial seperti gol penentu kemenangan.\r\n</span></p><p><span style=\"font-family: &quot;Comic Sans MS&quot;;\">Menanggapi polemik yang berkembang, pihak panitia penyelenggara akhirnya mengeluarkan pernyataan resmi. Mereka menyampaikan bahwa keputusan wasit bersifat final di lapangan, namun tetap akan dilakukan evaluasi internal terhadap kinerja perangkat pertandingan. Panitia juga menegaskan komitmennya untuk meningkatkan kualitas penyelenggaraan kompetisi ke depannya.\r\n</span></p><p><span style=\"font-family: &quot;Comic Sans MS&quot;;\">Sementara itu, manajemen tim tamu dikabarkan akan mengajukan surat protes resmi kepada federasi terkait. Mereka berharap kejadian serupa tidak terulang kembali dan meminta adanya peningkatan profesionalisme dalam kepemimpinan wasit.\r\n</span></p><p><span style=\"font-family: &quot;Comic Sans MS&quot;;\">Kasus ini menjadi pengingat bahwa sepak bola tidak hanya soal kemenangan dan kekalahan, tetapi juga tentang keadilan, sportivitas, dan kepercayaan publik. Di era digital, setiap keputusan di lapangan bisa dengan cepat menjadi sorotan nasional, bahkan viral, dalam hitungan menit.</span>\r\n</p><p><br></p><p><br></p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p><p>\r\n</p>', 'berita_1770387691_6985f8ebc38de.jpg', 'Savety', 'published', 'Bola, Trending, Viral, FYP', 17, '2026-01-31 11:06:51', '2026-02-12 13:42:13'),
(2, 'Bola Terbang ?', 'bola-terbang', '<div style=\"text-align: center;\"><b style=\"font-family: inherit;\">Kasus Bola Terbang Viral, Pertandingan Terhenti dan Jadi Sorotan Publik</b></div><div style=\"text-align: center;\"><br></div><div style=\"text-align: center; \">Sebuah momen tak biasa terjadi dalam sebuah pertandingan sepak bola yang digelar baru-baru ini dan langsung menjadi viral di media sosial. Insiden tersebut dikenal dengan sebutan “kasus bola terbang”, setelah bola pertandingan melambung tinggi keluar area stadion hingga menyebabkan laga terhenti selama beberapa menit.</div><div style=\"text-align: center;\"><br></div><div style=\"text-align: center; \">Peristiwa itu terjadi pada babak kedua saat salah satu pemain melepaskan tendangan keras dari luar kotak penalti. Alih-alih mengarah ke gawang, bola justru melambung sangat tinggi dan keluar dari stadion, melewati pagar pembatas hingga jatuh ke area luar lapangan. Kejadian ini sontak membuat para pemain, wasit, dan penonton terkejut.</div><div style=\"text-align: center;\"><br></div><div style=\"text-align: center; \">Wasit pertandingan langsung menghentikan jalannya laga karena tidak tersedia bola cadangan di sisi lapangan. Beberapa pemain terlihat menunggu sambil tertawa kecil, sementara sebagian lainnya memanfaatkan momen tersebut untuk minum dan berdiskusi dengan rekan setim. Di tribun, penonton pun ramai bersorak dan merekam kejadian tersebut menggunakan ponsel mereka.</div><div style=\"text-align: center;\"><br></div><div style=\"text-align: center;\">Situasi semakin menarik perhatian ketika bola yang terbang tersebut tidak segera ditemukan. Panitia pertandingan sempat kesulitan mencari bola karena diduga jatuh ke area permukiman di sekitar stadion. Akibatnya, pertandingan terhenti hampir sepuluh menit, waktu yang cukup lama untuk sebuah laga resmi.</div><div style=\"text-align: center;\"><br></div><div style=\"text-align: center;\">Video momen “bola terbang” ini dengan cepat beredar di media sosial. Banyak warganet memberikan komentar lucu dan menjadikan kejadian tersebut sebagai bahan candaan. Beberapa bahkan menyebut tendangan itu sebagai “tendangan roket” karena bola melesat jauh melampaui batas lapangan.</div><div style=\"text-align: center;\"><br></div><div style=\"text-align: center;\">Namun di balik kehebohan tersebut, sejumlah pihak menyoroti kurangnya kesiapan panitia pertandingan. Minimnya bola cadangan dinilai sebagai kelalaian yang seharusnya tidak terjadi, terutama dalam pertandingan resmi. Hal ini memicu diskusi tentang standar penyelenggaraan dan profesionalisme dalam kompetisi sepak bola.</div><div style=\"text-align: center;\"><br></div><div style=\"text-align: center;\">Pihak penyelenggara akhirnya memberikan klarifikasi bahwa kejadian tersebut terjadi di luar perkiraan. Mereka mengakui adanya kekurangan dalam persiapan dan berjanji akan melakukan evaluasi agar insiden serupa tidak terulang kembali. Pertandingan pun dilanjutkan setelah bola pengganti berhasil disiapkan.</div><div style=\"text-align: center;\"><br></div><div style=\"text-align: center; \">Meski tidak berdampak langsung pada hasil akhir pertandingan, kasus bola terbang ini menjadi pengingat bahwa detail kecil dalam sepak bola bisa menjadi sorotan besar, terutama di era media sosial. Sebuah momen sederhana di lapangan kini dapat berubah menjadi viral dan diperbincangkan oleh banyak orang dalam waktu singkat.</div>', 'berita_1770212915_69834e335a683.jpg', 'Ricky', 'published', 'bola, viral', 13, '2026-02-04 13:48:35', '2026-02-12 13:42:10'),
(3, 'Ditemukan Bola Ajaib ???', 'bola-ajaib', '<p style=\"text-align: center; \"><b><span style=\"font-family: &quot;Arial Black&quot;;\">Fenomena Bola Ajaib Viral, Arah Tendangan Tak Terduga Bikin Heboh</span></b></p><p style=\"text-align: center; \"><br></p><p><span style=\"font-family: Impact;\">Sebuah kejadian unik dan tak biasa terjadi dalam sebuah pertandingan sepak bola yang digelar baru-baru ini. Insiden tersebut langsung menyita perhatian publik setelah sebuah tendangan menghasilkan pergerakan bola yang tidak lazim dan dijuluki warganet sebagai “bola ajaib”.</span></p><p><br></p><p><span style=\"font-family: Impact;\">Peristiwa ini terjadi pada babak pertama ketika seorang pemain melepaskan tendangan bebas dari luar kotak penalti. Awalnya, bola terlihat melaju normal menuju arah gawang. Namun secara mengejutkan, bola tiba-tiba berbelok tajam di udara, mengecoh penjaga gawang dan berakhir di dalam gawang.</span></p><p><br></p><p><span style=\"font-family: Impact;\">Kiper yang sudah bergerak ke arah yang benar tampak terdiam sejenak, seolah tidak percaya dengan arah bola yang berubah secara drastis. Para pemain di lapangan pun menunjukkan ekspresi kebingungan, sementara wasit tetap mengesahkan gol tersebut karena tidak ada pelanggaran yang terjadi.</span></p><p><br></p><p><span style=\"font-family: Impact;\">Sorakan penonton langsung pecah di stadion. Banyak yang mengira peristiwa itu hanyalah ilusi optik akibat sudut kamera, namun tayangan ulang dari berbagai sudut justru memperlihatkan perubahan arah bola yang cukup ekstrem. Hal inilah yang membuat kejadian tersebut semakin viral.</span></p><p><br></p><p><span style=\"font-family: Impact;\">Video gol “bola ajaib” ini menyebar cepat di media sosial. Dalam waktu singkat, rekaman tersebut dipenuhi komentar warganet yang menyebut tendangan itu melawan hukum fisika. Sebagian netizen menyebut faktor angin sebagai penyebab, sementara lainnya menduga teknik tendangan pemain yang sangat luar biasa.</span></p><p><br></p><p><span style=\"font-family: Impact;\">Pakar sepak bola dan analis teknik tendangan ikut angkat bicara. Mereka menjelaskan bahwa fenomena tersebut bisa terjadi akibat kombinasi teknik tendangan tertentu, putaran bola yang kuat, serta kondisi angin di sekitar stadion. Meski terdengar ilmiah, kejadian ini tetap terasa tidak biasa bagi banyak orang.</span></p><p><br></p><p><span style=\"font-family: Impact;\">Menanggapi viralnya kejadian tersebut, pemain yang mencetak gol mengaku tidak menyangka hasil tendangannya akan menjadi perbincangan luas. Ia menyebut hanya fokus mengarahkan bola ke area gawang dan memanfaatkan peluang sebaik mungkin.</span></p><p><br></p><p><span style=\"font-family: Impact;\">Fenomena “bola ajaib” ini kembali membuktikan bahwa sepak bola selalu menghadirkan kejutan. Tidak hanya soal strategi dan fisik, tetapi juga momen-momen tak terduga yang mampu menghibur dan memikat perhatian publik di seluruh dunia.</span></p>', 'berita_1770388107_6985fa8b6ca8d.jpg', 'Savety', 'draft', 'olahraga, hoax, fake', 0, '2026-02-06 14:28:27', '2026-02-06 14:28:27');

-- --------------------------------------------------------

--
-- Struktur dari tabel `challenges`
--

CREATE TABLE `challenges` (
  `id` int(11) NOT NULL,
  `challenge_code` varchar(50) NOT NULL,
  `challenger_id` int(11) NOT NULL,
  `opponent_id` int(11) NOT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `challenge_date` datetime NOT NULL,
  `expiry_date` datetime NOT NULL,
  `sport_type` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('open','accepted','rejected','expired','completed') DEFAULT 'open',
  `challenger_score` int(11) DEFAULT NULL,
  `opponent_score` int(11) DEFAULT NULL,
  `winner_team_id` int(11) DEFAULT NULL,
  `match_status` varchar(50) DEFAULT NULL,
  `match_duration` varchar(20) DEFAULT NULL,
  `match_official` varchar(100) DEFAULT NULL,
  `match_notes` text DEFAULT NULL,
  `result_entered_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `challenges`
--

INSERT INTO `challenges` (`id`, `challenge_code`, `challenger_id`, `opponent_id`, `venue_id`, `challenge_date`, `expiry_date`, `sport_type`, `notes`, `status`, `challenger_score`, `opponent_score`, `winner_team_id`, `match_status`, `match_duration`, `match_official`, `match_notes`, `result_entered_at`, `created_at`, `updated_at`) VALUES
(12, 'CH202602030C21B3', 46, 39, NULL, '2026-02-11 11:00:00', '2026-02-10 11:00:00', 'LIGA AAFI BATAM U-16 PUTRA 2026', '....', 'completed', 1, 0, 46, 'completed', '90', 'Savety', 'tak da', '2026-02-12 19:03:17', '2026-02-03 12:14:56', '2026-02-12 12:03:17'),
(13, 'CH202602053F0965', 46, 24, NULL, '2026-02-11 14:00:00', '2026-02-10 14:00:00', 'LIGA AAFI BATAM U-16 PUTRA 2026', 'nope', 'completed', 1, 0, 46, 'completed', '90', '', '', '2026-02-12 19:03:28', '2026-02-05 02:26:28', '2026-02-12 12:03:28'),
(15, 'CH20260205180CCE', 14, 46, NULL, '2026-02-11 10:30:00', '2026-02-10 10:30:00', 'LIGA AAFI BATAM U-16 PUTRI 2026', 'Nope', 'completed', 1, 9, 46, 'completed', '90', 'Savety', '', '2026-02-12 19:02:58', '2026-02-05 02:51:29', '2026-02-12 12:02:58'),
(16, 'CH202602058DF109', 21, 15, 2, '2026-02-11 18:00:00', '2026-02-10 18:00:00', 'LIGA AAFI BATAM U-16 PUTRA 2026', 'ppp', 'accepted', 0, 0, NULL, 'coming_soon', '90', '', '', '2026-02-05 20:24:34', '2026-02-05 06:49:44', '2026-02-11 00:13:07'),
(17, 'CH20260206421926', 46, 19, 4, '2026-02-11 11:00:00', '2026-02-10 11:00:00', 'LIGA AAFI BATAM U-16 PUTRA 2026', 'Nope...', 'completed', 1, 0, 46, 'completed', '90', '', '', '2026-02-12 19:03:22', '2026-02-06 14:29:24', '2026-02-12 12:03:22'),
(18, 'CH2026020689F02C', 25, 37, NULL, '2026-02-12 18:00:00', '2026-02-11 18:00:00', 'LIGA AAFI BATAM U-13 PUTRA 2026', 'Tidak Ada', 'accepted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-06 14:30:16', '2026-02-11 00:13:24'),
(19, 'CH20260206F87044', 32, 15, NULL, '2026-02-11 18:00:00', '2026-02-10 18:00:00', 'LIGA AAFI BATAM U-13 PUTRA 2026', 'Nope...', 'accepted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-06 14:31:11', '2026-02-11 00:12:59');

-- --------------------------------------------------------

--
-- Struktur dari tabel `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `registration_status` enum('open','closed') DEFAULT 'open',
  `contact` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `goals`
--

CREATE TABLE `goals` (
  `id` int(11) NOT NULL,
  `match_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `minute` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `lineups`
--

CREATE TABLE `lineups` (
  `id` int(11) NOT NULL,
  `match_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `is_starting` tinyint(1) DEFAULT 1,
  `position` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `lineups`
--

INSERT INTO `lineups` (`id`, `match_id`, `player_id`, `team_id`, `is_starting`, `position`) VALUES
(4, 12, 42, 46, 1, 'FW'),
(5, 15, 43, 46, 1, 'DF'),
(6, 13, 43, 46, 1, 'DF'),
(7, 17, 46, 46, 1, 'FW'),
(8, 17, 45, 46, 1, 'MF'),
(9, 17, 43, 46, 1, 'GK'),
(10, 17, 47, 46, 1, 'FW'),
(11, 17, 44, 46, 1, 'DF'),
(16, 19, 57, 32, 1, 'GK'),
(17, 19, 56, 32, 1, 'FW'),
(18, 19, 58, 32, 1, 'MF'),
(19, 18, 48, 25, 1, 'GK'),
(20, 18, 51, 25, 1, 'FW'),
(21, 18, 49, 25, 1, 'DF'),
(22, 18, 52, 37, 1, 'FW'),
(23, 18, 54, 37, 1, 'FW'),
(24, 18, 53, 37, 1, 'DF'),
(25, 18, 55, 37, 1, 'DF'),
(26, 17, 28, 19, 1, 'GK'),
(27, 17, 27, 19, 1, 'MF'),
(28, 17, 60, 19, 1, 'DF'),
(29, 17, 42, 19, 1, 'FW'),
(30, 17, 59, 19, 1, 'DF');

-- --------------------------------------------------------

--
-- Struktur dari tabel `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `team1_id` int(11) DEFAULT NULL,
  `team2_id` int(11) DEFAULT NULL,
  `score1` int(11) DEFAULT NULL,
  `score2` int(11) DEFAULT NULL,
  `match_date` datetime DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `match_stats`
--

CREATE TABLE `match_stats` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `team1_possession` int(11) DEFAULT 0,
  `team2_possession` int(11) DEFAULT 0,
  `team1_shots_on_target` int(11) DEFAULT 0,
  `team2_shots_on_target` int(11) DEFAULT 0,
  `team1_fouls` int(11) DEFAULT 0,
  `team2_fouls` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `match_stats`
--

INSERT INTO `match_stats` (`id`, `match_id`, `team1_possession`, `team2_possession`, `team1_shots_on_target`, `team2_shots_on_target`, `team1_fouls`, `team2_fouls`, `created_at`, `updated_at`) VALUES
(1, 10, 0, 0, 0, 0, 0, 0, '2026-02-03 04:22:08', '2026-02-03 04:22:08'),
(2, 11, 0, 0, 0, 0, 0, 0, '2026-02-03 12:15:48', '2026-02-03 12:15:48'),
(3, 12, 0, 0, 0, 0, 0, 0, '2026-02-03 12:16:23', '2026-02-03 12:16:23'),
(4, 13, 0, 0, 0, 0, 0, 0, '2026-02-05 02:32:37', '2026-02-05 02:32:37'),
(5, 15, 0, 0, 0, 0, 0, 0, '2026-02-06 07:07:39', '2026-02-06 07:07:39'),
(6, 16, 0, 0, 0, 0, 0, 0, '2026-02-05 12:39:34', '2026-02-05 12:39:34'),
(7, 17, 0, 0, 0, 0, 0, 0, '2026-02-09 07:36:40', '2026-02-09 07:36:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `players`
--

CREATE TABLE `players` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `jersey_number` int(11) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('L','P') DEFAULT NULL,
  `nisn` varchar(20) DEFAULT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `sport_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `birth_place` varchar(100) DEFAULT NULL,
  `height` int(3) DEFAULT NULL,
  `weight` int(3) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `dominant_foot` enum('kiri','kanan','kedua') DEFAULT NULL,
  `position_detail` varchar(100) DEFAULT NULL,
  `dribbling` int(2) DEFAULT 5,
  `technique` int(2) DEFAULT 5,
  `speed` int(2) DEFAULT 5,
  `juggling` int(2) DEFAULT 5,
  `shooting` int(2) DEFAULT 5,
  `setplay_position` int(2) DEFAULT 5,
  `passing` int(2) DEFAULT 5,
  `control` int(2) DEFAULT 5,
  `ktp_image` varchar(255) DEFAULT NULL,
  `kk_image` varchar(255) DEFAULT NULL,
  `birth_cert_image` varchar(255) DEFAULT NULL,
  `diploma_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `players`
--

INSERT INTO `players` (`id`, `name`, `slug`, `photo`, `team_id`, `jersey_number`, `position`, `birth_date`, `gender`, `nisn`, `nik`, `sport_type`, `created_at`, `birth_place`, `height`, `weight`, `email`, `phone`, `nationality`, `street`, `city`, `province`, `postal_code`, `country`, `dominant_foot`, `position_detail`, `dribbling`, `technique`, `speed`, `juggling`, `shooting`, `setplay_position`, `passing`, `control`, `ktp_image`, `kk_image`, `birth_cert_image`, `diploma_image`, `status`, `updated_at`) VALUES
(27, 'Adi Nugroho', 'adi-nugroho-oxygen', 'player_1770385391_6985efef65de8.jpeg', 19, 11, 'MF', '2008-05-12', 'L', '4444444444', '4444444444444444', 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-03 04:21:17', 'Batam', 175, 50, 'adi@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'GK', 6, 3, 1, 3, 9, 7, 9, 7, NULL, 'kk_1770768176_698bc73049b0e.jpg', NULL, NULL, 'active', '2026-02-11 00:02:56'),
(28, 'Maarove ', 'budi-santoso-oxygen', 'player_1770385373_6985efdda5248.jpeg', 19, 2, 'GK', '2008-01-23', 'L', '5555555555', '5555555555555555', 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-03 04:21:17', 'Batam', 160, 43, 'maarove@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', NULL, 7, 7, 8, 8, 2, 3, 2, 10, 'ktp_1770337939_6985369304de8.jpg', 'kk_1770337939_6985369304f38.jpg', 'akte_1770337939_69853693050bb.png', 'ijazah_1770337939_69853693051a9.jpg', 'active', '2026-02-11 00:03:08'),
(42, 'Mardinata ', 'savety-000ffd', 'player_1770385360_6985efd0d2963.jpeg', 19, 45, 'FW', '2007-04-23', 'L', '3333333333', '3333333333333333', 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-03 14:14:55', 'Batam', 160, 45, 'Mardinata@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'FW', 10, 10, 10, 10, 10, 10, 10, 10, 'ktp_1770337638_69853566bb1d2.jpg', 'kk_1770768157_698bc71d87ba5.jpg', NULL, 'ijazah_1770337638_69853566bb6de.jpg', 'active', '2026-02-11 00:02:37'),
(43, 'Bayu Cahyo', 'ricky-321fc1', 'player_1770898464_698dc420acbbd.jpeg', 46, 88, 'GK', '2008-06-23', 'L', '2222222222', '2222222222222222', 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-03 14:47:33', 'Batam', 165, 45, 'bayu@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kanan', 'GK', 5, 5, 5, 5, 5, 5, 5, 5, NULL, 'kk_1770898464_698dc420acd05.jpg', 'akte_1770337474_698534c288267.png', 'ijazah_1770337474_698534c288405.jpg', 'active', '2026-02-12 12:14:24'),
(44, 'Ricky ', 'ricky-1770293507', 'player_1770337352_698534487248c.jpeg', 46, 99, 'DF', '2009-03-18', 'L', '1111111111', '1111111111111111', 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-05 12:11:47', 'Batam ', 165, 46, 'rickyyyyy@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'MF', 3, 4, 10, 10, 7, 8, 1, 6, 'ktp_1770337277_698533fd75766.jpg', 'kk_1770337196_698533ac74d37.jpg', 'akte_1770337196_698533ac95463.png', 'ijazah_1770337196_698533ac9564c.jpg', 'active', '2026-02-11 00:03:42'),
(45, 'Hendro', 'hendro-1770384998', 'player_1770384998_6985ee6678502.jpeg', 46, 67, 'MF', '2008-02-06', 'L', '6666666666', '6666666666666666', 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-06 13:36:38', 'Batam', 165, 43, 'hendro@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kiri', 'MF', 3, 3, 1, 10, 7, 8, 9, 2, 'ktp_1770384998_6985ee6678655.jpg', 'kk_1770384998_6985ee6678728.jpg', 'akte_1770384998_6985ee6678867.png', '', 'active', '2026-02-10 12:54:54'),
(46, 'David', 'david-1770385148', 'player_1770385148_6985eefcb8140.jpeg', 46, 88, 'FW', '2007-09-23', 'L', '7777777777', '7777777777777777', 'Futsal', '2026-02-06 13:39:08', 'Batam', 185, 78, 'david@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'FW', 10, 10, 10, 10, 10, 10, 10, 10, 'ktp_1770385148_6985eefcb8287.jpg', '', '', 'ijazah_1770385148_6985eefcb836e.jpg', 'inactive', '2026-02-09 14:14:29'),
(47, 'Tian', 'tian-1770385333', 'player_1770385333_6985efb5caedc.jpeg', 46, 97, 'FW', '2008-12-12', 'L', '8888888888', '8888888888888888', 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-06 13:42:13', 'Batam', 165, 43, 'tian@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'FW', 5, 5, 5, 5, 5, 5, 5, 5, 'ktp_1770385333_6985efb5cafe2.jpg', 'kk_1770768213_698bc7557a851.jpg', '', '', 'active', '2026-02-11 00:03:33'),
(48, 'Nichol', 'nichol-1770385529', 'player_1770385529_6985f0794792e.jpeg', 25, 34, 'GK', '2006-04-12', 'L', '9999999999', '9999999999999999', 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-06 13:45:29', 'Batam', 167, 45, 'nicholas@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kanan', 'GK', 4, 3, 2, 2, 8, 8, 9, 10, '', 'kk_1770385529_6985f07947b37.jpg', '', 'ijazah_1770385529_6985f07947c98.jpg', 'active', '2026-02-11 00:02:13'),
(49, 'John', 'john-1770385627', 'player_1770385627_6985f0db7b46c.jpeg', 25, 98, 'DF', '2007-02-06', 'L', '1010101010', '1010101010101010', 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-06 13:47:07', 'Batam', 167, 54, 'john@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kiri', 'DF', 4, 4, 5, 5, 7, 5, 5, 3, 'ktp_1770385627_6985f0db7b731.jpg', 'kk_1770385627_6985f0db7bae7.jpg', 'akte_1770385627_6985f0db7bddc.png', 'ijazah_1770385627_6985f0db7c0fd.jpg', 'active', '2026-02-11 00:02:00'),
(51, 'Hubner', 'hubner-1770385801', 'player_1770385801_6985f189d5df3.jpeg', 25, 67, 'FW', '2008-08-23', 'L', '1212121212', '1212121212121212', 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-06 13:50:01', 'Batam', 167, 45, 'hubner@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'FW', 7, 3, 3, 4, 2, 6, 8, 10, 'ktp_1770385801_6985f189d6075.jpg', 'kk_1770385801_6985f189d62d4.jpg', 'akte_1770385801_6985f189d645f.png', 'ijazah_1770385801_6985f189d6657.jpg', 'active', '2026-02-11 00:01:53'),
(52, 'Bayu AJ', 'fariz-1770385942', 'player_1770385942_6985f21634cfe.jpeg', 37, 23, 'FW', '2008-02-15', 'L', '1313131313', '1313131313131313', 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-06 13:52:22', 'Batam', 179, 76, 'fariz@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kanan', 'FW', 4, 3, 3, 2, 10, 10, 8, 5, 'ktp_1770385942_6985f21634e85.jpg', 'kk_1770385942_6985f21634f85.jpg', 'akte_1770385942_6985f21639828.png', 'ijazah_1770385942_6985f21639979.jpg', 'active', '2026-02-11 00:01:40'),
(53, 'Adnan', 'adnan-1770386032', 'player_1770386032_6985f270c999e.jpeg', 37, 43, 'DF', '2006-01-30', 'L', '1414141414', '1414141414141414', 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-06 13:53:52', 'Batam', 163, 50, 'adnan@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'DF', 5, 5, 5, 5, 5, 5, 5, 5, 'ktp_1770386032_6985f270c9aa9.jpg', 'kk_1770386032_6985f270c9b62.jpg', 'akte_1770386032_6985f270c9d27.png', 'ijazah_1770386032_6985f270c9e18.jpg', 'active', '2026-02-11 00:01:34'),
(54, 'Farhan', 'farhan-1770386158', 'player_1770386158_6985f2ee39b5c.jpeg', 37, 24, 'FW', '2007-08-23', 'L', '1515151515', '1515151515151515', 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-06 13:55:58', 'Batam', 158, 45, 'farhan@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'FW', 2, 4, 4, 3, 10, 10, 10, 10, 'ktp_1770386158_6985f2ee39cdd.jpg', 'kk_1770386158_6985f2ee39dbb.jpg', 'akte_1770386158_6985f2ee39f1e.png', 'ijazah_1770386158_6985f2ee3a03d.jpg', 'active', '2026-02-11 00:01:26'),
(55, 'Rahman', 'rahman-1770386255', 'player_1770386255_6985f34f01265.jpeg', 37, 76, 'DF', '2008-07-23', 'L', '1616161616', '1616161616161616', 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-06 13:57:35', 'Batam', 180, 67, 'rahman@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '', 'Indonesia', 'kedua', 'DF', 3, 4, 3, 3, 9, 7, 9, 8, 'ktp_1770386255_6985f34f015c2.jpg', 'kk_1770768079_698bc6cfefce9.jpg', '', 'ijazah_1770386255_6985f34f0799a.jpg', 'active', '2026-02-11 00:01:19'),
(56, 'Kall', 'kall-1770386357', 'player_1770386357_6985f3b5cd179.jpeg', 32, 12, 'FW', '2008-02-06', 'L', '1717171717', '1717171717171717', 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-06 13:59:17', 'Batam', 167, 48, 'kall@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kiri', 'FW', 10, 10, 10, 10, 6, 10, 10, 5, 'ktp_1770386357_6985f3b5cd308.jpg', 'kk_1770386357_6985f3b5cd434.jpg', 'akte_1770386357_6985f3b5cd5ce.png', 'ijazah_1770386357_6985f3b5cd734.jpg', 'active', '2026-02-11 00:00:57'),
(57, 'Guss', 'guss-1770386443', 'player_1770386443_6985f40bebaf4.jpeg', 32, 10, 'GK', '2008-02-23', 'L', '1818181818', '1818181818181818', 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-06 14:00:43', 'Batam', 178, 45, 'guss@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'GK', 6, 9, 1, 8, 7, 5, 3, 0, 'ktp_1770386443_6985f40bebc98.jpg', 'kk_1770768048_698bc6b0bae0f.jpg', '', '', 'active', '2026-02-11 00:00:48'),
(58, 'Miska', 'miska-1770386541', 'player_1770386541_6985f46db96e4.jpeg', 32, 88, 'MF', '2006-06-03', 'L', '1919191919', '1919191919191919', 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-06 14:02:21', 'Batam', 154, 45, 'miska@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'MF', 6, 2, 7, 7, 10, 5, 2, 3, 'ktp_1770386541_6985f46db97e2.jpg', 'kk_1770386541_6985f46db9a3f.jpg', 'akte_1770386541_6985f46db9b46.png', 'ijazah_1770386541_6985f46db9d9e.jpg', 'active', '2026-02-11 00:00:28'),
(59, 'Marcell', 'marcell-1770386641', 'player_1770386666_6985f4eace31f.jpeg', 19, 78, 'DF', '2008-02-06', 'L', '2020202020', '2020202020202020', 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-06 14:04:01', 'Batam', 178, 67, 'marcell@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kiri', 'DF', 8, 6, 7, 10, 3, 1, 3, 7, 'ktp_1770386641_6985f4d157547.jpg', 'kk_1770768018_698bc6922ae92.jpg', 'akte_1770386641_6985f4d157635.png', '', 'active', '2026-02-11 00:00:18'),
(60, 'Tio', 'tio-1770386743', 'player_1770386743_6985f537e3074.jpeg', 19, 23, 'DF', '2008-07-23', 'L', '2121212121', '2121212121212121', 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-06 14:05:43', 'Batam', 167, 55, 'tio@gmail.com', '087898954988', 'Indonesia', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 'kedua', 'DF', 10, 5, 8, 5, 10, 4, 5, 1, 'ktp_1770386743_6985f537e31fe.jpg', 'kk_1770386743_6985f537e33ae.jpg', 'akte_1770386743_6985f537e34dd.png', 'ijazah_1770386743_6985f537e35e1.jpg', 'active', '2026-02-10 23:59:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `staff_certificates`
--

CREATE TABLE `staff_certificates` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `certificate_name` varchar(200) NOT NULL,
  `certificate_file` varchar(255) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `issuing_authority` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `staff_certificates`
--

INSERT INTO `staff_certificates` (`id`, `staff_id`, `certificate_name`, `certificate_file`, `issue_date`, `issuing_authority`, `created_at`) VALUES
(6, 6, 'Training', 'cert_1770387023_6985f64fcd8c1.jpg', '2026-02-03', 'STEM ', '2026-02-06 14:10:23'),
(7, 7, 'Training', 'cert_1770387113_6985f6a9df589.png', '2025-07-16', 'STEM ', '2026-02-06 14:11:54'),
(8, 8, 'Training', 'cert_1770387190_6985f6f6098bc.jpg', '2024-03-06', 'STEM ', '2026-02-06 14:13:10'),
(9, 9, 'Training', 'cert_1770387265_6985f7410e7fa.png', '2026-02-06', 'STEM ', '2026-02-06 14:14:25'),
(10, 9, 'Training', 'cert_1770387283_6985f753f36bb.jpg', '2026-02-06', 'STEM', '2026-02-06 14:14:44');

-- --------------------------------------------------------

--
-- Struktur dari tabel `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `alias` varchar(100) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `manager` varchar(100) DEFAULT NULL,
  `coach` varchar(100) DEFAULT NULL,
  `basecamp` varchar(255) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `established_year` date DEFAULT NULL,
  `uniform_color` varchar(100) DEFAULT NULL,
  `sport_type` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `teams`
--

INSERT INTO `teams` (`id`, `name`, `alias`, `slug`, `logo`, `manager`, `coach`, `basecamp`, `contact`, `created_at`, `established_year`, `uniform_color`, `sport_type`, `is_active`, `updated_at`) VALUES
(14, 'BJFA', 'Bjfa', NULL, 'team_1769587183_6979c1ef8127d.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 00:59:43', '2026-01-01', 'Biru', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-10 14:17:35'),
(15, 'BR', 'Br', NULL, 'team_1769587231_6979c21fddc17.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:00:31', '2026-01-01', 'Hitam', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:12:38'),
(16, 'BSC', 'Bsc', NULL, 'team_1769587276_6979c24c8abcb.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:01:16', '2026-01-01', 'Putih', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:12:22'),
(17, 'DULIM', 'Dulim', NULL, 'team_1769587332_6979c284ace20.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:02:12', '2026-01-01', 'Ungu', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:12:30'),
(18, 'DUPUL', 'Dupul', NULL, 'team_1769587405_6979c2cdaa38a.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:03:25', '2026-01-01', 'Putih', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:12:09'),
(19, 'FAFAGE', 'Fafage', NULL, 'team_1769587466_6979c30a73084.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:04:26', '2026-01-01', 'Biru', 'LIGA AAFI BATAM U-16 PUTRA 2026', 1, '2026-02-11 00:12:02'),
(20, 'GESYA', 'Gesya', NULL, 'team_1769587509_6979c33567521.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:05:09', '2026-01-01', 'Biru', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:11:57'),
(21, 'GRFA', 'Grfa', NULL, 'team_1769587560_6979c368de1df.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:06:00', '2026-01-01', 'Kuning', 'LIGA AAFI BATAM U-16 PUTRI 2026', 1, '2026-02-11 00:11:50'),
(22, 'GSP', 'Gsp', NULL, 'team_1769587601_6979c391edf8a.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:06:41', '2026-01-01', 'Emas', 'LIGA AAFI BATAM U-16 PUTRA 2026', 1, '2026-02-11 00:11:43'),
(23, 'IMPERAL', 'Imperal', NULL, 'team_1769587651_6979c3c348f76.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:07:31', '2026-01-01', 'Hitam', 'LIGA AAFI BATAM U-16 PUTRA 2026', 1, '2026-02-11 00:11:38'),
(24, 'KFA', 'Kfa', NULL, 'team_1769587702_6979c3f653703.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:08:22', '2026-01-01', 'Merah', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:11:27'),
(25, 'KINGS', 'Kings', NULL, 'team_1769587746_6979c42268874.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:09:06', '2026-01-01', 'Pink', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:11:21'),
(26, 'KMC', 'Kmc', NULL, 'team_1769587786_6979c44a3bbee.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:09:46', '2026-01-01', 'Kuning', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:11:12'),
(27, 'LSFA', 'Lsfa', NULL, 'team_1769587830_6979c476ca496.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:10:30', '2026-01-01', 'Merah', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:11:02'),
(28, 'MANTANG', 'Mantang', NULL, 'team_1769587878_6979c4a65c8eb.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:11:18', '2026-01-01', 'Hijau', 'LIGA AAFI BATAM U-16 PUTRA 2026', 1, '2026-02-11 00:10:53'),
(29, 'MEKASETA', 'Mekaseta', NULL, 'team_1769587923_6979c4d337fc2.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:12:03', '2026-01-01', 'Oren', 'LIGA AAFI BATAM U-16 PUTRI 2026', 1, '2026-02-11 00:10:46'),
(30, 'NAMOR', 'Namor', NULL, 'team_1769587960_6979c4f8240f3.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:12:40', '2026-01-01', 'Putih', 'LIGA AAFI BATAM U-16 PUTRA 2026', 1, '2026-02-11 00:10:37'),
(31, 'NFA', 'Nfa', NULL, 'team_1769587996_6979c51c30c42.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:13:16', '2026-01-01', 'Hitam', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:10:32'),
(32, 'OXYGEN', 'Oxygen', NULL, 'team_1769588031_6979c53fbd4bc.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:13:51', '2026-01-01', 'Hijau', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:10:25'),
(33, 'PATRIOT', 'Patriot', NULL, 'team_1769588091_6979c57bbbde6.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:14:51', '2026-01-01', 'Putih', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:10:16'),
(34, 'PROGRESS PLUS', 'Progress Plus', NULL, 'team_1769588144_6979c5b0e0b5b.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:15:44', '2026-01-01', 'Hitam', 'LIGA AAFI BATAM U-16 PUTRI 2026', 1, '2026-02-11 00:10:09'),
(35, 'SMP 1 TPI', 'Smp 1 Tpi', NULL, 'team_1769588184_6979c5d8b3d1b.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:16:24', '2026-01-01', 'Oren', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:10:03'),
(36, 'TANGO', 'Tango', NULL, 'team_1769588219_6979c5fb4c63f.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:16:59', '2026-01-01', 'Kuning', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:09:53'),
(37, 'TIGER', 'Tiger', NULL, 'team_1769588276_6979c634250f2.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:17:56', '2026-01-01', 'Emas', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:09:45'),
(38, 'TIMASA', 'Timasa', NULL, 'team_1769588316_6979c65cc9cfc.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:18:36', '2026-01-01', 'Hitam', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:09:33'),
(39, 'TIPUL', 'Tipul', NULL, 'team_1769588350_6979c67e286f0.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:19:10', '2026-01-01', 'Biru', 'LIGA AAFI BATAM U-16 PUTRA 2026', 1, '2026-02-11 00:09:29'),
(40, 'TMK', 'Tmk', NULL, 'team_1769588386_6979c6a20b646.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:19:46', '2026-01-01', 'Hitam', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:09:20'),
(41, 'TOPAS', 'Topas', NULL, 'team_1769588425_6979c6c9cfc0d.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:20:25', '2026-01-01', 'Biru', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:09:15'),
(42, 'TUNAS HARAPAN', 'Tunas Harapan', NULL, 'team_1769588474_6979c6fa000a0.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:21:14', '2026-01-01', 'Putih', 'LIGA AAFI BATAM U-16 PUTRA 2026', 1, '2026-02-11 00:08:57'),
(43, 'VITANZA', 'Vitanza', NULL, 'team_1769588513_6979c7216e1f1.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:21:53', '2026-01-01', 'Abu-Abu', 'LIGA AAFI BATAM U-16 PUTRI 2026', 1, '2026-02-11 00:08:49'),
(44, 'YILDIZ', 'Yildiz', NULL, 'team_1769588557_6979c74de0478.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:22:37', '2026-01-01', 'Kuning', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:08:44'),
(45, 'ZAAP', 'Zaap', NULL, 'team_1769588595_6979c77309121.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-01-28 01:23:15', '2026-01-01', 'Hitam-Putih', 'LIGA AAFI BATAM U-16 PUTRI 2026', 1, '2026-02-11 00:09:07'),
(46, 'BATAM SPORT', 'Batam Sport', NULL, 'team_1770120538_6981e55a7f10f.jpeg', NULL, 'Savety', 'FootSquare', NULL, '2026-02-03 12:08:58', '2026-01-01', 'Merah', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-11 00:08:32'),
(47, 'BINA PRATAMA', 'Bina Pratama', NULL, 'team_1770120586_6981e58aeb275.jpeg', NULL, 'Savety A', 'FootSquare', NULL, '2026-02-03 12:09:46', '2015-06-09', 'Hitam', 'LIGA AAFI BATAM U-13 PUTRA 2026', 1, '2026-02-10 15:48:32');

-- --------------------------------------------------------

--
-- Struktur dari tabel `team_events`
--

CREATE TABLE `team_events` (
  `team_id` int(11) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `team_events`
--

INSERT INTO `team_events` (`team_id`, `event_name`, `created_at`) VALUES
(14, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-10 14:17:35'),
(14, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-10 14:17:35'),
(14, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-10 14:17:35'),
(15, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:12:38'),
(15, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:12:38'),
(15, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:12:38'),
(16, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:12:22'),
(16, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:12:22'),
(16, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:12:22'),
(17, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:12:30'),
(17, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:12:30'),
(18, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:12:09'),
(18, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:12:09'),
(19, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:12:02'),
(19, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:12:02'),
(20, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:11:57'),
(20, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:11:57'),
(20, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:11:57'),
(21, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:11:50'),
(22, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:11:43'),
(22, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:11:43'),
(23, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:11:38'),
(24, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:11:27'),
(24, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:11:27'),
(25, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:11:21'),
(25, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:11:21'),
(25, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:11:21'),
(26, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:11:12'),
(26, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:11:12'),
(26, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:11:12'),
(27, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:11:02'),
(27, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:11:02'),
(27, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:11:02'),
(28, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:10:53'),
(28, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:10:53'),
(29, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:10:46'),
(30, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:10:38'),
(31, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:10:32'),
(31, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:10:32'),
(32, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:10:25'),
(32, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:10:25'),
(32, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:10:25'),
(33, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:10:16'),
(33, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:10:16'),
(33, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:10:16'),
(34, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:10:09'),
(35, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:10:03'),
(35, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:10:03'),
(36, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:09:53'),
(36, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:09:53'),
(37, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:09:46'),
(37, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:09:46'),
(37, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:09:46'),
(38, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:09:33'),
(39, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:09:29'),
(39, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:09:29'),
(40, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:09:20'),
(40, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:09:20'),
(40, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:09:20'),
(41, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:09:15'),
(41, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:09:15'),
(42, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:08:57'),
(42, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:08:57'),
(43, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:08:49'),
(44, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:08:45'),
(44, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:08:45'),
(45, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:09:07'),
(46, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-11 00:08:32'),
(46, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-11 00:08:32'),
(46, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-11 00:08:32'),
(47, 'LIGA AAFI BATAM U-13 PUTRA 2026', '2026-02-10 15:48:32'),
(47, 'LIGA AAFI BATAM U-16 PUTRA 2026', '2026-02-10 15:48:32'),
(47, 'LIGA AAFI BATAM U-16 PUTRI 2026', '2026-02-10 15:48:32');

-- --------------------------------------------------------

--
-- Struktur dari tabel `team_staff`
--

CREATE TABLE `team_staff` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `birth_place` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Indonesia',
  `is_active` tinyint(1) DEFAULT 1,
  `team_id` int(11) DEFAULT NULL,
  `position` enum('manager','headcoach','coach','goalkeeper_coach','medic','official') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `team_staff`
--

INSERT INTO `team_staff` (`id`, `name`, `photo`, `birth_place`, `birth_date`, `address`, `city`, `province`, `postal_code`, `country`, `is_active`, `team_id`, `position`, `email`, `phone`, `created_at`, `updated_at`) VALUES
(5, 'Coach Batam Sport', 'uploads/staff/staff_1770897515_698dc06b8d2cd.jpeg', 'Batam', '1981-08-27', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 0, 46, 'official', 'coachbatamsport@gmail.com', '087898954988', '2026-02-03 12:13:54', '2026-02-12 13:23:51'),
(6, 'Coach Fafage', 'uploads/staff/staff_1770387023_6985f64fcd6e5.jpeg', 'Batam', '1988-04-23', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 1, 19, 'manager', 'coachfafage@gmail.com', '087898954988', '2026-02-06 14:10:23', '2026-02-06 14:10:23'),
(7, 'Coach Kings', 'uploads/staff/staff_1770387113_6985f6a9df15b.jpeg', 'Batam', '1972-09-12', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 1, 25, 'headcoach', 'coachkings@gmail.com', '087898954988', '2026-02-06 14:11:53', '2026-02-06 14:11:53'),
(8, 'Coach Tiger', 'uploads/staff/staff_1770387189_6985f6f5b723e.jpeg', 'Batam', '1967-09-23', 'Batam Centre', '', 'Kepulauan Riau', '7677', 'Indonesia', 1, 37, 'goalkeeper_coach', 'coachtiger@gmail.com', '087898954988', '2026-02-06 14:13:10', '2026-02-06 14:13:10'),
(9, 'Coach Oxygen', 'uploads/staff/staff_1770387265_6985f7410e596.jpeg', 'Batam', '1985-02-16', 'Batam Centre', 'Batam', 'Kepulauan Riau', '7677', 'Indonesia', 1, 32, 'medic', 'coachoxygen@gmail.com', '087898954988', '2026-02-06 14:14:25', '2026-02-06 14:14:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transfers`
--

CREATE TABLE `transfers` (
  `id` int(11) NOT NULL,
  `player_id` int(11) DEFAULT NULL,
  `from_team_id` int(11) DEFAULT NULL,
  `to_team_id` int(11) DEFAULT NULL,
  `transfer_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transfers`
--

INSERT INTO `transfers` (`id`, `player_id`, `from_team_id`, `to_team_id`, `transfer_date`) VALUES
(1, 27, 47, 25, '2026-02-03'),
(2, 42, 46, 22, '2026-02-05');

-- --------------------------------------------------------

--
-- Struktur dari tabel `venues`
--

CREATE TABLE `venues` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  `facilities` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `venues`
--

INSERT INTO `venues` (`id`, `name`, `location`, `capacity`, `facilities`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 'GOR Buluh Indah', 'Jl. Buluh Indah', 1000, 'Toilet', 1, '2026-01-28 03:49:45', '2026-02-06 14:20:32'),
(3, 'Lapangan Merdeka', 'Jl. Merdeka No. 67', 800, 'Makan + Minum + Toilet', 1, '2026-01-28 03:49:45', '2026-02-06 14:20:44'),
(4, 'Stadion Utama', 'Jl. Stadion No. 1', 5000, 'All Completed', 1, '2026-01-28 03:49:45', '2026-02-06 14:20:53');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_admin_team` (`team_id`);

--
-- Indeks untuk tabel `berita`
--
ALTER TABLE `berita`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_views` (`views`);

--
-- Indeks untuk tabel `challenges`
--
ALTER TABLE `challenges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `challenge_code` (`challenge_code`),
  ADD KEY `idx_challenge_code` (`challenge_code`),
  ADD KEY `idx_challenger_id` (`challenger_id`),
  ADD KEY `idx_opponent_id` (`opponent_id`),
  ADD KEY `idx_venue_id` (`venue_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_challenge_date` (`challenge_date`),
  ADD KEY `idx_sport_type` (`sport_type`),
  ADD KEY `winner_team_id` (`winner_team_id`);

--
-- Indeks untuk tabel `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indeks untuk tabel `goals`
--
ALTER TABLE `goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `match_id` (`match_id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `team_id` (`team_id`);

--
-- Indeks untuk tabel `lineups`
--
ALTER TABLE `lineups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `match_id` (`match_id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `team_id` (`team_id`);

--
-- Indeks untuk tabel `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `team1_id` (`team1_id`),
  ADD KEY `team2_id` (`team2_id`);

--
-- Indeks untuk tabel `match_stats`
--
ALTER TABLE `match_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_match_id` (`match_id`);

--
-- Indeks untuk tabel `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `nik` (`nik`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `idx_player_name` (`name`),
  ADD KEY `idx_player_team` (`team_id`),
  ADD KEY `idx_player_nik` (`nik`);

--
-- Indeks untuk tabel `staff_certificates`
--
ALTER TABLE `staff_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_id` (`staff_id`);

--
-- Indeks untuk tabel `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `team_events`
--
ALTER TABLE `team_events`
  ADD PRIMARY KEY (`team_id`,`event_name`);

--
-- Indeks untuk tabel `team_staff`
--
ALTER TABLE `team_staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `idx_team_id` (`team_id`),
  ADD KEY `idx_position` (`position`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indeks untuk tabel `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `from_team_id` (`from_team_id`),
  ADD KEY `to_team_id` (`to_team_id`);

--
-- Indeks untuk tabel `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `berita`
--
ALTER TABLE `berita`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `challenges`
--
ALTER TABLE `challenges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT untuk tabel `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `goals`
--
ALTER TABLE `goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `lineups`
--
ALTER TABLE `lineups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT untuk tabel `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `match_stats`
--
ALTER TABLE `match_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `players`
--
ALTER TABLE `players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT untuk tabel `staff_certificates`
--
ALTER TABLE `staff_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT untuk tabel `team_staff`
--
ALTER TABLE `team_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `venues`
--
ALTER TABLE `venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `admin_users`
--
ALTER TABLE `admin_users`
  ADD CONSTRAINT `fk_admin_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `challenges`
--
ALTER TABLE `challenges`
  ADD CONSTRAINT `challenges_ibfk_1` FOREIGN KEY (`challenger_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `challenges_ibfk_2` FOREIGN KEY (`opponent_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `challenges_ibfk_3` FOREIGN KEY (`winner_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `challenges_ibfk_4` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `goals`
--
ALTER TABLE `goals`
  ADD CONSTRAINT `goals_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `challenges` (`id`),
  ADD CONSTRAINT `goals_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `goals_ibfk_3` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Ketidakleluasaan untuk tabel `lineups`
--
ALTER TABLE `lineups`
  ADD CONSTRAINT `lineups_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `challenges` (`id`),
  ADD CONSTRAINT `lineups_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `lineups_ibfk_3` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Ketidakleluasaan untuk tabel `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`team1_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `matches_ibfk_3` FOREIGN KEY (`team2_id`) REFERENCES `teams` (`id`);

--
-- Ketidakleluasaan untuk tabel `players`
--
ALTER TABLE `players`
  ADD CONSTRAINT `players_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Ketidakleluasaan untuk tabel `staff_certificates`
--
ALTER TABLE `staff_certificates`
  ADD CONSTRAINT `staff_certificates_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `team_staff` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `team_events`
--
ALTER TABLE `team_events`
  ADD CONSTRAINT `fk_team_events_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `team_staff`
--
ALTER TABLE `team_staff`
  ADD CONSTRAINT `team_staff_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`);

--
-- Ketidakleluasaan untuk tabel `transfers`
--
ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`from_team_id`) REFERENCES `teams` (`id`),
  ADD CONSTRAINT `transfers_ibfk_3` FOREIGN KEY (`to_team_id`) REFERENCES `teams` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
