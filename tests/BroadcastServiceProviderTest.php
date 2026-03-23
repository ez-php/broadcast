<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Broadcast\Broadcast;
use EzPhp\Broadcast\BroadcastDriverInterface;
use EzPhp\Broadcast\Broadcaster;
use EzPhp\Broadcast\BroadcastServiceProvider;
use EzPhp\Broadcast\Driver\NullDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class BroadcastServiceProviderTest
 *
 * @package Tests
 */
#[CoversClass(BroadcastServiceProvider::class)]
#[UsesClass(Broadcast::class)]
#[UsesClass(Broadcaster::class)]
#[UsesClass(NullDriver::class)]
final class BroadcastServiceProviderTest extends ApplicationTestCase
{
    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(BroadcastServiceProvider::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Broadcast::resetBroadcaster();
        parent::tearDown();
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testBroadcastDriverInterfaceIsBoundInContainer(): void
    {
        $this->assertInstanceOf(
            BroadcastDriverInterface::class,
            $this->app()->make(BroadcastDriverInterface::class)
        );
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testBroadcasterIsBoundInContainer(): void
    {
        $this->assertInstanceOf(Broadcaster::class, $this->app()->make(Broadcaster::class));
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testDefaultDriverIsNullWhenNoConfigSet(): void
    {
        $driver = $this->app()->make(BroadcastDriverInterface::class);

        $this->assertInstanceOf(NullDriver::class, $driver);
    }

    /**
     * @return void
     */
    public function testBroadcastFacadeIsWiredAfterBoot(): void
    {
        // If the facade is wired, this must not throw
        Broadcast::to('test', 'ev', []);
        $this->addToAssertionCount(1);
    }
}
