<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreAccountRetrievalRequestRequest;
use App\Services\Medical\AccountRetrievalRequestService;
use Illuminate\Http\JsonResponse;

/**
 * @group Account Retrieval
 */
class AccountRetrievalRequestController extends Controller
{
    public function __construct(private AccountRetrievalRequestService $retrievalRequestService) {}

    /**
     * Submits a request to regain access to a forgotten account (email or
     * password), reachable both before registering a new account and from a
     * logged-in fresh account. Reviewed by admin/support staff before any
     * access is granted - this endpoint never grants anything itself, and
     * its response is identical whether or not old_email matched an
     * account, so it cannot be used to probe for existing accounts.
     * Requests are automatically pruned 5 days after submission.
     *
     * @unauthenticated
     *
     * @bodyParam old_email string required The email address of the account you're trying to regain access to. Example: jane.doe@example.com
     * @bodyParam first_name string required Example: Jane
     * @bodyParam middle_name string Example: Marie
     * @bodyParam last_name string required Example: Doe
     * @bodyParam dob string required Date of birth in YYYY-MM-DD format. Example: 1990-01-15
     * @bodyParam id_photo file required Photo of a government ID. jpg/jpeg/png, max 5MB.
     * @bodyParam selfie file required Live selfie photo. jpg/jpeg/png, max 3MB.
     *
     * @response 201 {"status":201,"message":"Request submitted for review.","data":null}
     */
    public function store(StoreAccountRetrievalRequestRequest $request): JsonResponse
    {
        /** @var array{old_email: string, first_name: string, middle_name: ?string, last_name: string, dob: string} $data */
        $data = $request->validated();

        $this->retrievalRequestService->submit(
            $request->user('sanctum'),
            $data,
            $request->file('id_photo'),
            $request->file('selfie'),
        );

        return $this->success(null, 'Request submitted for review.', 201);
    }
}
