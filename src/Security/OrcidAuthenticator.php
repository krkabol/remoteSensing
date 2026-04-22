<?php

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

class OrcidAuthenticator extends AbstractAuthenticator
{
    private GenericProvider $orcidProvider;
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

        $this->orcidProvider = new GenericProvider([
            'clientId' => $_ENV['ORCID_CLIENT_ID'],
            'clientSecret' => $_ENV['ORCID_CLIENT_SECRET'],
            'redirectUri' => $_ENV['ORCID_REDIRECT_URI'],
            'urlAuthorize' => $_ENV['ORCID_AUTH_URL'],
            'urlAccessToken' => $_ENV['ORCID_TOKEN_URL'],
            'urlResourceOwnerDetails' => $_ENV['ORCID_API_URL'] . '/$/',
            'scopes' => ['/authenticate', '/read-limited'],
        ]);
    }

    public function supports(Request $request): ?bool
    {
        // Support ORCID callback
        return $request->attributes->get('_route') === 'app_auth_orcid_callback';
    }

    public function authenticate(Request $request): Passport
    {
        $code = $request->query->get('code');

        if (!$code) {
            throw new AuthenticationException('No authorization code provided');
        }

        try {
            $accessToken = $this->orcidProvider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            $orcidUser = $this->orcidProvider->getResourceOwner($accessToken);
            $orcidData = $orcidUser->toArray();

            // Extract ORCID ID
            $orcidId = $orcidData['orcid-identifier']['path'] ?? null;

            if (!$orcidId) {
                throw new AuthenticationException('Could not retrieve ORCID ID');
            }

            // Find or create user
            $user = $this->userRepository->findByOrcid($orcidId);

            if (!$user) {
                $user = new User();
                $user->setOrcid($orcidId);
                
                // Extract name information
                $person = $orcidData['person'] ?? [];
                $name = $person['name'] ?? [];
                
                $givenNames = $name['given-names']['value'] ?? null;
                $familyName = $name['family-name']['value'] ?? null;
                
                $user->setGivenNames($givenNames);
                $user->setFamilyName($familyName);
                $user->setName(
                    trim(($givenNames ?? '') . ' ' . ($familyName ?? ''))
                );

                // Extract email if available
                $emails = $person['emails'] ?? [];
                $email = null;
                foreach ($emails as $emailData) {
                    if (($emailData['primary'] ?? false) || $email === null) {
                        $email = $emailData['email'] ?? null;
                    }
                }
                $user->setEmail($email);

                // Store additional data
                $user->setAdditionalData($orcidData);

                $this->entityManager->persist($user);
            }

            $user->setLastLoginAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            return new SelfValidatingPassport(new UserBadge($orcidId, fn() => $user));

        } catch (\Exception $e) {
            throw new AuthenticationException('ORCID authentication failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Redirect to internal section after successful login
        return new RedirectResponse($this->urlGenerator->generate('app_internal'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Redirect to login with error
        $request->getSession()->set('auth_error', $exception->getMessage());
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // Redirect to ORCID authorization URL
        $authUrl = $this->orcidProvider->getAuthorizationUrl();
        return new RedirectResponse($authUrl);
    }
}