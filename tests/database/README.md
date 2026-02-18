# Specific Integration-Test Database Readme

Project ini menggunakan database khusus untuk integration test:

- Nama database: `futscore_test`
- Script setup/reset: `tests/scripts/reset_test_db.php`
- Schema: `tests/database/schema.sql`
- Data seed: `tests/database/seed.sql`

## Alasan

Integration test tidak boleh dijalankan pada `futscore_db` (atau database non-test lainnya).
Script reset akan langsung gagal jika `DB_NAME` bukan tepat `futscore_test`.

## Penggunaan lokal

Atur environment variable jika diperlukan:

```bash
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=futscore_test
DB_USER=root
DB_PASS=
```

Jalankan unit test:

```bash
composer test:unit
```

Reset DB + jalankan integration test:

```bash
composer test:integration
```
