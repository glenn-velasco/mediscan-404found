<?php

namespace App\Console\Commands;

use App\Models\AccountRetrievalRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('account-retrieval-requests:prune')]
#[Description('Permanently delete account retrieval requests older than 5 days, regardless of status')]
class PruneAccountRetrievalRequests extends Command
{
    public function handle(): void
    {
        $count = (new AccountRetrievalRequest)->pruneAll();

        $this->info("{$count} account retrieval request(s) pruned.");
    }
}
