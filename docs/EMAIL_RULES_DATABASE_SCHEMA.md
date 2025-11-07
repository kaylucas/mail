# Email Rules Database Schema

This document provides a visual overview of the database schema for the AI-powered email rules system.

## Entity Relationship Diagram (Text Format)

```
┌─────────────────────────────────────────────────────────────────┐
│                         users                                   │
├─────────────────────────────────────────────────────────────────┤
│ id (PK)                                                         │
│ microsoft_id                                                    │
│ name                                                            │
│ email                                                           │
│ email_delta_token                                               │
│ last_email_sync_at                                              │
└────────────┬────────────────────────────────┬────────────────────┘
             │                                │
             │ 1:N                            │ 1:N
             │                                │
┌────────────▼────────────────────┐  ┌────────▼──────────────────┐
│      email_rules                │  │      emails               │
├─────────────────────────────────┤  ├───────────────────────────┤
│ id (PK)                         │  │ id (PK)                   │
│ user_id (FK) ───────────────────┼──┤ user_id (FK)              │
│ name                            │  │ message_id                │
│ description                     │  │ subject                   │
│ prompt (TEXT)                   │  │ from_email                │
│ simple_conditions (JSON)        │  │ body_content              │
│ is_active (BOOLEAN)             │  │ received_date_time        │
│ priority (INT)                  │  │ is_read                   │
│ ai_provider (VARCHAR)           │  │ has_attachments           │
│ ai_model (VARCHAR)              │  └─────┬─────────┬───────────┘
│ created_at                      │        │         │
│ updated_at                      │        │ 1:N     │ 1:N
└────┬────────────────────────────┘        │         │
     │ 1:N                                 │         │
     │                         ┌───────────▼─────┐   │
┌────▼────────────────────┐    │  email_labels  │   │
│ email_rule_actions      │    ├────────────────┤   │
├─────────────────────────┤    │ id (PK)        │   │
│ id (PK)                 │    │ email_id (FK)  │   │
│ email_rule_id (FK) ─────┼───▶│ label_name     │   │
│ action_type (ENUM)      │    │ applied_by_rule│   │
│   - add_label           │    │   _id (FK NULL)│◀──┤
│   - forward             │    │ created_at     │   │
│   - add_reminder        │    └────────────────┘   │
│ action_config (JSON)    │                         │
│ created_at              │    ┌───────────────────▼┐
│ updated_at              │    │  email_reminders   │
└─────────────────────────┘    ├────────────────────┤
                               │ id (PK)            │
┌──────────────────────────┐   │ email_id (FK)      │
│ email_rule_executions    │   │ user_id (FK)       │
├──────────────────────────┤   │ applied_by_rule    │
│ id (PK)                  │   │   _id (FK NULL)    │◀──┐
│ email_id (FK) ───────────┼──▶│ remind_at          │   │
│ email_rule_id (FK) ──────┼───│ message            │   │
│ user_id (FK) ────────────┼──▶│ status (ENUM)      │   │
│ ai_provider              │   │   - pending        │   │
│ ai_model                 │   │   - triggered      │   │
│ prompt_sent (TEXT)       │   │   - dismissed      │   │
│ ai_response (JSON)       │   │   - completed      │   │
│ evaluation_result (BOOL) │   │ triggered_at       │   │
│ actions_executed (JSON)  │   │ created_at         │   │
│ execution_time_ms (INT)  │   │ updated_at         │   │
│ error_message (TEXT)     │   └────────────────────┘   │
│ created_at (AUDIT LOG)   │                            │
└──────────────────────────┘                            │
         │                                              │
         │                FK NULL ON DELETE             │
         └──────────────────────────────────────────────┘
```

## Table Purposes

### Core Tables

#### `email_rules`
- **Purpose:** Stores user-defined email processing rules
- **Key Features:**
  - AI prompt for natural language rule definition
  - Simple conditions for pre-filtering (reduces AI costs)
  - Priority system for execution order
  - Optional AI provider/model override per rule
- **Relationships:**
  - Belongs to `users` (cascade delete)
  - Has many `email_rule_actions`
  - Has many `email_labels` (nullable, null on delete)
  - Has many `email_reminders` (nullable, null on delete)
  - Has many `email_rule_executions`

#### `email_rule_actions`
- **Purpose:** Stores actions to execute when rule matches
- **Key Features:**
  - Enum action types (add_label, forward, add_reminder)
  - JSON config for flexible action parameters
  - Multiple actions per rule supported
- **Relationships:**
  - Belongs to `email_rules` (cascade delete)

### Application Tables

#### `email_labels`
- **Purpose:** Stores labels/tags applied to emails
- **Key Features:**
  - Unique constraint prevents duplicate labels per email
  - Tracks which rule applied the label (nullable)
  - Immutable (no updated_at)
- **Relationships:**
  - Belongs to `emails` (cascade delete)
  - Optionally belongs to `email_rules` (null on delete)

#### `email_reminders`
- **Purpose:** Stores scheduled reminders for emails
- **Key Features:**
  - Status tracking (pending → triggered → completed/dismissed)
  - User-specific reminders
  - Optional custom message
  - Tracks which rule created the reminder
- **Relationships:**
  - Belongs to `emails` (cascade delete)
  - Belongs to `users` (cascade delete)
  - Optionally belongs to `email_rules` (null on delete)

### Audit Table

#### `email_rule_executions`
- **Purpose:** Immutable audit log of all rule executions
- **Key Features:**
  - Complete AI interaction data (prompt + response)
  - Execution timing metrics
  - Error tracking
  - Action execution log
  - NO updated_at (audit log)
- **Relationships:**
  - Belongs to `emails` (cascade delete)
  - Belongs to `email_rules` (cascade delete)
  - Belongs to `users` (cascade delete)

## Indexes for Performance

### `email_rules`
- `user_id` - Find user's rules
- `is_active` - Find active rules only
- `(user_id, priority, is_active)` - Composite for ordered rule execution

### `email_rule_actions`
- `email_rule_id` - Find actions for a rule

### `email_labels`
- `(email_id, label_name)` - UNIQUE, prevent duplicates
- `applied_by_rule_id` - Find labels applied by specific rule

### `email_reminders`
- `user_id` - Find user's reminders
- `status` - Find reminders by status
- `(remind_at, status)` - Composite for pending reminder queries

### `email_rule_executions`
- `email_id` - Find executions for an email
- `email_rule_id` - Find executions of a specific rule
- `user_id` - Find user's rule executions
- `created_at` - Time-based queries for analytics

## Foreign Key Cascade Rules

### Cascade on Delete
- `users` → `email_rules` (delete user = delete all their rules)
- `email_rules` → `email_rule_actions` (delete rule = delete its actions)
- `emails` → `email_labels` (delete email = delete its labels)
- `emails` → `email_reminders` (delete email = delete its reminders)
- `emails` → `email_rule_executions` (delete email = delete execution logs)
- `users` → `email_reminders` (delete user = delete their reminders)
- `users` → `email_rule_executions` (delete user = delete execution logs)
- `email_rules` → `email_rule_executions` (delete rule = delete execution logs)

### Null on Delete (Preserve Audit Trail)
- `email_rules` → `email_labels.applied_by_rule_id` (rule deleted, label remains)
- `email_rules` → `email_reminders.applied_by_rule_id` (rule deleted, reminder remains)

## Data Types & Constraints

### Enums
- `email_rule_actions.action_type`: ['add_label', 'forward', 'add_reminder']
- `email_reminders.status`: ['pending', 'triggered', 'dismissed', 'completed']

### JSON Fields
- `email_rules.simple_conditions` - Pre-filter conditions
- `email_rule_actions.action_config` - Action-specific parameters
- `email_rule_executions.ai_response` - AI response data
- `email_rule_executions.actions_executed` - Executed actions log

### Text Fields (Large Content)
- `email_rules.description` - Optional rule description
- `email_rules.prompt` - AI evaluation prompt
- `email_reminders.message` - Reminder message
- `email_rule_executions.prompt_sent` - Sent AI prompt
- `email_rule_executions.error_message` - Error details

### String Constraints
- `email_labels.label_name` - VARCHAR(100)
- `email_rules.ai_provider` - VARCHAR(255)
- `email_rules.ai_model` - VARCHAR(255)

## Example Queries

### Find Active Rules for User (Ordered by Priority)
```sql
SELECT * FROM email_rules
WHERE user_id = ? AND is_active = TRUE
ORDER BY priority ASC, created_at ASC;
```

### Find Pending Reminders Due Now
```sql
SELECT * FROM email_reminders
WHERE status = 'pending'
  AND remind_at <= NOW()
ORDER BY remind_at ASC;
```

### Find Labels Applied by Specific Rule
```sql
SELECT el.*, e.subject, e.from_email
FROM email_labels el
JOIN emails e ON el.email_id = e.id
WHERE el.applied_by_rule_id = ?
ORDER BY el.created_at DESC;
```

### Get Rule Execution Statistics
```sql
SELECT
    er.id,
    er.name,
    COUNT(ere.id) as total_executions,
    SUM(CASE WHEN ere.evaluation_result = TRUE THEN 1 ELSE 0 END) as matches,
    AVG(ere.execution_time_ms) as avg_execution_time,
    COUNT(ere.error_message) as errors
FROM email_rules er
LEFT JOIN email_rule_executions ere ON er.id = ere.email_rule_id
WHERE er.user_id = ?
GROUP BY er.id, er.name
ORDER BY total_executions DESC;
```

### Find All Labels on Email (with Rule Attribution)
```sql
SELECT
    el.label_name,
    er.name as rule_name,
    el.created_at
FROM email_labels el
LEFT JOIN email_rules er ON el.applied_by_rule_id = er.id
WHERE el.email_id = ?
ORDER BY el.created_at DESC;
```

## Migration Order

The migrations must be run in this order (enforced by timestamp):

1. `2025_11_07_083407_create_email_rules_table.php`
2. `2025_11_07_083412_create_email_rule_actions_table.php`
3. `2025_11_07_083415_create_email_labels_table.php`
4. `2025_11_07_083418_create_email_reminders_table.php`
5. `2025_11_07_083421_create_email_rule_executions_table.php`

This order ensures foreign key constraints are satisfied.

## Storage Estimates

### Small User (100 rules, 1000 emails)
- `email_rules`: ~100 rows × ~1 KB = 100 KB
- `email_rule_actions`: ~300 rows × ~500 B = 150 KB
- `email_labels`: ~500 rows × ~100 B = 50 KB
- `email_reminders`: ~50 rows × ~500 B = 25 KB
- `email_rule_executions`: ~10,000 rows × ~5 KB = 50 MB

### Large User (50 rules, 100,000 emails)
- `email_rules`: ~50 rows × ~1 KB = 50 KB
- `email_rule_actions`: ~150 rows × ~500 B = 75 KB
- `email_labels`: ~50,000 rows × ~100 B = 5 MB
- `email_reminders`: ~5,000 rows × ~500 B = 2.5 MB
- `email_rule_executions`: ~500,000 rows × ~5 KB = 2.5 GB

**Note:** The `email_rule_executions` table will grow quickly. Consider:
- Partitioning by `created_at` for large deployments
- Archiving old executions after 90 days
- Setting up index maintenance schedules

---

**Generated:** 2025-11-07
**Laravel Version:** 12
**Database:** MariaDB 10.11 (MySQL Compatible)
