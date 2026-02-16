# Migrations

Run database migrations from this directory using CLI during deployment.
Do not expose migration or schema-debug scripts in public web routes.

## Required migration for two-half lineups

- File: `migration_add_half_column_to_lineups.sql`
- Apply this migration before deploying code that writes `lineups.half` (for example `pelatih/match_lineup.php`).

Example command:

```bash
mysql -u <user> -p <database_name> < migrations/migration_add_half_column_to_lineups.sql
```
