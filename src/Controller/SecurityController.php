<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use League\OAuth2\Client\Provider\GenericProvider;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $error,
        ]);
    }

    #[Route('/auth/orcid', name: 'app_auth_orcid')]
    public function loginWithOrcid(UrlGeneratorInterface $urlGenerator): Response
    {
        $orcidProvider = new GenericProvider([
            'clientId' => $_ENV['ORCID_CLIENT_ID'],
            'clientSecret' => $_ENV['ORCID_CLIENT_SECRET'],
            'redirectUri' => $_ENV['ORCID_REDIRECT_URI'],
            'urlAuthorize' => $_ENV['ORCID_AUTH_URL'],
            'urlAccessToken' => $_ENV['ORCID_TOKEN_URL'],
            'urlResourceOwnerDetails' => $_ENV['ORCID_API_URL'] . '/$/',
            'scopes' => ['/authenticate', '/read-limited'],
        ]);

        $authUrl = $orcidProvider->getAuthorizationUrl();

        return $this->redirect($authUrl);
    }

    #[Route('/auth/orcid/callback', name: 'app_auth_orcid_callback')]
    public function orcidCallback(): Response
    {
        // This route is handled by the OrcidAuthenticator
        // The authenticator will process the OAuth callback
        return new Response('Authentication in progress...', 202);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // This route is handled by the security firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}