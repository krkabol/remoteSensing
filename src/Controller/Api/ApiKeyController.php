<?php declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\ApiKey;
use App\Security\ApiKeyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validation;
use OpenApi\Attributes as OA;

#[Route('/api/v1/api-keys', name: 'app_api_v1_api_keys_')]
#[IsGranted('ROLE_USER')]
class ApiKeyController extends AbstractController
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {}

    /**
     * List all API keys for current user
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/api-keys',
        summary: 'List API keys',
        description: 'Get all API keys for the current user (secrets are not returned)',
        tags: ['API Keys'],
        security: [['bearerAuth' => []], ['apiKeyAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'prefix', type: 'string', example: 'sk_live_'),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'last_used_at', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'is_active', type: 'boolean')
                        ]
                    )
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            )
        ]
    )]
    public function listKeys(): JsonResponse
    {
        $user = $this->getUser();
        $keys = $this->apiKeyService->getUserKeys($user);

        $data = array_map(function (ApiKey $key) {
            return [
                'id' => $key->getId(),
                'name' => $key->getName(),
                'prefix' => ApiKeyService::getKeyPrefix(),
                'created_at' => $key->getCreatedAt()->format('Y-m-d H:i:s'),
                'expires_at' => $key->getExpiresAt()?->format('Y-m-d H:i:s'),
                'last_used_at' => $key->getLastUsedAt()?->format('Y-m-d H:i:s'),
                'is_active' => $key->isActive(),
            ];
        }, $keys);

        return $this->json($data);
    }

    /**
     * Create a new API key
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/api-keys',
        summary: 'Create API key',
        description: 'Create a new API key (the secret is only shown once)',
        tags: ['API Keys'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Device Sensor #1'),
                    new OA\Property(property: 'expires_in_days', type: 'integer', example: 3650, description: 'Optional: days until expiration (null for no expiration)')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Key created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'key', type: 'string', example: 'sk_live_abc123...'),
                        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'warning', type: 'string', example: 'Store this key securely. It will not be shown again.')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid request'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            )
        ]
    )]
    public function createKey(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->json(['error' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $name = $data['name'];
        $expiresAt = null;

        if (isset($data['expires_in_days']) && is_numeric($data['expires_in_days'])) {
            $expiresAt = new \DateTimeImmutable("+{$data['expires_in_days']} days");
        }

        $rawKey = $this->apiKeyService->generateKey($user, $name, $expiresAt);

        return $this->json([
            'id' => $user->getApiKeys()->last()->getId(),
            'name' => $name,
            'key' => $rawKey,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'warning' => 'Store this key securely. It will not be shown again.'
        ], Response::HTTP_CREATED);
    }

    /**
     * Revoke an API key
     */
    #[Route('/{id}', name: 'revoke', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/api-keys/{id}',
        summary: 'Revoke API key',
        description: 'Revoke (deactivate) an API key',
        tags: ['API Keys'],
        security: [['bearerAuth' => []], ['apiKeyAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'API Key ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Key revoked successfully'
            ),
            new OA\Response(
                response: 404,
                description: 'Key not found'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            )
        ]
    )]
    public function revokeKey(int $id): JsonResponse
    {
        $user = $this->getUser();

        // Verify the key belongs to the user
        foreach ($user->getApiKeys() as $key) {
            if ($key->getId() === $id) {
                $this->apiKeyService->revokeKey($id);
                return $this->json(null, Response::HTTP_NO_CONTENT);
            }
        }

        return $this->json(['error' => 'API key not found'], Response::HTTP_NOT_FOUND);
    }
}
