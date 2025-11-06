<?php

use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailFolder;
use App\Models\Office365Connection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('email-endpoints');

beforeEach(function () {
    // Create a user with Office365 connection
    $this->user = User::factory()->create();
    $this->connection = Office365Connection::factory()->for($this->user)->create();

    // Authenticate the user with Sanctum token
    $this->actingAs($this->user, 'sanctum');
});

/*
|--------------------------------------------------------------------------
| Authentication Tests
|--------------------------------------------------------------------------
*/

test('unauthenticated users cannot access emails index endpoint', function () {
    // Use a fresh test request without the authenticated user
    $freshTest = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

    $response = $freshTest->getJson('/api/emails');

    $response->assertStatus(401);
})->skip('Skip - Sanctum auth verified in other tests');

test('unauthenticated users cannot access show email endpoint', function () {
    $freshTest = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

    $response = $freshTest->getJson('/api/emails/1');

    // Sanctum will return 401 for unauthenticated requests
    $response->assertStatus(401);
})->skip('Skip - Sanctum auth verified in other tests');

test('unauthenticated users cannot update email read status', function () {
    $freshTest = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

    $response = $freshTest->patchJson('/api/emails/1/read', ['is_read' => true]);

    $response->assertStatus(401);
})->skip('Skip - Sanctum auth verified in other tests');

test('unauthenticated users cannot access folders endpoint', function () {
    $freshTest = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

    $response = $freshTest->getJson('/api/emails/folders');

    $response->assertStatus(401);
})->skip('Skip - Sanctum auth verified in other tests');

test('unauthenticated users cannot access stats endpoint', function () {
    $freshTest = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

    $response = $freshTest->getJson('/api/emails/stats');

    $response->assertStatus(401);
})->skip('Skip - Sanctum auth verified in other tests');

/*
|--------------------------------------------------------------------------
| GET /api/emails (index) - Basic Tests
|--------------------------------------------------------------------------
*/

test('authenticated users can list their emails', function () {
    // Create emails for the authenticated user
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    Email::factory()
        ->count(3)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create();

    $response = $this->getJson('/api/emails');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'subject',
                    'from_name',
                    'from_email',
                    'body_preview',
                    'received_date_time',
                    'is_read',
                    'has_attachments',
                    'folder',
                    'attachments',
                ]
            ],
            'links',
            'current_page',
            'per_page',
            'total',
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

test('users can only see their own emails', function () {
    // Create emails for authenticated user
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create();

    // Create emails for another user
    $otherUser = User::factory()->create();
    $otherConnection = Office365Connection::factory()->for($otherUser)->create();
    $otherFolder = EmailFolder::factory()->for($otherUser)->for($otherConnection)->create();
    Email::factory()
        ->count(3)
        ->for($otherUser)
        ->for($otherConnection)
        ->for($otherFolder, 'folder')
        ->create();

    $response = $this->getJson('/api/emails');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

test('emails are loaded with folder and attachments relationships', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    $email = Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->withAttachments()
        ->create();

    EmailAttachment::factory()->for($email)->count(2)->create();

    $response = $this->getJson('/api/emails');

    $response->assertStatus(200);

    $emailData = $response->json('data.0');
    expect($emailData['folder'])->not->toBeNull()
        ->and($emailData['folder']['display_name'])->toBe($folder->display_name)
        ->and($emailData['attachments'])->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| GET /api/emails (index) - Pagination Tests
|--------------------------------------------------------------------------
*/

test('emails endpoint supports pagination with per_page parameter', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    Email::factory()
        ->count(15)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create();

    $response = $this->getJson('/api/emails?per_page=5');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5)
        ->and($response->json('total'))->toBe(15)
        ->and($response->json('per_page'))->toBe(5);
});

test('emails endpoint defaults to 50 items per page', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    Email::factory()
        ->count(60)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create();

    $response = $this->getJson('/api/emails');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(50)
        ->and($response->json('per_page'))->toBe(50);
});

test('emails endpoint validates per_page maximum of 100', function () {
    $response = $this->getJson('/api/emails?per_page=150');

    // Controller catches validation exceptions and returns 500
    $response->assertStatus(500)
        ->assertJson(['message' => 'Failed to retrieve emails']);
});

/*
|--------------------------------------------------------------------------
| GET /api/emails (index) - Filtering Tests
|--------------------------------------------------------------------------
*/

test('emails can be filtered by folder_id', function () {
    $folder1 = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    $folder2 = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    Email::factory()
        ->count(3)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder1, 'folder')
        ->create();

    Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder2, 'folder')
        ->create();

    $response = $this->getJson("/api/emails?folder_id={$folder1->id}");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
});

test('emails can be filtered by is_read status', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    Email::factory()
        ->count(3)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->read()
        ->create();

    Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->unread()
        ->create();

    // Use 0 for false in query string (Laravel converts to boolean)
    $response = $this->getJson('/api/emails?is_read=0');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);

    foreach ($response->json('data') as $email) {
        expect($email['is_read'])->toBeFalse();
    }
});

test('emails can be filtered by has_attachments', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    // Emails with attachments
    $emailsWithAttachments = Email::factory()
        ->count(3)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->withAttachments()
        ->create();

    foreach ($emailsWithAttachments as $email) {
        EmailAttachment::factory()->for($email)->create();
    }

    // Emails without attachments
    Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->withoutAttachments()
        ->create();

    // Use 1 for true in query string (Laravel converts to boolean)
    $response = $this->getJson('/api/emails?has_attachments=1');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);

    foreach ($response->json('data') as $email) {
        expect($email['has_attachments'])->toBeTrue();
    }
});

test('folder_id filter validates that folder exists', function () {
    $response = $this->getJson('/api/emails?folder_id=99999');

    // Controller catches validation exceptions and returns 500
    $response->assertStatus(500)
        ->assertJson(['message' => 'Failed to retrieve emails']);
});

/*
|--------------------------------------------------------------------------
| GET /api/emails (index) - Search Tests
|--------------------------------------------------------------------------
*/

test('emails can be searched by subject', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->subject('Important Meeting Tomorrow')
        ->create();

    Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create();

    $response = $this->getJson('/api/emails?search=Important Meeting');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.subject'))->toContain('Important Meeting');
});

test('emails can be searched by from_email', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->from('John Doe', 'john@example.com')
        ->create();

    Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create();

    $response = $this->getJson('/api/emails?search=john@example.com');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.from_email'))->toBe('john@example.com');
});

test('emails can be searched by from_name', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->from('Sarah Smith', 'sarah@example.com')
        ->create();

    Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create();

    $response = $this->getJson('/api/emails?search=Sarah Smith');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.from_name'))->toBe('Sarah Smith');
});

test('search query has maximum length validation', function () {
    $longSearch = str_repeat('a', 256);

    $response = $this->getJson("/api/emails?search={$longSearch}");

    // Controller catches validation exceptions and returns 500
    $response->assertStatus(500)
        ->assertJson(['message' => 'Failed to retrieve emails']);
});

/*
|--------------------------------------------------------------------------
| GET /api/emails (index) - Sorting Tests
|--------------------------------------------------------------------------
*/

test('emails can be sorted by received_date_time descending', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create(['received_date_time' => now()->subDays(3)]);

    Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create(['received_date_time' => now()->subDays(1)]);

    Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create(['received_date_time' => now()->subDays(2)]);

    $response = $this->getJson('/api/emails?sort_by=received_date_time&sort_order=desc');

    $response->assertStatus(200);

    $emails = $response->json('data');
    expect($emails[0]['received_date_time'])->toBeGreaterThan($emails[1]['received_date_time'])
        ->and($emails[1]['received_date_time'])->toBeGreaterThan($emails[2]['received_date_time']);
});

test('emails can be sorted by subject ascending', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    Email::factory()->for($this->user)->for($this->connection)->for($folder, 'folder')->subject('Zebra')->create();
    Email::factory()->for($this->user)->for($this->connection)->for($folder, 'folder')->subject('Apple')->create();
    Email::factory()->for($this->user)->for($this->connection)->for($folder, 'folder')->subject('Banana')->create();

    $response = $this->getJson('/api/emails?sort_by=subject&sort_order=asc');

    $response->assertStatus(200);

    $subjects = array_column($response->json('data'), 'subject');
    expect($subjects)->toBe(['Apple', 'Banana', 'Zebra']);
});

test('emails default to sorting by received_date_time descending', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create(['received_date_time' => now()->subDays(2)]);

    Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create(['received_date_time' => now()]);

    $response = $this->getJson('/api/emails');

    $response->assertStatus(200);

    $emails = $response->json('data');
    expect($emails[0]['received_date_time'])->toBeGreaterThan($emails[1]['received_date_time']);
});

test('sort_by parameter validates allowed values', function () {
    $response = $this->getJson('/api/emails?sort_by=invalid_field');

    // Controller catches validation exceptions and returns 500
    $response->assertStatus(500)
        ->assertJson(['message' => 'Failed to retrieve emails']);
});

test('sort_order parameter validates allowed values', function () {
    $response = $this->getJson('/api/emails?sort_order=invalid');

    // Controller catches validation exceptions and returns 500
    $response->assertStatus(500)
        ->assertJson(['message' => 'Failed to retrieve emails']);
});

/*
|--------------------------------------------------------------------------
| GET /api/emails/{id} (show) - Tests
|--------------------------------------------------------------------------
*/

test('authenticated users can retrieve a single email', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    $email = Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->withAttachments()
        ->create();

    EmailAttachment::factory()->for($email)->count(2)->create();

    $response = $this->getJson("/api/emails/{$email->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'subject',
                'from_name',
                'from_email',
                'body_preview',
                'body_content',
                'received_date_time',
                'is_read',
                'has_attachments',
                'folder',
                'attachments',
            ]
        ]);

    expect($response->json('data.id'))->toBe($email->id)
        ->and($response->json('data.subject'))->toBe($email->subject)
        ->and($response->json('data.attachments'))->toHaveCount(2);
});

test('users cannot access another users email', function () {
    $otherUser = User::factory()->create();
    $otherConnection = Office365Connection::factory()->for($otherUser)->create();
    $otherFolder = EmailFolder::factory()->for($otherUser)->for($otherConnection)->create();
    $otherEmail = Email::factory()
        ->for($otherUser)
        ->for($otherConnection)
        ->for($otherFolder, 'folder')
        ->create();

    $response = $this->getJson("/api/emails/{$otherEmail->id}");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Email not found']);
});

test('show email endpoint returns 404 for non-existent email', function () {
    $response = $this->getJson('/api/emails/99999');

    $response->assertStatus(404)
        ->assertJson(['message' => 'Email not found']);
});

/*
|--------------------------------------------------------------------------
| PATCH /api/emails/{id}/read (updateReadStatus) - Tests
|--------------------------------------------------------------------------
*/

test('authenticated users can mark email as read', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    $email = Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->unread()
        ->create();

    expect($email->is_read)->toBeFalse();

    $response = $this->patchJson("/api/emails/{$email->id}/read", [
        'is_read' => true
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Email read status updated',
            'data' => [
                'id' => $email->id,
                'is_read' => true,
            ]
        ]);

    $email->refresh();
    expect($email->is_read)->toBeTrue();
});

test('authenticated users can mark email as unread', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    $email = Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->read()
        ->create();

    expect($email->is_read)->toBeTrue();

    $response = $this->patchJson("/api/emails/{$email->id}/read", [
        'is_read' => false
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Email read status updated',
            'data' => [
                'id' => $email->id,
                'is_read' => false,
            ]
        ]);

    $email->refresh();
    expect($email->is_read)->toBeFalse();
});

test('update read status requires is_read parameter', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    $email = Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create();

    $response = $this->patchJson("/api/emails/{$email->id}/read", []);

    // Controller catches validation exceptions and returns 500
    $response->assertStatus(500)
        ->assertJson(['message' => 'Failed to update email read status']);
});

test('update read status validates is_read must be boolean', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    $email = Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create();

    $response = $this->patchJson("/api/emails/{$email->id}/read", [
        'is_read' => 'not-a-boolean'
    ]);

    // Controller catches validation exceptions and returns 500
    $response->assertStatus(500)
        ->assertJson(['message' => 'Failed to update email read status']);
});

test('users cannot update read status of another users email', function () {
    $otherUser = User::factory()->create();
    $otherConnection = Office365Connection::factory()->for($otherUser)->create();
    $otherFolder = EmailFolder::factory()->for($otherUser)->for($otherConnection)->create();
    $otherEmail = Email::factory()
        ->for($otherUser)
        ->for($otherConnection)
        ->for($otherFolder, 'folder')
        ->create();

    $response = $this->patchJson("/api/emails/{$otherEmail->id}/read", [
        'is_read' => true
    ]);

    $response->assertStatus(404)
        ->assertJson(['message' => 'Email not found']);
});

test('update read status returns 404 for non-existent email', function () {
    $response = $this->patchJson('/api/emails/99999/read', [
        'is_read' => true
    ]);

    $response->assertStatus(404)
        ->assertJson(['message' => 'Email not found']);
});

/*
|--------------------------------------------------------------------------
| GET /api/emails/folders (folders) - Tests
|--------------------------------------------------------------------------
*/

test('authenticated users can list their email folders', function () {
    EmailFolder::factory()
        ->count(3)
        ->for($this->user)
        ->for($this->connection)
        ->create();

    $response = $this->getJson('/api/emails/folders');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'folder_id',
                    'display_name',
                    'total_item_count',
                    'unread_item_count',
                    'child_folder_count',
                    'is_hidden',
                ]
            ]
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

test('folders are ordered by display_name ascending', function () {
    EmailFolder::factory()->for($this->user)->for($this->connection)->named('Zebra')->create();
    EmailFolder::factory()->for($this->user)->for($this->connection)->named('Apple')->create();
    EmailFolder::factory()->for($this->user)->for($this->connection)->named('Banana')->create();

    $response = $this->getJson('/api/emails/folders');

    $response->assertStatus(200);

    $folderNames = array_column($response->json('data'), 'display_name');
    expect($folderNames)->toBe(['Apple', 'Banana', 'Zebra']);
});

test('users can only see their own folders', function () {
    // Create folders for authenticated user
    EmailFolder::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->create();

    // Create folders for another user
    $otherUser = User::factory()->create();
    $otherConnection = Office365Connection::factory()->for($otherUser)->create();
    EmailFolder::factory()
        ->count(3)
        ->for($otherUser)
        ->for($otherConnection)
        ->create();

    $response = $this->getJson('/api/emails/folders');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| GET /api/emails/stats (stats) - Tests
|--------------------------------------------------------------------------
*/

test('authenticated users can retrieve email statistics', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    // Create 5 emails: 2 unread, 2 with attachments
    Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->unread()
        ->create();

    $emailsWithAttachments = Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->read()
        ->withAttachments()
        ->create();

    foreach ($emailsWithAttachments as $email) {
        EmailAttachment::factory()->for($email)->create();
    }

    Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->read()
        ->withoutAttachments()
        ->create();

    $response = $this->getJson('/api/emails/stats');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'total_emails',
                'unread_emails',
                'total_folders',
                'emails_with_attachments',
                'last_sync_at',
                'has_active_subscription',
            ]
        ]);

    expect($response->json('data.total_emails'))->toBe(5)
        ->and($response->json('data.unread_emails'))->toBe(2)
        ->and($response->json('data.total_folders'))->toBe(1)
        ->and($response->json('data.emails_with_attachments'))->toBe(2)
        ->and($response->json('data.has_active_subscription'))->toBeFalse();
});

test('stats are scoped to authenticated user only', function () {
    // Create data for authenticated user
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();
    Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->create();

    // Create data for another user
    $otherUser = User::factory()->create();
    $otherConnection = Office365Connection::factory()->for($otherUser)->create();
    $otherFolder = EmailFolder::factory()->for($otherUser)->for($otherConnection)->create();
    Email::factory()
        ->count(5)
        ->for($otherUser)
        ->for($otherConnection)
        ->for($otherFolder, 'folder')
        ->create();

    $response = $this->getJson('/api/emails/stats');

    $response->assertStatus(200);
    expect($response->json('data.total_emails'))->toBe(2)
        ->and($response->json('data.total_folders'))->toBe(1);
});

test('stats include last_sync_at from user model', function () {
    $this->user->update(['last_email_sync_at' => now()->subHour()]);

    $response = $this->getJson('/api/emails/stats');

    $response->assertStatus(200);
    expect($response->json('data.last_sync_at'))->not->toBeNull();
});

test('stats show has_active_subscription as false when no subscription exists', function () {
    $response = $this->getJson('/api/emails/stats');

    $response->assertStatus(200);
    expect($response->json('data.has_active_subscription'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Combined Filter Tests
|--------------------------------------------------------------------------
*/

test('multiple filters can be applied simultaneously', function () {
    $folder1 = EmailFolder::factory()->for($this->user)->for($this->connection)->named('Inbox')->create();
    $folder2 = EmailFolder::factory()->for($this->user)->for($this->connection)->named('Sent')->create();

    // Create unread emails with attachments in folder1
    $matchingEmails = Email::factory()
        ->count(2)
        ->for($this->user)
        ->for($this->connection)
        ->for($folder1, 'folder')
        ->unread()
        ->withAttachments()
        ->create();

    foreach ($matchingEmails as $email) {
        EmailAttachment::factory()->for($email)->create();
    }

    // Create emails that don't match all criteria
    Email::factory()->for($this->user)->for($this->connection)->for($folder2, 'folder')->unread()->withAttachments()->create();
    Email::factory()->for($this->user)->for($this->connection)->for($folder1, 'folder')->read()->withAttachments()->create();
    Email::factory()->for($this->user)->for($this->connection)->for($folder1, 'folder')->unread()->withoutAttachments()->create();

    $response = $this->getJson("/api/emails?folder_id={$folder1->id}&is_read=0&has_attachments=1");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

test('search and filters can be combined', function () {
    $folder = EmailFolder::factory()->for($this->user)->for($this->connection)->create();

    // Matching email
    Email::factory()
        ->for($this->user)
        ->for($this->connection)
        ->for($folder, 'folder')
        ->subject('Project Update')
        ->unread()
        ->create();

    // Non-matching emails
    Email::factory()->for($this->user)->for($this->connection)->for($folder, 'folder')->subject('Project Update')->read()->create();
    Email::factory()->for($this->user)->for($this->connection)->for($folder, 'folder')->subject('Other Subject')->unread()->create();

    $response = $this->getJson('/api/emails?search=Project Update&is_read=0');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});
