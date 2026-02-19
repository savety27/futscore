# Specific Integration-Test Database Readme

Project ini menggunakan database khusus untuk integration test:

- Nama database: `futscore_test`
- Script setup/reset: `tests/scripts/reset_test_db.php`
- Schema source: `migrations/futscore_db (8).sql`
- Schema patch: `tests/database/schema_patch.sql`
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

## Catatan sinkronisasi schema

Reset integration test menggunakan baseline schema dump (`migrations/futscore_db (8).sql`), lalu schema patch (`tests/database/schema_patch.sql`), lalu seed data.
Migration historis (`migration_*.sql` / `fix_*.sql`) tetap tidak direplay saat reset untuk menghindari konflik schema ganda.
