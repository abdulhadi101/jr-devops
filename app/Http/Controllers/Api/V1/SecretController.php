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
 * APIs for managing one-time secrets
 */
class SecretController extends Controller
{
    public function __construct(
        private SecretService $secretService
    ) {}

    /**
     * Store a new secret.
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
     * Retrieve a secret (burn after reading).
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
