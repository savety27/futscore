# Suggested Commands for Alvetrix

## Development
- **Install Dependencies**: `composer install`
- **Reset Test Database**: `php tests/scripts/reset_test_db.php` (or `composer db:test:reset`)

## Testing
- **Run All Tests**: `composer test` or `phpunit -c phpunit.xml`
- **Run Integration Tests**: `composer test:integration` or `phpunit -c phpunit.integration.xml`
- **Run Specific Test**: `phpunit tests/PathToTest.php`

## Linting/Formatting
(No explicit linting commands found in `composer.json`, but PSR standards are recommended for PHP).
