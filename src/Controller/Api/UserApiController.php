<?php declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

#[Route('/api/v1', name: 'app_api_v1_')]
class UserApiController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {}

    /**
     * Get user by ID
     */
    #[Route('/user/{id}', name: 'user_get_one', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/v1/user/{id}',
        summary: 'Get user by ID',
        description: 'Retrieves detailed information about a specific user',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'User ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response',
                content: new OA\JsonContent(ref: '#/components/schemas/User')
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'User not found')
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
    public function getUserById(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $user->getId(),
            'githubId' => $user->getGithubId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'givenNames' => $user->getGivenNames(),
            'familyName' => $user->getFamilyName(),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get current authenticated user
     */
    #[Route('/user/me', name: 'user_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/v1/user/me',
        summary: 'Get current user',
        description: 'Retrieves information about the currently authenticated user',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response',
                content: new OA\JsonContent(ref: '#/components/schemas/User')
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
    public function getMe(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'githubId' => $user->getGithubId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'givenNames' => $user->getGivenNames(),
            'familyName' => $user->getFamilyName(),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * List all users
     */
    #[Route('/user', name: 'user_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/v1/user',
        summary: 'List all users',
        description: 'Retrieves a list of all users',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/User')
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
    public function listUsers(): JsonResponse
    {
        $users = $this->userRepository->findAll();

        $data = array_map(function (User $user) {
            return [
                'id' => $user->getId(),
                'githubId' => $user->getGithubId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'givenNames' => $user->getGivenNames(),
                'familyName' => $user->getFamilyName(),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ];
        }, $users);

        return $this->json($data);
    }
}
