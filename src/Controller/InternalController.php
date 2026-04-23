<?php

namespace App\Controller;

use App\Entity\ApiKey;
use App\Security\ApiKeyService;
use App\Security\JwtTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/internal', name: 'app_internal_')]
#[IsGranted('ROLE_USER')]
class InternalController extends AbstractController
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenService,
        private readonly ApiKeyService $apiKeyService
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('internal/index.html.twig', [
            'controller_name' => 'InternalController',
        ]);
    }

    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('internal/dashboard.html.twig', [
            'controller_name' => 'InternalController',
        ]);
    }

    #[Route('/api-token', name: 'api_token')]
    public function apiToken(): Response
    {
        $user = $this->getUser();
        $token = $this->jwtTokenService->createToken($user);
        
        return $this->render('internal/api_token.html.twig', [
            'token' => $token,
            'expires_in' => $this->jwtTokenService->getTokenLifetime(),
        ]);
    }

    #[Route('/api-keys', name: 'api_keys')]
    public function apiKeys(): Response
    {
        $user = $this->getUser();
        $keys = $this->apiKeyService->getUserKeys($user);
        
        return $this->render('internal/api_keys.html.twig', [
            'keys' => $keys,
        ]);
    }
}
