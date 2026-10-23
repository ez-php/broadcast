<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Broadcast\Broadcast;
use EzPhp\Broadcast\BroadcastDriverInterface;
use EzPhp\Broadcast\Broadcaster;
use EzPhp\Broadcast\BroadcastServiceProvider;
use EzPhp\Broadcast\Driver\LogDriver;
use EzPhp\Broadcast\Driver\RedisDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Redis;
use RedisException;

/**
 * The `log` (with `broadcast.log_path`) and `redis` branches of
 * BroadcastServiceProvider's driver match.
 *
 * Each test writes its own config/broadcast.php, chosen by test name in
 * getBasePath() (which runs before bootstrap). The redis test is skipped when
 * ext-redis is missing or no server answers at REDIS_HOST/REDIS_PORT.
 *
 * @package Tests
 */
#[CoversClass(BroadcastServiceProvider::class)]
#[UsesClass(Broadcast::class)]
#[UsesClass(Broadcaster::class)]
#[UsesClass(LogDriver::class)]
#[UsesClass(RedisDriver::class)]
final class BroadcastProviderDriversTest extends ApplicationTestCase
{
    private string $logFile = '';

    protected function getBasePath(): string
    {
        $path = parent::getBasePath();
        $this->logFile = $path . '/broadcast.log';
        $config = $this->name() === 'test_redis_driver_is_built_from_config'
            ? sprintf(
                "<?php return ['driver' => 'redis', 'redis' => ['host' => %s, 'port' => %d, 'database' => 1]];",
                var_export(self::redisHost(), true),
                self::redisPort(),
            )
            : sprintf("<?php return ['driver' => 'log', 'log_path' => %s];", var_export($this->logFile, true));
        file_put_contents($path . '/config/broadcast.php', $config);

        return $path;
    }

    protected function configureApplication(Application $app): void
    {
        $app->register(BroadcastServiceProvider::class);
    }

    protected function tearDown(): void
    {
        Broadcast::resetBroadcaster();

        if ($this->logFile !== '' && is_file($this->logFile)) {
            unlink($this->logFile);
        }

        parent::tearDown();
    }

    public function test_log_driver_writes_to_the_configured_path(): void
    {
        self::assertInstanceOf(LogDriver::class, $this->app()->make(BroadcastDriverInterface::class));

        $this->app()->make(Broadcaster::class)->to('orders.1', 'order.shipped', ['id' => 1]);

        self::assertFileExists($this->logFile);
        self::assertStringContainsString('order.shipped', (string) file_get_contents($this->logFile));
    }

    public function test_redis_driver_is_built_from_config(): void
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        try {
            if (!(new Redis())->connect(self::redisHost(), self::redisPort(), 1.0)) {
                self::markTestSkipped('Redis is not available.');
            }
        } catch (RedisException) {
            self::markTestSkipped('Redis is not available.');
        }

        $driver = $this->app()->make(BroadcastDriverInterface::class);

        self::assertInstanceOf(RedisDriver::class, $driver);
        $driver->publish('orders.1', 'order.shipped', ['id' => 1]); // PUBLISH with no subscribers: must not throw
    }

    private static function redisHost(): string
    {
        return (string) (getenv('REDIS_HOST') ?: '127.0.0.1');
    }

    private static function redisPort(): int
    {
        return (int) (getenv('REDIS_PORT') ?: 6379);
    }
}
