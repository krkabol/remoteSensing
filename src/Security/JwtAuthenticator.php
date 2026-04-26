<?php declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtTokenService                                 $jwtTokenService,
        #[Target('api_failed_auth')] private RateLimiterFactoryInterface $failedLimiter
    )
    {
    }

    public function supports(Request $request): ?bool
    {
        // Only support API routes with Authorization header
        return $request->attributes->get('_route') !== null
            && str_starts_with($request->attributes->get('_route'), 'app_api_v1_')
            && $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {

        $authorizationHeader = $request->headers->get('Authorization');
        $jwt = trim(str_replace('Bearer', '', $authorizationHeader));

        if (empty($jwt)) {
            throw new CustomUserMessageAuthenticationException('Invalid JWT token');
        }

        $user = $this->jwtTokenService->parseToken($jwt);

        if ($user === null) {
            $this->consumeFailedAttempt($request);
            throw new CustomUserMessageAuthenticationException('Invalid or expired JWT token');
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getGithubId(), fn() => $user)
        );
    }

    private function consumeFailedAttempt(Request $request): void
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $limit = $this->failedLimiter->create($ip)->consume();

        if (!$limit->isAccepted()) {
            throw new CustomUserMessageAuthenticationException('Too many failed attempts');
        }
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
