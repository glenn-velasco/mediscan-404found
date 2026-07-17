<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditLogType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DenyAccountRetrievalRequestRequest;
use App\Models\AccountRetrievalRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Medical\AccountRetrievalRequestService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountRetrievalRequestController extends Controller
{
    public function __construct(
        private AccountRetrievalRequestService $retrievalRequestService,
        private AuditLogger $auditLogger,
    ) {}

    public function index(): Response
    {
        $requests = AccountRetrievalRequest::with('requester')
            ->orderByRaw("status != 'pending'")
            ->oldest()
            ->paginate(15);

        return Inertia::render('admin/account-retrieval-requests/index', [
            'requests' => $requests,
        ]);
    }

    public function show(AccountRetrievalRequest $accountRetrievalRequest): Response
    {
        $this->auditLogger->log(
            action: 'account_retrieval_request.viewed',
            type: AuditLogType::View,
            actor: Auth::user(),
            subject: $accountRetrievalRequest->requester,
            metadata: ['account_retrieval_request_id' => $accountRetrievalRequest->id],
            channel: 'web',
        );

        return Inertia::render('admin/account-retrieval-requests/show', [
            'retrievalRequest' => $accountRetrievalRequest->load('requester'),
            'files' => [
                'id_photo' => route('admin.account-retrieval-requests.file', [$accountRetrievalRequest, 'id-photo']),
                'selfie' => route('admin.account-retrieval-requests.file', [$accountRetrievalRequest, 'selfie']),
            ],
        ]);
    }

    public function file(AccountRetrievalRequest $accountRetrievalRequest, string $type): StreamedResponse
    {
        $path = match ($type) {
            'id-photo' => $accountRetrievalRequest->id_photo_path,
            'selfie' => $accountRetrievalRequest->selfie_path,
            default => abort(404),
        };

        return Storage::disk('s3')->response($path);
    }

    public function approve(AccountRetrievalRequest $accountRetrievalRequest): RedirectResponse
    {
        $this->retrievalRequestService->approve($accountRetrievalRequest, Auth::user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Retrieval request approved.',
        ]);

        return redirect()->route('admin.account-retrieval-requests.index');
    }

    public function deny(DenyAccountRetrievalRequestRequest $request, AccountRetrievalRequest $accountRetrievalRequest): RedirectResponse
    {
        $this->retrievalRequestService->deny(
            $accountRetrievalRequest,
            Auth::user(),
            $request->validated('rejection_reason')
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Retrieval request denied.',
        ]);

        return redirect()->route('admin.account-retrieval-requests.index');
    }
}
