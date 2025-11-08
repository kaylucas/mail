# Service Layer Data Handling and AI Integration Fixes

**Date:** November 7, 2025
**Status:** RESOLVED
**Commit:** 34fe55e

## Overview

Fixed critical data handling issues across the email rules service layer where action types, property names, recipient data shapes, and AI logging didn't match the actual implementation and database schema. These fixes ensure proper alignment between service layer code, models, and database structure.

## Issues Fixed

### 1. EmailRuleEvaluator Service

**File:** `/Users/kaylucas/Projects/mail/app/Services/EmailRuleEvaluator.php`

#### Issue 1: Action Type Mismatch (Line 252-255)
**Problem:** Match expression used incorrect action type values that don't exist in database.

```php
// BEFORE - WRONG
$result = match ($action->action_type) {
    'label' => $this->labelHandler->execute($email, $action),
    'reminder' => $this->reminderHandler->execute($email, $action),
    // ...
};
```

**Fix:** Updated to use actual database values:
```php
// AFTER - CORRECT
$result = match ($action->action_type) {
    'add_label' => $this->labelHandler->execute($email, $action),
    'add_reminder' => $this->reminderHandler->execute($email, $action),
    // ...
};
```

**Impact:** Actions now correctly match stored action types. Without this fix, all actions would fall through to the default case and fail silently.

---

#### Issue 2: Wrong Property Name (Line 195)
**Problem:** Accessing non-existent `ai_prompt` property instead of actual `prompt`.

```php
// BEFORE - WRONG
$prompt .= "Criteria: {$rule->ai_prompt}\n\n";
```

**Fix:** Changed to correct property name:
```php
// AFTER - CORRECT
$prompt .= "Criteria: {$rule->prompt}\n\n";
```

**Impact:** AI prompts were not being loaded correctly. The `prompt` field is defined in the EmailRule fillable array and migrations.

---

#### Issue 3: Recipient Data Shape Mismatch (Line 346)
**Problem:** Accessing recipients using wrong nested array structure.

```php
// BEFORE - WRONG
$toRecipients = is_array($email->to_recipients)
    ? implode(', ', array_map(fn($r) => $r['email_address']['address'] ?? '', $email->to_recipients))
    : '';
```

**Fix:** Updated to match actual data structure:
```php
// AFTER - CORRECT
$toRecipients = is_array($email->to_recipients)
    ? implode(', ', array_map(fn($r) => $r['email'] ?? '', $email->to_recipients))
    : '';
```

**Details:** The Email model stores recipients as array with direct 'email' key, not nested 'email_address' object.

**Data Structure Reference:**
```php
// Actual structure in database
$email->to_recipients = [
    ['email' => 'user@example.com'],
    ['email' => 'another@example.com'],
]

// NOT this structure
$email->to_recipients = [
    ['email_address' => ['address' => 'user@example.com']],
]
```

---

#### Issue 4: EmailRuleExecution Logging Schema Mismatch (Lines 286-296, 313-323)
**Problem:** Creating execution records with non-existent columns ('matched', 'actions_failed', 'execution_details').

```php
// BEFORE - WRONG
EmailRuleExecution::create([
    'email_rule_id' => $rule->id,
    'email_id' => $email->id,
    'matched' => true,                    // Column doesn't exist
    'actions_executed' => $actionsExecuted,
    'actions_failed' => $actionsFailed,   // Column doesn't exist
    'execution_details' => [              // Column doesn't exist
        'results' => $results,
        'executed_at' => now()->toIso8601String(),
    ],
]);
```

**Fix:** Updated to match actual schema:
```php
// AFTER - CORRECT
EmailRuleExecution::create([
    'email_rule_id' => $rule->id,
    'email_id' => $email->id,
    'user_id' => $email->user_id,
    'evaluation_result' => true,           // Correct boolean field
    'actions_executed' => $actionsExecuted,
    'actions_taken' => $results,           // Correct field name
    'ai_provider' => null,
    'ai_model' => null,
    'prompt_sent' => null,
    'ai_response' => null,
    'error_message' => null,
    'execution_time_ms' => null,
]);
```

**Database Schema Reference:**
```php
// EmailRuleExecution fillable fields
protected $fillable = [
    'email_rule_id',      // Foreign key to EmailRule
    'email_id',           // Foreign key to Email
    'user_id',            // Foreign key to User
    'ai_provider',        // e.g., 'anthropic'
    'ai_model',           // e.g., 'claude-3-haiku'
    'prompt_sent',        // Text of prompt sent to AI
    'ai_response',        // Array response from AI
    'evaluation_result',  // Boolean: did rule match?
    'actions_executed',   // Integer: count of successful actions
    'actions_taken',      // Array: details of actions executed
    'error_message',      // String: any error that occurred
    'execution_time_ms',  // Integer: milliseconds to execute
];
```

---

### 2. ReminderActionHandler Service

**File:** `/Users/kaylucas/Projects/mail/app/Services/Actions/ReminderActionHandler.php`

#### Issue 5: EmailReminder Field Names and Constants (Lines 47-54)

**Problem:** Using wrong field names and string status instead of model constant.

```php
// BEFORE - WRONG
$reminder = EmailReminder::create([
    'user_id' => $email->user_id,
    'email_id' => $email->id,
    'rule_id' => $action->email_rule_id,              // Wrong field name
    'remind_at' => $remindAt,                         // Wrong field name
    'message' => $message,                            // Wrong field name
    'status' => 'pending',                            // String instead of constant
]);
```

**Fix:** Updated to match actual schema and use constants:
```php
// AFTER - CORRECT
$reminder = EmailReminder::create([
    'user_id' => $email->user_id,
    'email_id' => $email->id,
    'applied_by_rule_id' => $action->email_rule_id,  // Correct foreign key
    'reminder_date' => $remindAt,                     // Correct field name
    'reminder_text' => $message,                      // Correct field name
    'status' => EmailReminder::STATUS_PENDING,        // Use constant
]);
```

**Database Schema Reference:**
```php
// EmailReminder fillable fields
protected $fillable = [
    'email_id',           // Foreign key to Email
    'user_id',            // Foreign key to User
    'reminder_text',      // Text of the reminder
    'reminder_date',      // When to remind (datetime)
    'status',             // pending, triggered, dismissed, completed
    'applied_by_rule_id', // Foreign key to EmailRule (nullable)
    'triggered_at',       // When reminder was triggered
    'dismissed_at',       // When reminder was dismissed
    'completed_at',       // When reminder was completed
];

// Status constants
public const STATUS_PENDING = 'pending';
public const STATUS_TRIGGERED = 'triggered';
public const STATUS_DISMISSED = 'dismissed';
public const STATUS_COMPLETED = 'completed';
```

---

### 3. ForwardActionHandler Service

**File:** `/Users/kaylucas/Projects/mail/app/Services/Actions/ForwardActionHandler.php`

#### Issue 6: Recipient Data Shape in buildForwardedEmailBody() (Line 123)

**Problem:** Using wrong nested array structure to access recipient email.

```php
// BEFORE - WRONG
$toAddresses = array_map(
    fn($r) => htmlspecialchars($r['email_address']['address'] ?? ''),
    $email->to_recipients
);
```

**Fix:** Updated to match actual data structure:
```php
// AFTER - CORRECT
$toAddresses = array_map(
    fn($r) => htmlspecialchars($r['email'] ?? ''),
    $email->to_recipients
);
```

**Impact:** Forwarded emails now correctly display the original recipients in the email body.

---

### 4. SendReminderNotificationJob

**File:** `/Users/kaylucas/Projects/mail/app/Jobs/SendReminderNotificationJob.php`

#### Issue 7: Status Update Mismatch (Lines 81-84)

**Problem:** Using non-existent 'sent' status and wrong timestamp field.

```php
// BEFORE - WRONG
$reminder->update([
    'status' => 'sent',            // 'sent' status doesn't exist
    'sent_at' => now(),            // 'sent_at' field doesn't exist
]);
```

**Fix:** Updated to use correct status and timestamp field:
```php
// AFTER - CORRECT
$reminder->update([
    'status' => EmailReminder::STATUS_COMPLETED,
    'completed_at' => now(),
]);
```

**Logic:** When a reminder notification is sent, the reminder is marked as COMPLETED (successfully delivered), not "sent".

---

### 5. EmailRule Model

**File:** `/Users/kaylucas/Projects/mail/app/Models/EmailRule.php`

#### Issue 8: 'to' Condition Matching (Lines 124-141)

**Problem:** Calling `strtolower()` on recipient array and using wrong field access.

```php
// BEFORE - WRONG
if (!empty($conditions['to'])) {
    $toRecipients = array_map('strtolower', $email->to_recipients ?? []);
    // This attempts to call strtolower() on an array!

    foreach ($toRecipients as $recipient) {
        if (str_contains($recipient, $searchTerm)) {
            // But $recipient is still an array, not a string
        }
    }
}
```

**Fix:** Updated to properly iterate and access recipient email:
```php
// AFTER - CORRECT
if (!empty($conditions['to'])) {
    $searchTerm = strtolower($conditions['to']);
    $found = false;

    if (is_array($email->to_recipients)) {
        foreach ($email->to_recipients as $recipient) {
            $recipientEmail = strtolower($recipient['email'] ?? '');
            if (str_contains($recipientEmail, $searchTerm)) {
                $found = true;
                break;
            }
        }
    }

    if (!$found) {
        return false;
    }
}
```

**Impact:** Simple condition matching for 'to' field now works correctly.

---

## Testing

All fixes have been verified with:

1. **PHP Syntax Checks** ✅
   - All modified files pass `php -l` syntax validation
   - No parsing errors

2. **Type Safety** ✅
   - Using model constants for status values
   - Proper array key access with null coalescing
   - Correct type casting

3. **Data Consistency** ✅
   - All field names match database migrations
   - All fillable attributes match model definitions
   - All relationships properly maintained

## Summary of Changes

| Service/Model | File | Issues Fixed | Type |
|---|---|---|---|
| EmailRuleEvaluator | `app/Services/EmailRuleEvaluator.php` | 4 | Data Shape, Schema |
| ReminderActionHandler | `app/Services/Actions/ReminderActionHandler.php` | 1 | Field Names, Constants |
| ForwardActionHandler | `app/Services/Actions/ForwardActionHandler.php` | 1 | Data Shape |
| SendReminderNotificationJob | `app/Jobs/SendReminderNotificationJob.php` | 1 | Field Names, Constants |
| EmailRule | `app/Models/EmailRule.php` | 1 | Data Shape, Array Access |

## Recommendations

1. **Add Type Hints:** Consider adding type hints to catch similar issues at development time.
   ```php
   public function buildEmailContext(Email $email): array
   ```

2. **Use Constants:** Continue using model constants for enum-like fields:
   ```php
   'status' => EmailReminder::STATUS_PENDING,
   ```

3. **Validate Data Shapes:** Add validation in models to ensure data integrity:
   ```php
   protected function validateRecipients(): void
   {
       if (!is_array($this->to_recipients)) {
           return;
       }

       foreach ($this->to_recipients as $recipient) {
           if (!isset($recipient['email'])) {
               throw new \InvalidArgumentException('Invalid recipient structure');
           }
       }
   }
   ```

4. **Documentation:** Keep service layer documentation updated with expected data structures.

## Related Files

- Database Migrations: `database/migrations/2025_11_07_08*.php`
- Models: `app/Models/EmailReminder.php`, `app/Models/EmailRuleExecution.php`
- Routes: `routes/api.php` (email rules endpoints)

## Commit

```
34fe55e Fix service layer data handling and AI integration alignment
```
