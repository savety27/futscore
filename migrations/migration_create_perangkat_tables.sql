-- Migration: Create perangkat and perangkat_licenses tables
-- Date: 2026-02-21

CREATE TABLE IF NOT EXISTS perangkat (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    no_ktp VARCHAR(50) NOT NULL,
    birth_place VARCHAR(100) NULL,
    age DATE NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    postal_code VARCHAR(10) NULL,
    country VARCHAR(100) NULL DEFAULT 'Indonesia',
    photo VARCHAR(255) NULL,
    ktp_photo VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_perangkat_no_ktp (no_ktp),
    INDEX idx_perangkat_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS perangkat_licenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    perangkat_id INT UNSIGNED NOT NULL,
    license_name VARCHAR(255) NOT NULL,
    license_file VARCHAR(255) NOT NULL,
    issuing_authority VARCHAR(255) NULL,
    issue_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_perangkat_license_perangkat
        FOREIGN KEY (perangkat_id) REFERENCES perangkat(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
