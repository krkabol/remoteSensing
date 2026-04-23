<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Security\JwtTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

#[Route('/api/v1/token', name: 'app_api_v1_token_')]
class TokenController extends AbstractController
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenService
    ) {}

    /**
     * Get JWT token
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/v1/token',
        summary: 'Get JWT token',
        description: 'Exchange session authentication for a JWT token',
        tags: ['Authentication'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJIUzI1NiIs...'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Unauthorized')
                    ]
                )
            )
        ]
    )]
    public function createToken(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtTokenService->createToken($user);

        return $this->json([
            'token' => $token,
            'expires_in' => $this->jwtTokenService->getTokenLifetime(),
            'token_type' => 'Bearer'
        ]);
    }

    /**
     * Refresh JWT token
     */
    #[Route('/refresh', name: 'refresh', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/v1/token/refresh',
        summary: 'Refresh JWT token',
        description: 'Get a new JWT token (requires valid session)',
        tags: ['Authentication'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJIUzI1NiIs...'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Unauthorized')
                    ]
                )
            )
        ]
    )]
    public function refreshToken(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtTokenService->createToken($user);

        return $this->json([
            'token' => $token,
            'expires_in' => $this->jwtTokenService->getTokenLifetime(),
            'token_type' => 'Bearer'
        ]);
    }
}