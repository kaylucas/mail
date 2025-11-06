<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Email;
use App\Models\EmailFolder;
use App\Services\EmailSyncService;
use App\Services\Office365Service;

class TestSync extends Command
{
    protected $signature = 'test:sync {user_id=19}';
    protected $description = 'Test email sync';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User {$userId} not found");
            return 1;
        }

        // Clear existing data
        $this->info('Clearing existing data...');
        Email::where('user_id', $userId)->delete();
        EmailFolder::where('user_id', $userId)->delete();
        $user->clearDeltaToken();

        $office365Service = app(Office365Service::class);
        $syncService = new EmailSyncService($office365Service);

        $this->info('Starting complete sync test for user ' . $userId . '...');
        
        try {
            $start = microtime(true);
            $result = $syncService->initialSync($user, null);
            $elapsed = round(microtime(true) - $start, 2);
            
            $this->info("SUCCESS! Sync finished in {$elapsed} seconds");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Messages synced', $result['messages_synced']],
                    ['Folders synced', $result['folders_synced']],
                    ['Pages processed', $result['pages_processed']],
                    ['Delta token stored', $result['delta_token'] ? 'Yes' : 'No'],
                ]
            );
            
            $dbEmails = Email::where('user_id', $userId)->count();
            $dbFolders = EmailFolder::where('user_id', $userId)->count();
            
            $this->info("Database totals: {$dbEmails} emails, {$dbFolders} folders");
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}
