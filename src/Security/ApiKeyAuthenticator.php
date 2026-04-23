<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {}

    public function supports(Request $request): ?bool
    {
        // Support API routes with Authorization: ApiKey header
        return $request->headers->has('Authorization')
            && preg_match('/^ApiKey\s+/i', $request->headers->get('Authorization'));
    }

    public function authenticate(Request $request): Passport
    {
        $authorizationHeader = $request->headers->get('Authorization');
        $rawKey = trim(str_ireplace('ApiKey', '', $authorizationHeader));

        if (empty($rawKey)) {
            throw new CustomUserMessageAuthenticationException('Invalid API key');
        }

        $user = $this->apiKeyService->validateKey($rawKey);

        if ($user === null) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired API key');
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getGithubId(), fn() => $user)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Return null to continue with the request
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }
}