<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\V1\RegisterDeviceKeyRequest;
use App\Http\Resources\Api\V1\DeviceKeyResource;
use App\Models\UserDeviceKey;
use App\Services\User\DeviceKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Device Keys
 */
class DeviceKeyController extends Controller
{
    public function __construct(private DeviceKeyService $deviceKeyService) {}

    /**
     * Register or rotate a device's public key.
     *
     * The mobile app generates a public/private key pair (sodium sealed-box),
     * registers its public key here, and uses the private key to decrypt
     * envelopes received from professionals.
     *
     * @bodyParam public_key string required Base64-encoded sodium sealed-box public key (44 chars for 32-byte key). Example: 1234567890abcdefghijklmnopqrstuvwxyzABCDEF12
     * @bodyParam label string Device name/label (e.g., iPhone 15, Samsung Galaxy). Example: iPhone 15
     *
     * @response 201 {"status":201,"message":"Device key registered.","data":{"id":1,"user_id":1,"public_key":"1234567890abcdefghijklmnopqrstuvwxyzABCDEF12","label":"iPhone 15","is_active":true,"registered_at":"2026-01-01T00:00:00.000000Z","revoked_at":null}}
     * @response 422 {"status":422,"message":"The public_key field must be 44 characters.","errors":{"public_key":["The public_key field must be 44 characters."]}}
     */
    public function store(RegisterDeviceKeyRequest $request): JsonResponse
    {
        $key = $this->deviceKeyService->registerOrRotate(
            $request->user(),
            $request->validated('public_key'),
            $request->validated('label'),
        );

        return $this->success(new DeviceKeyResource($key), 'Device key registered.', 201);
    }

    /**
     * Revoke a device key.
     *
     * Mark a previously registered key as inactive/revoked.
     * Envelopes encrypted to this key become unretrievable.
     *
     * @response 200 {"status":200,"message":"Device key revoked.","data":null}
     * @response 403 {"status":403,"message":"This device key does not belong to you.","errors":null}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function destroy(Request $request, UserDeviceKey $deviceKey): JsonResponse
    {
        $this->deviceKeyService->revoke($request->user(), $deviceKey);

        return $this->success(message: 'Device key revoked.');
    }
}
