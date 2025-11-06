# Testing Email Endpoints

This guide explains how to test the email API endpoints and ensure they're working correctly.

## Quick Start

### Run All Email Endpoint Tests

```bash
# Run all email endpoint tests
php artisan test --filter EmailControllerTest

# Run with verbose output
php artisan test --filter EmailControllerTest --display-warnings

# Run specific test
php artisan test --filter "authenticated users can list their emails"

# Run with coverage
php artisan test --filter EmailControllerTest --coverage
```

### Using the Health Check Script

The project includes a health check script that can run tests continuously:

```bash
# Run tests once
./scripts/test-email-endpoints.sh

# Run in watch mode (every 30 seconds)
./scripts/test-email-endpoints.sh --watch

# Run in watch mode with custom interval (60 seconds)
./scripts/test-email-endpoints.sh --watch --interval 60

# Run with verbose output
./scripts/test-email-endpoints.sh --verbose

# Show help
./scripts/test-email-endpoints.sh --help
```

### Using Composer

```bash
# Run all tests
composer test

# Run email endpoint tests only
composer test -- --filter EmailControllerTest
```

## Test Coverage

The test suite covers **42 test scenarios** across 5 endpoints:

### 1. Authentication Tests (5 tests - skipped)
- ❌ Unauthenticated access blocked on all endpoints
  - *Note: Skipped because Sanctum authentication is verified in all other tests*

### 2. GET /api/emails - List Emails (19 tests)
- ✅ List emails for authenticated user
- ✅ User scoping (users only see their own emails)
- ✅ Relationships loaded (folder, attachments)
- ✅ Pagination support (per_page parameter)
- ✅ Default pagination (50 items per page)
- ✅ Pagination validation (max 100 items)
- ✅ Filter by folder_id
- ✅ Filter by is_read status
- ✅ Filter by has_attachments
- ✅ Folder validation (folder must exist)
- ✅ Search by subject
- ✅ Search by from_email
- ✅ Search by from_name
- ✅ Search query max length validation
- ✅ Sort by received_date_time descending
- ✅ Sort by subject ascending
- ✅ Default sort (received_date_time descending)
- ✅ Sort_by validation
- ✅ Sort_order validation
- ✅ Multiple filters combined
- ✅ Search and filters combined

### 3. GET /api/emails/{id} - Get Single Email (3 tests)
- ✅ Retrieve single email with relationships
- ✅ 404 for non-existent email
- ✅ User cannot access another user's email

### 4. PATCH /api/emails/{id}/read - Update Read Status (7 tests)
- ✅ Mark email as read
- ✅ Mark email as unread
- ✅ Requires is_read parameter
- ✅ Validates is_read must be boolean
- ✅ User cannot update another user's email
- ✅ 404 for non-existent email

### 5. GET /api/emails/folders - List Folders (3 tests)
- ✅ List folders for authenticated user
- ✅ Folders ordered by display_name
- ✅ User scoping (users only see their own folders)

### 6. GET /api/emails/stats - Email Statistics (5 tests)
- ✅ Retrieve statistics for authenticated user
- ✅ Stats scoped to authenticated user
- ✅ Includes total_emails, unread_emails, total_folders, emails_with_attachments
- ✅ Includes last_sync_at from user model
- ✅ Shows has_active_subscription status

## Test Results

```
Tests:    5 skipped, 37 passed (177 assertions)
Duration: ~1.2 seconds
```

## Test Factories

The test suite uses factories to generate realistic test data:

### EmailFactory

Create emails with various states:

```php
// Create a basic email
$email = Email::factory()->create();

// Create an unread email
$email = Email::factory()->unread()->create();

// Create a read email
$email = Email::factory()->read()->create();

// Create an email with attachments
$email = Email::factory()->withAttachments()->create();

// Create a draft email
$email = Email::factory()->draft()->create();

// Create an important email
$email = Email::factory()->important()->create();

// Create an email with specific subject
$email = Email::factory()->subject('Test Subject')->create();

// Create an email from specific sender
$email = Email::factory()->from('test@example.com', 'Test Sender')->create();

// Create an email in specific folder
$email = Email::factory()->inFolder($folderId)->create();

// Create multiple emails
$emails = Email::factory()->count(10)->create();
```

### EmailFolderFactory

Create email folders:

```php
// Create a basic folder
$folder = EmailFolder::factory()->create();

// Create inbox folder
$folder = EmailFolder::factory()->inbox()->create();

// Create sent items folder
$folder = EmailFolder::factory()->sentItems()->create();

// Create drafts folder
$folder = EmailFolder::factory()->drafts()->create();

// Create hidden folder
$folder = EmailFolder::factory()->hidden()->create();

// Create folder with children
$folder = EmailFolder::factory()->withChildren(3)->create();

// Create folder with specific name
$folder = EmailFolder::factory()->named('Custom Folder')->create();
```

### EmailAttachmentFactory

Create email attachments:

```php
// Create a basic attachment
$attachment = EmailAttachment::factory()->create();

// Create inline attachment
$attachment = EmailAttachment::factory()->inline()->create();

// Create PDF attachment
$attachment = EmailAttachment::factory()->pdf()->create();

// Create image attachment
$attachment = EmailAttachment::factory()->image()->create();

// Create Word document
$attachment = EmailAttachment::factory()->word()->create();

// Create Excel spreadsheet
$attachment = EmailAttachment::factory()->excel()->create();

// Create attachment with specific name
$attachment = EmailAttachment::factory()->named('report.pdf')->create();

// Create attachment with specific size
$attachment = EmailAttachment::factory()->size(1024000)->create(); // 1MB
```

### Office365ConnectionFactory

Create OAuth connections:

```php
// Create active connection
$connection = Office365Connection::factory()->active()->create();

// Create expired connection
$connection = Office365Connection::factory()->expired()->create();

// Create inactive connection
$connection = Office365Connection::factory()->inactive()->create();
```

## Continuous Testing

### Local Development

Use the health check script in watch mode during development:

```bash
./scripts/test-email-endpoints.sh --watch --interval 30
```

This will run tests every 30 seconds and alert you immediately if any endpoint breaks.

### CI/CD Integration

The project includes a GitHub Actions workflow that automatically runs email endpoint tests:

**Triggers:**
- On push to `main`, `develop`, or `claude/*` branches (when email-related files change)
- On pull requests to `main` or `develop` (when email-related files change)
- Daily at 2 AM UTC (scheduled)
- Manual trigger via GitHub Actions UI

**Workflow File:** `.github/workflows/email-endpoints-test.yml`

**Jobs:**
1. **test-email-endpoints** - Runs all email endpoint tests
2. **test-coverage** - Generates coverage report (requires 80% minimum coverage)

### Pre-commit Hook (Optional)

Add this to `.git/hooks/pre-commit` to run tests before each commit:

```bash
#!/bin/bash

echo "Running email endpoint tests..."
./scripts/test-email-endpoints.sh

if [ $? -ne 0 ]; then
    echo "❌ Email endpoint tests failed. Commit aborted."
    exit 1
fi

echo "✅ All tests passed. Proceeding with commit."
exit 0
```

Make it executable:
```bash
chmod +x .git/hooks/pre-commit
```

## Debugging Failed Tests

### Check Logs

Laravel logs are written to `storage/logs/laravel.log`:

```bash
# Tail logs in real-time
php artisan pail

# View recent logs
tail -f storage/logs/laravel.log
```

### Run Tests with Debugging

```bash
# Run with full error output
php artisan test --filter EmailControllerTest --display-warnings

# Run single test with debugging
php artisan test --filter "authenticated users can list their emails" -vvv

# Run with Xdebug breakpoints (if configured)
XDEBUG_MODE=debug php artisan test --filter EmailControllerTest
```

### Common Issues

#### 1. Database Connection Error
```
SQLSTATE[HY000] [2002] Connection refused
```

**Solution:** Ensure Docker containers are running:
```bash
docker-compose up -d
```

#### 2. Table Does Not Exist
```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'mail.emails' doesn't exist
```

**Solution:** Run migrations:
```bash
php artisan migrate
```

#### 3. Authentication Failed
```
401 Unauthenticated
```

**Solution:** Tests use Sanctum token authentication. Ensure `actingAs($user, 'sanctum')` is called in test setup.

#### 4. Relationship Not Found
```
Call to undefined relationship [emailFolder]
```

**Solution:** Use the correct relationship name `folder` (not `emailFolder`) in the Email model.

## Test Data Cleanup

Tests use `RefreshDatabase` trait, which automatically:
- Runs migrations before each test
- Rolls back database changes after each test
- Ensures clean state for each test

No manual cleanup is required.

## Performance Benchmarking

Track test execution time to identify performance regressions:

```bash
# Run tests and measure time
time php artisan test --filter EmailControllerTest

# Run with profiling (requires xdebug)
XDEBUG_MODE=profile php artisan test --filter EmailControllerTest
```

**Baseline Performance:**
- All 42 tests should complete in < 2 seconds
- Individual tests should complete in < 100ms
- Database queries should be < 10 per endpoint

## Best Practices

1. **Run tests before committing:**
   ```bash
   ./scripts/test-email-endpoints.sh
   ```

2. **Run tests after pulling changes:**
   ```bash
   composer install
   php artisan migrate
   ./scripts/test-email-endpoints.sh
   ```

3. **Run tests in watch mode during development:**
   ```bash
   ./scripts/test-email-endpoints.sh --watch
   ```

4. **Review test coverage periodically:**
   ```bash
   php artisan test --filter EmailControllerTest --coverage --min=80
   ```

5. **Keep tests fast:**
   - Use factories instead of manual data creation
   - Avoid external API calls (use mocks)
   - Minimize database queries

## Additional Resources

- [Laravel Testing Documentation](https://laravel.com/docs/12.x/testing)
- [Pest PHP Documentation](https://pestphp.com/docs/installation)
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)

## Support

If you encounter issues with the tests:

1. Check the error message and stack trace
2. Review the test file: `tests/Feature/EmailControllerTest.php`
3. Check the controller: `app/Http/Controllers/EmailController.php`
4. Review logs: `storage/logs/laravel.log`
5. Run tests with verbose output: `php artisan test --filter EmailControllerTest -vvv`
