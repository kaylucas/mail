<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:deduplicate
                            {--dry-run : Preview changes without applying them}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge duplicate users based on microsoft_id and email';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be applied');
        }

        $this->info('🔎 Searching for duplicate users...');
        $this->newLine();

        // Find duplicate emails
        $duplicateEmails = User::select('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        if ($duplicateEmails->isEmpty()) {
            $this->info('✅ No duplicate users found');

            return Command::SUCCESS;
        }

        $this->warn("Found {$duplicateEmails->count()} duplicate email address(es)");
        $this->newLine();

        $totalDuplicates = 0;
        $totalMerged = 0;

        foreach ($duplicateEmails as $email) {
            $users = User::where('email', $email)
                ->with(['office365Connection', 'emails', 'emailFolders'])
                ->orderBy('created_at', 'asc')
                ->get();

            $totalDuplicates += $users->count() - 1;

            $this->line("📧 Email: <comment>{$email}</comment>");
            $this->table(
                ['ID', 'Name', 'Microsoft ID', 'Created', 'Emails', 'Folders', 'Has Connection'],
                $users->map(fn ($u) => [
                    $u->id,
                    $u->name,
                    $u->microsoft_id ?? '<fg=red>NULL</>',
                    $u->created_at->format('Y-m-d H:i'),
                    $u->emails->count(),
                    $u->emailFolders->count(),
                    $u->office365Connection ? '✓' : '✗',
                ])
            );

            // Determine primary user (most recent with active connection)
            $primaryUser = $users
                ->sortByDesc(fn ($u) => $u->office365Connection?->updated_at ?? $u->created_at)
                ->first();

            $duplicateUsers = $users->reject(fn ($u) => $u->id === $primaryUser->id);

            $this->info("   👤 <fg=green>Primary user:</> ID {$primaryUser->id} (keeping)");

            foreach ($duplicateUsers as $duplicate) {
                $this->line("   🔀 <fg=yellow>Duplicate:</> ID {$duplicate->id} (will merge into {$primaryUser->id})");
            }

            if (! $dryRun && ! $force) {
                if (! $this->confirm("Merge {$duplicateUsers->count()} duplicate(s) into user {$primaryUser->id}?")) {
                    $this->warn('   ⏭️  Skipped');
                    $this->newLine();

                    continue;
                }
            }

            if (! $dryRun) {
                foreach ($duplicateUsers as $duplicate) {
                    try {
                        DB::transaction(function () use ($duplicate, $primaryUser) {
                            // Move emails to primary user
                            $emailsMoved = $duplicate->emails()->update([
                                'user_id' => $primaryUser->id,
                                'office365_connection_id' => $primaryUser->office365Connection->id ?? null,
                            ]);

                            // Move email folders to primary user
                            $foldersMoved = $duplicate->emailFolders()->update([
                                'user_id' => $primaryUser->id,
                            ]);

                            // Move graph subscriptions to primary user
                            $subscriptionsMoved = $duplicate->graphSubscriptions()->update([
                                'user_id' => $primaryUser->id,
                            ]);

                            // If duplicate has connection and primary doesn't, move it
                            if ($duplicate->office365Connection && ! $primaryUser->office365Connection) {
                                $duplicate->office365Connection->update(['user_id' => $primaryUser->id]);
                            } elseif ($duplicate->office365Connection) {
                                // Soft delete duplicate's connection (primary already has one)
                                $duplicate->office365Connection->delete();
                            }

                            // Update primary user's microsoft_id if missing
                            if (! $primaryUser->microsoft_id && $duplicate->microsoft_id) {
                                $primaryUser->update(['microsoft_id' => $duplicate->microsoft_id]);
                            }

                            // Revoke duplicate user's API tokens
                            $duplicate->tokens()->delete();

                            // Soft delete duplicate user
                            $duplicate->delete();

                            $this->info("      ✅ Merged user {$duplicate->id}: {$emailsMoved} emails, {$foldersMoved} folders, {$subscriptionsMoved} subscriptions");
                        });

                        $totalMerged++;
                    } catch (\Exception $e) {
                        $this->error("      ❌ Failed to merge user {$duplicate->id}: {$e->getMessage()}");
                    }
                }
            } else {
                $this->info("   ⏭️  Would merge {$duplicateUsers->count()} duplicate(s) (dry run)");
                $totalMerged += $duplicateUsers->count();
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($dryRun) {
            $this->info('📊 Summary (Dry Run):');
            $this->info("   Duplicate users found: {$totalDuplicates}");
            $this->info("   Would merge: {$totalMerged}");
            $this->newLine();
            $this->warn('💡 Run without --dry-run to apply changes');
        } else {
            $this->info('📊 Summary:');
            $this->info("   Duplicate users found: {$totalDuplicates}");
            $this->info("   ✅ Merged successfully: {$totalMerged}");

            if ($totalMerged > 0) {
                $this->newLine();
                $this->info('💡 Tip: Run this command again to verify no duplicates remain');
            }
        }

        return Command::SUCCESS;
    }
}
