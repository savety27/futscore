-- Table untuk berita
CREATE TABLE berita (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    judul VARCHAR(200) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    konten LONGTEXT NOT NULL,
    gambar VARCHAR(255),
    penulis VARCHAR(100),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    tag VARCHAR(255),
    views INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_created_at (created_at),
    KEY idx_views (views)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
