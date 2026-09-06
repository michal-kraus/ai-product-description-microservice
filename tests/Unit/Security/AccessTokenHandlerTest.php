<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\AccessTokenHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;

final class AccessTokenHandlerTest extends TestCase
{
    private AccessTokenHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new AccessTokenHandler('client1:secret-token-1, client2:;secret-token-2, single-key');
    }

    public function testItReturnsUserBadgeForValidBearerToken(): void
    {
        $badge = $this->handler->getUserBadgeFrom('secret-token-1');

        self::assertSame('client1', $badge->getUserIdentifier());

        $userLoader = $badge->getUserLoader();
        self::assertNotNull($userLoader);
        $user = $userLoader('client1');
        self::assertInstanceOf(UserInterface::class, $user);
        self::assertSame('client1', $user->getUserIdentifier());
        self::assertContains('ROLE_API_USER', $user->getRoles());
    }

    public function testItHandlesClientKeyWithLeadingSemicolon(): void
    {
        $badge = $this->handler->getUserBadgeFrom('secret-token-2');

        self::assertSame('client2', $badge->getUserIdentifier());
    }

    public function testItHandlesSingleKeyDefaultClient(): void
    {
        $badge = $this->handler->getUserBadgeFrom('single-key');

        self::assertSame('default', $badge->getUserIdentifier());
    }

    public function testItThrowsForInvalidToken(): void
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid API key.');

        $this->handler->getUserBadgeFrom('unknown-token');
    }

    public function testItThrowsForEmptyToken(): void
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Missing API key.');

        $this->handler->getUserBadgeFrom('');
    }

    public function testItHandlesEmptyEntriesGracefully(): void
    {
        $handler = new AccessTokenHandler("key1, , \n, key2");
        self::assertSame('default', $handler->getUserBadgeFrom('key1')->getUserIdentifier());
        self::assertSame('default', $handler->getUserBadgeFrom('key2')->getUserIdentifier());
    }
}
