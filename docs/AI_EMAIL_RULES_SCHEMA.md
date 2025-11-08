# AI Email Rules Database Schema

## Overview

This document describes the complete database schema for the AI-powered email rules system. All 5 tables have been successfully migrated and are ready for use.

**Migration Batch:** 2  
**Migration Date:** 2025-11-07  
**Status:** ✅ Successfully Migrated

---

## Table Schemas

### 1. `email_rules` Table

Stores user-defined rules that use AI to evaluate and process emails.

**File:** `database/migrations/2025_11_07_083407_create_email_rules_table.php`

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | NO | AUTO | Primary key |
| `user_id` | bigint | NO | - | Foreign key to users |
| `name` | varchar(255) | NO | - | Rule display name |
| `description` | text | YES | NULL | Optional rule description |
| `prompt` | text | NO | - | AI prompt for evaluation |
| `simple_conditions` | json | YES | NULL | Pre-filter conditions (reduces AI calls) |
| `is_active` | tinyint(1) | NO | 1 | Whether rule is enabled |
| `priority` | int | NO | 0 | Execution priority (lower = higher) |
| `ai_provider` | varchar(255) | YES | NULL | AI provider (anthropic/openai/gemini) |
| `ai_model` | varchar(255) | YES | NULL | Specific model override |
| `created_at` | timestamp | YES | NULL | Creation timestamp |
| `updated_at` | timestamp | YES | NULL | Last update timestamp |
| `deleted_at` | timestamp | YES | NULL | Soft delete timestamp |

**Indexes:**
- `email_rules_user_id_is_active_index`: (user_id, is_active) - Query active rules per user
- `email_rules_priority_index`: (priority) - Sort by execution priority

**Foreign Keys:**
- `user_id` → `users(id)` ON DELETE CASCADE

**Features:**
- Soft deletes enabled
- Supports multiple AI providers
- Priority-based execution order
- Pre-filter conditions to optimize AI usage

---

### 2. `email_rule_actions` Table

Defines actions to execute when a rule matches an email.

**File:** `database/migrations/2025_11_07_083412_create_email_rule_actions_table.php`

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | NO | AUTO | Primary key |
| `email_rule_id` | bigint | NO | - | Foreign key to email_rules |
| `action_type` | enum | NO | - | Action type: add_label, forward, add_reminder |
| `action_config` | json | NO | - | Configuration specific to action type |
| `created_at` | timestamp | YES | NULL | Creation timestamp |
| `updated_at` | timestamp | YES | NULL | Last update timestamp |

**Indexes:**
- `email_rule_actions_email_rule_id_index`: (email_rule_id) - Query actions per rule
- `email_rule_actions_action_type_index`: (action_type) - Filter by action type

**Foreign Keys:**
- `email_rule_id` → `email_rules(id)` ON DELETE CASCADE

**Action Types:**
1. **add_label**: Adds a label to the email
2. **forward**: Forwards email to specified address
3. **add_reminder**: Creates a reminder for the email

**Example action_config:**
```json
// add_label
{"label_name": "Important", "color": "#FF0000"}

// forward
{"recipient": "assistant@example.com", "include_attachments": true}

// add_reminder
{"reminder_text": "Follow up", "days_from_now": 3}
```

---

### 3. `email_labels` Table

Stores labels applied to emails (manually or via rules).

**File:** `database/migrations/2025_11_07_083415_create_email_labels_table.php`

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | NO | AUTO | Primary key |
| `email_id` | bigint | NO | - | Foreign key to emails |
| `label_name` | varchar(255) | NO | - | Label text |
| `color` | varchar(255) | YES | NULL | Hex color code for UI (#RRGGBB) |
| `applied_by_rule_id` | bigint | YES | NULL | Rule that applied this label (if any) |
| `created_at` | timestamp | YES | NULL | When label was applied |
| `updated_at` | timestamp | YES | NULL | Last update timestamp |

**Indexes:**
- `email_labels_email_id_index`: (email_id) - Query labels per email
- `email_labels_label_name_index`: (label_name) - Search by label name
- `email_labels_applied_by_rule_id_index`: (applied_by_rule_id) - Track rule-applied labels

**Unique Constraints:**
- `email_labels_email_id_label_name_unique`: (email_id, label_name) - Prevent duplicate labels

**Foreign Keys:**
- `email_id` → `emails(id)` ON DELETE CASCADE
- `applied_by_rule_id` → `email_rules(id)` ON DELETE SET NULL

**Features:**
- One label per email (enforced by unique constraint)
- Tracks whether label was applied manually or by rule
- Optional color for UI display

---

### 4. `email_reminders` Table

Stores scheduled reminders for emails.

**File:** `database/migrations/2025_11_07_083418_create_email_reminders_table.php`

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | NO | AUTO | Primary key |
| `email_id` | bigint | NO | - | Foreign key to emails |
| `user_id` | bigint | NO | - | Foreign key to users |
| `reminder_text` | text | NO | - | Reminder message |
| `reminder_date` | datetime | NO | - | When to trigger reminder |
| `status` | enum | NO | pending | Status: pending, triggered, dismissed, completed |
| `applied_by_rule_id` | bigint | YES | NULL | Rule that created reminder (if any) |
| `triggered_at` | timestamp | YES | NULL | When reminder was triggered |
| `dismissed_at` | timestamp | YES | NULL | When user dismissed reminder |
| `completed_at` | timestamp | YES | NULL | When user marked as completed |
| `created_at` | timestamp | YES | NULL | Creation timestamp |
| `updated_at` | timestamp | YES | NULL | Last update timestamp |

**Indexes:**
- `email_reminders_user_id_status_index`: (user_id, status) - Query user's active reminders
- `email_reminders_email_id_index`: (email_id) - Query reminders per email
- `email_reminders_reminder_date_status_index`: (reminder_date, status) - Find pending reminders to trigger
- `email_reminders_applied_by_rule_id_index`: (applied_by_rule_id) - Track rule-created reminders

**Foreign Keys:**
- `email_id` → `emails(id)` ON DELETE CASCADE
- `user_id` → `users(id)` ON DELETE CASCADE
- `applied_by_rule_id` → `email_rules(id)` ON DELETE SET NULL

**Status Lifecycle:**
1. **pending**: Reminder scheduled, not yet triggered
2. **triggered**: Reminder fired, awaiting user action
3. **dismissed**: User dismissed without action
4. **completed**: User marked task as done

**Features:**
- Tracks state transitions with timestamps
- Supports manual and rule-created reminders
- Indexed for efficient scheduled checks

---

### 5. `email_rule_executions` Table

Audit log of rule executions for debugging and analytics.

**File:** `database/migrations/2025_11_07_083421_create_email_rule_executions_table.php`

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | NO | AUTO | Primary key |
| `email_rule_id` | bigint | NO | - | Foreign key to email_rules |
| `email_id` | bigint | NO | - | Foreign key to emails |
| `user_id` | bigint | NO | - | Foreign key to users |
| `ai_provider` | varchar(255) | YES | NULL | AI provider used (anthropic/openai/gemini) |
| `ai_model` | varchar(255) | YES | NULL | Model used (claude-3-5-sonnet, gpt-4, etc.) |
| `prompt_sent` | text | YES | NULL | Full prompt sent to AI |
| `ai_response` | json | YES | NULL | Raw AI response |
| `evaluation_result` | tinyint(1) | NO | 0 | Did rule match? (1=yes, 0=no) |
| `actions_executed` | int | NO | 0 | Count of successful actions |
| `actions_taken` | json | YES | NULL | Details of executed actions |
| `error_message` | text | YES | NULL | Error details if execution failed |
| `execution_time_ms` | int | YES | NULL | Performance tracking (milliseconds) |
| `created_at` | timestamp | YES | NULL | Execution timestamp |
| `updated_at` | timestamp | YES | NULL | Last update timestamp |

**Indexes:**
- `email_rule_executions_email_rule_id_created_at_index`: (email_rule_id, created_at) - Rule execution history
- `email_rule_executions_email_id_index`: (email_id) - All rules executed for an email
- `email_rule_executions_user_id_created_at_index`: (user_id, created_at) - User's execution history
- `email_rule_executions_evaluation_result_index`: (evaluation_result) - Analytics on match rates

**Foreign Keys:**
- `email_rule_id` → `email_rules(id)` ON DELETE CASCADE
- `email_id` → `emails(id)` ON DELETE CASCADE
- `user_id` → `users(id)` ON DELETE CASCADE

**Features:**
- Complete audit trail for debugging
- Performance metrics for optimization
- Stores full AI prompts and responses
- Enables analytics on rule effectiveness

**Example actions_taken:**
```json
[
  {
    "action_type": "add_label",
    "success": true,
    "details": {"label_name": "Urgent", "label_id": 123}
  },
  {
    "action_type": "add_reminder",
    "success": true,
    "details": {"reminder_id": 456, "reminder_date": "2025-11-10T09:00:00Z"}
  }
]
```

---

## Entity Relationships

```
users (1) ─────────< (N) email_rules
                           │
                           │ (1)
                           │
                           v
                        (N) email_rule_actions
                           
emails (1) ─────────< (N) email_labels
                           │
                           └─> applied_by_rule_id ──> email_rules (optional)

emails (1) ─────────< (N) email_reminders
users (1)  ─────────< (N) email_reminders
                           │
                           └─> applied_by_rule_id ──> email_rules (optional)

email_rules (1) ─< (N) email_rule_executions >─ (N) emails
users (1)       ─────────< (N) email_rule_executions
```

---

## Cascade Deletion Rules

### When a `user` is deleted:
- ✅ All `email_rules` cascade delete
- ✅ All `email_reminders` cascade delete
- ✅ All `email_rule_executions` cascade delete

### When an `email_rule` is deleted:
- ✅ All `email_rule_actions` cascade delete
- ✅ All `email_rule_executions` cascade delete
- ⚠️  `email_labels.applied_by_rule_id` set to NULL (preserves labels)
- ⚠️  `email_reminders.applied_by_rule_id` set to NULL (preserves reminders)

### When an `email` is deleted:
- ✅ All `email_labels` cascade delete
- ✅ All `email_reminders` cascade delete
- ✅ All `email_rule_executions` cascade delete

---

## Query Examples

### Find active rules for a user (ordered by priority)
```php
$rules = EmailRule::where('user_id', $userId)
    ->where('is_active', true)
    ->orderBy('priority')
    ->with('actions')
    ->get();
```

### Get all labels for an email
```php
$labels = EmailLabel::where('email_id', $emailId)
    ->with('appliedByRule')
    ->get();
```

### Find pending reminders to trigger
```php
$reminders = EmailReminder::where('status', 'pending')
    ->where('reminder_date', '<=', now())
    ->with(['user', 'email'])
    ->get();
```

### Analyze rule effectiveness
```php
$stats = EmailRuleExecution::where('email_rule_id', $ruleId)
    ->selectRaw('
        COUNT(*) as total_executions,
        SUM(evaluation_result) as matches,
        SUM(actions_executed) as total_actions,
        AVG(execution_time_ms) as avg_time_ms
    ')
    ->first();
```

### Find slow rule executions
```php
$slowRules = EmailRuleExecution::where('execution_time_ms', '>', 1000)
    ->with('emailRule')
    ->orderByDesc('execution_time_ms')
    ->limit(10)
    ->get();
```

---

## Performance Considerations

### Indexes Created
All tables have appropriate indexes for common query patterns:
- **User-based queries**: `user_id` indexed in all relevant tables
- **Date-range queries**: Composite indexes with `created_at`/`reminder_date`
- **Status filtering**: `status` indexed for reminders
- **Rule lookups**: Foreign keys indexed for fast joins
- **Analytics**: `evaluation_result` indexed for aggregate queries

### Query Optimization Tips
1. Always eager-load relationships to avoid N+1 queries
2. Use composite indexes for multi-column WHERE clauses
3. Partition `email_rule_executions` table if it grows large (>1M rows)
4. Consider archiving old executions after 90 days
5. Use `chunkById()` for processing large result sets

### JSON Column Usage
- `simple_conditions`, `action_config`, `ai_response`, `actions_taken` use JSON
- MariaDB 10.11+ supports JSON indexing for specific keys if needed
- Consider extracting frequently-queried JSON fields to columns

---

## Migration Status

All migrations completed successfully:

```
✅ 2025_11_07_083407_create_email_rules_table ................ 47.66ms
✅ 2025_11_07_083412_create_email_rule_actions_table ......... 23.22ms
✅ 2025_11_07_083415_create_email_labels_table ............... 44.04ms
✅ 2025_11_07_083418_create_email_reminders_table ............ 45.35ms
✅ 2025_11_07_083421_create_email_rule_executions_table ...... 41.71ms
```

**Total Migration Time:** ~200ms  
**Batch:** 2  
**Database:** MariaDB 10.11

---

## Next Steps

### Immediate Tasks
1. Create Eloquent models for all 5 tables
2. Define model relationships (hasMany, belongsTo, etc.)
3. Add model factories for testing
4. Create seeders with sample data
5. Write feature tests for rule execution

### Model Files to Create
- `app/Models/EmailRule.php`
- `app/Models/EmailRuleAction.php`
- `app/Models/EmailLabel.php`
- `app/Models/EmailReminder.php`
- `app/Models/EmailRuleExecution.php`

### Service Layer
- `app/Services/EmailRuleService.php` - Rule evaluation and execution
- `app/Services/AIProviderService.php` - AI provider abstraction
- `app/Jobs/ExecuteEmailRulesJob.php` - Process rules in queue

### API Endpoints
- `POST /api/email-rules` - Create rule
- `GET /api/email-rules` - List user's rules
- `PUT /api/email-rules/{id}` - Update rule
- `DELETE /api/email-rules/{id}` - Soft delete rule
- `GET /api/email-rules/{id}/executions` - Rule execution history

---

**Generated:** 2025-11-07  
**Laravel Version:** 12  
**Database:** MariaDB 10.11  
**Migration Batch:** 2
