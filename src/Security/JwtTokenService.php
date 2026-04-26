<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Symfony\Component\Clock\ClockInterface;

class JwtTokenService
{
    private InMemory $key;
    protected JwtFacade $jwtFacade;

    public function __construct(protected UserRepository $userRepository, private ClockInterface $clock, private readonly string $secret, private int $tokenLifetime)
    {
        $this->jwtFacade = new JwtFacade();
        $this->key = InMemory::plainText(
            $this->secret ?? bin2hex(random_bytes(32))
        );
    }

    public function createToken(User $user): string
    {
        $token = $this->jwtFacade->issue(
            new Sha256(),
            $this->key,
            function (Builder $builder, \DateTimeImmutable $issuedAt) use ($user): Builder {
                return $builder
                    ->issuedBy('remote-sensing-app')
                    ->permittedFor('remote-sensing-api')
                    ->identifiedBy(bin2hex(random_bytes(16)))
                    ->expiresAt($issuedAt->modify("+{$this->tokenLifetime} seconds"))
                    ->withClaim('user_id', $user->getId())
                    ->withClaim('github_id', $user->getGithubId())
                    ->withClaim('email', $user->getEmail());
            }
        );

        return $token->toString();
    }

    public function parseToken(string $jwt): ?User
    {
        try {
            $token = $this->jwtFacade->parse(
                $jwt,
                new SignedWith(new Sha256(), $this->key),
                new Constraint\StrictValidAt(
                    $this->clock
                ),
                new Constraint\IssuedBy('remote-sensing-app'),
                new Constraint\PermittedFor('remote-sensing-api'),
            );

            return $this->userRepository->findByGithubId($token->claims()->get('github_id'));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getTokenLifetime(): int
    {
        return $this->tokenLifetime;
    }
}
