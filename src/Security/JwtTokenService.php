<?php

namespace App\Security;

use App\Entity\User;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;

class JwtTokenService
{
    private Configuration $config;
    private string $secret;
    private int $tokenLifetime;

    public function __construct()
    {
        $this->secret = $_ENV['JWT_SECRET_KEY'] ?? bin2hex(random_bytes(32));
        $this->tokenLifetime = (int) ($_ENV['JWT_TOKEN_LIFETIME'] ?? 3600); // 1 hour default

        $signingKey = InMemory::plainText($this->secret);

        $this->config = Configuration::forSymmetricSigner(
            new Sha256(),
            $signingKey
        );
    }

    public function createToken(User $user): string
    {
        $now = new \DateTimeImmutable();

        $token = $this->config->builder()
            ->issuedBy('remote-sensing-app')
            ->permittedFor('remote-sensing-api')
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify("+{$this->tokenLifetime} seconds"))
            ->withClaim('user_id', $user->getId())
            ->withClaim('github_id', $user->getGithubId())
            ->withClaim('email', $user->getEmail())
            ->getToken($this->config->signer(), $this->config->signingKey());

        return $token->toString();
    }

    public function parseToken(string $jwt): ?User
    {
        try {
            $token = $this->config->parser()->parse($jwt);

            // Validate the token
            $this->config->validator()->assert(
                $token,
                new IssuedBy('remote-sensing-app'),
                new PermittedFor('remote-sensing-api'),
                new SignedWith($this->config->signer(), InMemory::plainText($this->secret)),
                new ValidAt(new \DateTimeImmutable()),
            );

            // Create a minimal user object from token claims
            $user = new User();
            $user->setGithubId($token->claims()->get('github_id'));
            
            // Use reflection to set the ID (since there's no setter)
            $reflection = new \ReflectionClass($user);
            $idProperty = $reflection->getProperty('id');
            $idProperty->setAccessible(true);
            $idProperty->setValue($user, $token->claims()->get('user_id'));

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getTokenLifetime(): int
    {
        return $this->tokenLifetime;
    }
}