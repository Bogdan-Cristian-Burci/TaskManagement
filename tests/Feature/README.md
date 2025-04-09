# API Tests

This directory contains automated tests for our API endpoints. These tests ensure that our API behaves as expected and remains stable as the codebase evolves.

## Sprint API Tests

The Sprint API tests verify the functionality of our Sprint-related endpoints. These endpoints allow users to manage sprints, including listing, creating, updating, starting, completing, and associating tasks with sprints.

### Test Files

1. **SprintRoutesTest.php**
   - Ensures that all Sprint API routes require proper authentication
   - Tests that unauthenticated access is properly denied (401 or 403 responses)
   - Verifies security of all major Sprint endpoints

2. **SimplifiedSprintApiTest.php**
   - Tests the existence and basic functionality of all Sprint API routes
   - Verifies proper status codes for authenticated requests
   - Ensures routes are correctly registered and accessible

### Running the Tests

To run all Sprint API tests:

```bash
php artisan test tests/Feature/SimplifiedSprintApiTest.php tests/Feature/SprintRoutesTest.php
```

To run a specific test file:

```bash
php artisan test tests/Feature/SprintRoutesTest.php
```

## Test Environment

The tests use an in-memory SQLite database for better performance and isolation. The database configuration for tests is defined in the `phpunit.xml` file.

## Troubleshooting

If you encounter issues with the test database:
1. Ensure your `phpunit.xml` has the correct database configuration:
   ```xml
   <env name="DB_CONNECTION" value="sqlite"/>
   <env name="DB_DATABASE" value=":memory:"/>
   ```

2. If tests fail due to missing tables, you may need to run migrations in the test environment:
   ```bash
   php artisan migrate --env=testing
   ```

## Future Test Improvements

Future test improvements should include:
1. Complete database integration tests with proper test factories
2. More comprehensive validation tests for request data
3. Authorization tests to verify role-based access controls
4. Tests for sprint statistics and analytics endpoints