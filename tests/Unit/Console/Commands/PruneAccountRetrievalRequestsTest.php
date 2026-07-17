<?php

use App\Models\AccountRetrievalRequest;

it('prunes requests older than 5 days regardless of status', function () {
    $old = AccountRetrievalRequest::factory()->create(['created_at' => now()->subDays(6)]);
    $recent = AccountRetrievalRequest::factory()->create(['created_at' => now()->subDays(2)]);

    $this->artisan('account-retrieval-requests:prune')->assertSuccessful();

    expect(AccountRetrievalRequest::find($old->id))->toBeNull()
        ->and(AccountRetrievalRequest::find($recent->id))->not->toBeNull();
});
