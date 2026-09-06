<?php

declare(strict_types=1);

namespace App\Security;

use SensitiveParameter;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class AccessTokenHandler implements AccessTokenHandlerInterface
{
    /**
     * @var array<string, non-empty-string> Map of API key => client identifier
     */
    private array $keysToClients;

    public function __construct(string $apiKeysConfig)
    {
        $this->keysToClients = $this->parseConfig($apiKeysConfig);
    }

    public function getUserBadgeFrom(#[SensitiveParameter] string $accessToken): UserBadge
    {
        if ($accessToken === '') {
            throw new BadCredentialsException('Missing API key.');
        }

        $matchedClient = null;
        foreach ($this->keysToClients as $key => $client) {
            if (hash_equals($key, $accessToken)) {
                $matchedClient = $client;
            }
        }

        if ($matchedClient === null) {
            throw new BadCredentialsException('Invalid API key.');
        }

        return new UserBadge(
            $matchedClient,
            static fn(): InMemoryUser => new InMemoryUser($matchedClient, null, ['ROLE_API_USER']),
        );
    }

    /**
     * @return array<string, non-empty-string>
     */
    private function parseConfig(string $config): array
    {
        $keys = [];
        $entries = preg_split('/[\r\n,]+/', $config, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, ':')) {
                $parts = explode(':', $entry, 2);
                $client = trim($parts[0]);
                $key = ltrim(trim($parts[1]), ';');
            } else {
                $client = 'default';
                $key = $entry;
            }

            if ($client !== '' && $key !== '') {
                $keys[$key] = $client;
            }
        }

        return $keys;
    }
}
