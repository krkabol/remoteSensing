<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GithubAuthenticator extends AbstractAuthenticator
{
    private GenericProvider $githubProvider;
    private UrlGeneratorInterface $urlGenerator;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;

        $this->githubProvider = new GenericProvider([
            'clientId' => $_ENV['GITHUB_CLIENT_ID'],
            'clientSecret' => $_ENV['GITHUB_CLIENT_SECRET'],
            'redirectUri' => $_ENV['GITHUB_REDIRECT_URI'],
            'urlAuthorize' => 'https://github.com/login/oauth/authorize',
            'urlAccessToken' => 'https://github.com/login/oauth/access_token',
            'urlResourceOwnerDetails' => 'https://api.github.com/user',
        ]);
    }

    public function supports(Request $request): ?bool
    {
        // Only support the GitHub callback route
        return $request->attributes->get('_route') === 'app_auth_github_callback';
    }

    public function authenticate(Request $request): Passport
    {
        $code = $request->query->get('code');

        if (!$code) {
            throw new AuthenticationException('No authorization code provided');
        }

        try {
            $accessToken = $this->githubProvider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            $githubUser = $this->githubProvider->getResourceOwner($accessToken);
            $githubData = $githubUser->toArray();

            // Extract GitHub ID
            $githubId = (string) ($githubData['id'] ?? null);

            if (!$githubId) {
                throw new AuthenticationException('Could not retrieve GitHub ID');
            }

            // Find or create user
            $user = $this->userRepository->findByGithubId($githubId);

            if (!$user) {
                $user = new User();
                $user->setGithubId($githubId);

                // Extract name information
                $user->setName($githubData['name'] ?? null);
                $user->setGivenNames($githubData['name'] ?? null);
                $user->setFamilyName(null);
                $user->setEmail($githubData['email'] ?? null);

                // Store additional data
                $user->setAdditionalData($githubData);

                $this->entityManager->persist($user);
            }

            $user->setLastLoginAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            return new SelfValidatingPassport(new UserBadge($githubId, fn() => $user));

        } catch (\Exception $e) {
            throw new AuthenticationException('GitHub authentication failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Redirect to internal section after successful login
        return new RedirectResponse($this->urlGenerator->generate('app_internal_index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Redirect to login with error
        $request->getSession()->set('auth_error', $exception->getMessage());
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // Redirect to GitHub authorization URL
        $authUrl = $this->githubProvider->getAuthorizationUrl();
        return new RedirectResponse($authUrl);
    }
}
