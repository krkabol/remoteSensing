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

    #[Route('/auth/github', name: 'app_auth_github')]
    public function loginWithGithub(UrlGeneratorInterface $urlGenerator): Response
    {
        $githubProvider = new GenericProvider([
            'clientId' => $_ENV['GITHUB_CLIENT_ID'],
            'clientSecret' => $_ENV['GITHUB_CLIENT_SECRET'],
            'redirectUri' => $_ENV['GITHUB_REDIRECT_URI'],
            'urlAuthorize' => 'https://github.com/login/oauth/authorize',
            'urlAccessToken' => 'https://github.com/login/oauth/access_token',
            'urlResourceOwnerDetails' => 'https://api.github.com/user',
        ]);

        $authUrl = $githubProvider->getAuthorizationUrl();

        return $this->redirect($authUrl);
    }

    #[Route('/auth/github/callback', name: 'app_auth_github_callback')]
    public function githubCallback(): Response
    {
        // This route is handled by the GithubAuthenticator
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