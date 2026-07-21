<?php

namespace App\Console\Commands;

use App\Services\TransactionWorkflowService;
use Illuminate\Console\Command;

class CancelExpiredPendingTransactions extends Command
{
    protected $signature = 'transactions:cancel-expired-pending';

    protected $description = 'Cancel pending rental transactions whose start date has already passed';

    public function handle(TransactionWorkflowService $workflowService): int
    {
        $cancelled = $workflowService->cancelExpiredPendingTransactions();

        if ($cancelled === 0) {
            $this->info('No expired pending rental transactions to cancel.');

            return self::SUCCESS;
        }

        $this->info("Cancelled {$cancelled} expired pending rental transaction(s).");

        return self::SUCCESS;
    }
}
