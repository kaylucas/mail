<?php

namespace App\Console\Commands;

use App\Models\Office365Connection;
use App\Services\Office365Service;
use Illuminate\Console\Command;

class RefreshOffice365Tokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'office365:refresh-tokens
                            {--dry-run : Preview tokens that would be refreshed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh expiring Office365 access tokens proactively';

    /**
     * Create a new command instance.
     */
    public function __construct(
        private Office365Service $office365Service
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No tokens will be refreshed');
        }

        $this->info('🔄 Searching for expiring Office365 tokens...');

        // Find active connections with tokens expiring in next 10 minutes
        $expiringConnections = Office365Connection::where('is_active', true)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addMinutes(10))
            ->where('token_expires_at', '>', now()) // Not already expired
            ->with('user:id,name,email')
            ->get();

        if ($expiringConnections->isEmpty()) {
            $this->info('✅ No tokens need refreshing at this time');

            return Command::SUCCESS;
        }

        $this->warn("Found {$expiringConnections->count()} token(s) expiring in the next 10 minutes");
        $this->newLine();

        $refreshed = 0;
        $failed = 0;

        foreach ($expiringConnections as $connection) {
            $user = $connection->user;
            $expiresIn = $connection->token_expires_at->diffForHumans();

            $this->line("👤 User: {$user->name} ({$user->email})");
            $this->line("   Token expires: {$expiresIn}");

            if ($dryRun) {
                $this->info('   ⏭️  Would refresh (dry run)');
                $refreshed++;

                continue;
            }

            try {
                // Refresh the access token
                $tokenData = $this->office365Service->refreshAccessToken($connection);

                // Update connection with new tokens
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => $tokenData['expires_at'],
                ]);

                $newExpiresIn = $tokenData['expires_at']->diffForHumans();
                $this->info("   ✅ Refreshed successfully (new expiry: {$newExpiresIn})");
                $refreshed++;
            } catch (\Exception $e) {
                $this->error("   ❌ Failed to refresh: {$e->getMessage()}");
                $failed++;

                // If refresh token is invalid/expired, mark connection as inactive
                if (str_contains($e->getMessage(), 'invalid_grant') ||
                    str_contains($e->getMessage(), 'expired')) {
                    $connection->update(['is_active' => false]);
                    $this->warn('   ⚠️  Marked connection as inactive');
                }
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();

        if ($dryRun) {
            $this->info('📊 Summary (Dry Run):');
            $this->info("   Would refresh: {$refreshed}");
        } else {
            $this->info('📊 Summary:');
            $this->info("   ✅ Refreshed: {$refreshed}");

            if ($failed > 0) {
                $this->error("   ❌ Failed: {$failed}");
            }
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
