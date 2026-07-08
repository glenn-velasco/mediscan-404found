<?php

namespace App\Console\Commands;

use App\Services\User\InvitationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('invitations:prune')]
#[Description('Delete all invitations that expired without being accepted')]
class PruneInvitations extends Command
{
    public function handle(InvitationService $invitationService): void
    {
        $count = $invitationService->pruneExpired();

        $this->info("{$count} invitation(s) pruned.");
    }
}
