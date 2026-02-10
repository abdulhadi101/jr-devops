<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SecretExpiredException;
use App\Exceptions\SecretNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSecretRequest;
use App\Http\Resources\SecretResource;
use App\Services\SecretService;
use Illuminate\Http\JsonResponse;

/**
 * @group Secret Management
 *
 * APIs for creating and retrieving one-time secrets.
 */
class SecretController extends Controller
{
    public function __construct(
        private SecretService $secretService
    ) {}

    /**
     * Create a Secret
     *
     * Store a new secret message with an optional expiration time (TTL).
     * The returned ID is used to retrieve the secret later.
     *
     * @response 201 {
     *  "id": "9d4f5e6a-7b8c-9d0e-1f2a-3b4c5d6e7f8a",
     *  "url": "https://secure-drop.asuku.xyz/api/v1/secrets/9d4f5e6a...",
     *  "expires_at": "2024-02-10T12:00:00Z"
     * }
     */
    public function store(StoreSecretRequest $request): JsonResponse
    {
        $secret = $this->secretService->storeSecret(
            $request->input('content'),
            $request->input('ttl')
        );

        return (new SecretResource($secret))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Retrieve a Secret
     *
     * Retrieve a secret by its ID. This operation burns the secret, making it inaccessible for future requests.
     *
     * @urlParam id string required The UUID of the secret. Example: 9d4f5e6a-7b8c-9d0e-1f2a-3b4c5d6e7f8a
     *
     * @response 200 {
     *  "content": "Super secret message content"
     * }
     * @response 404 {
     *  "message": "Secret not found or has already been burned."
     * }
     * @response 410 {
     *  "message": "Secret has expired."
     * }
     */
    public function show(string $id): JsonResponse
    {
        try {
            $content = $this->secretService->retrieveAndBurnSecret($id);

            return response()->json([
                'data' => [
                    'content' => $content,
                ],
            ]);
        } catch (SecretNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (SecretExpiredException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 410);
        }
    }
}
