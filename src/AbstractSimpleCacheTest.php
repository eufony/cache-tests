<?php

namespace Eufony\Cache\Tests;

use DateInterval;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Provides an abstract PSR-16 implementation tester.
 */
abstract class AbstractSimpleCacheTest extends TestCase
{
    /**
     * The PSR-16 cache implementation to test.
     *
     * @var \Psr\SimpleCache\CacheInterface $cache
     */
    protected CacheInterface $cache;

    /**
     * Returns a new instance of a PSR-16 cache implementation to test.
     *
     * @return \Psr\SimpleCache\CacheInterface
     */
    abstract public function getCache(): CacheInterface;

    /**
     * Returns an array of valid cache items.
     *
     * @return mixed[]
     */
    public function validCacheItems(): array
    {
        return [
            "",
            "foo",
            str_repeat("a", 1024 * 1024),
            0,
            PHP_INT_MIN,
            PHP_INT_MAX,
            0.0,
            PHP_FLOAT_MIN,
            PHP_FLOAT_MAX,
            true,
            false,
            null,
            ["foo", "bar", "baz"],
            ["foo" => "bar"],
            [["foo" => "bar"], ["foo" => "baz"]],
            new Exception("Serialized exception"),
        ];
    }

    /**
     * Returns an array of valid TTLs.
     *
     * @return mixed[]
     */
    public function validTTLs(): array
    {
        return [
            1,
            5,
            new DateInterval("PT1S"),
            new DateInterval("PT5S"),
            0,
            -1,
            null,
        ];
    }

    /**
     * Returns an array of invalid cache keys
     *
     * @return mixed[]
     */
    public function invalidCacheKeys(): array
    {
        return [
            "",
            "{}",
            "()",
            "/\\",
            "@",
            ":",
            0,
            true,
            false,
            null,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->cache = $this->getCache();
    }

    /**
     * Data provider for testing invalid cache keys.
     *
     * Returns the method name, an invalid cache key, and an array of additional
     * method arguments for each data set.
     *
     * @return mixed[][]
     */
    public function data_invalidKeys(): array
    {
        $methods = ["get", "set", "delete", "getMultiple", "setMultiple", "deleteMultiple", "has"];
        $invalid_keys = $this->invalidCacheKeys();

        $data = [];

        foreach ($methods as $method) {
            // If dealing with a "multiple" method, the parameter should be an array of keys
            if (in_array($method, ["getMultiple", "setMultiple", "deleteMultiple"])) {
                $invalid_keys = array_map(fn($key) => [$key], $invalid_keys);
            }

            // Some PSR-16 methods require additional parameters
            $args = match ($method) {
                "set" => ["bar"],
                "setMultiple" => [["bar"]],
                default => []
            };

            // Push arguments to data set
            foreach ($invalid_keys as $key) {
                $data[] = [$method, $key, $args];
            }
        }

        return $data;
    }

    /**
     * Data provider for testing invalid TTLs.
     *
     * Returns the method name and an array of invalid method arguments for each
     * data set.
     *
     * @return mixed[][]
     */
    public function data_invalidTTLs(): array
    {
        $invalid_ttl = "";

        return [
            ["set", ["foo", "bar", $invalid_ttl]],
            ["setMultiple", [["foo"], ["bar"]], $invalid_ttl],
        ];
    }

    /**
     * Data provider for testing invalid iterables.
     *
     * Returns the method name and an array of invalid method arguments for each
     * data set.
     *
     * @return string[][]
     */
    public function data_invalidIterables(): array
    {
        $invalid_iterable = null;

        return [
            ["getMultiple", [$invalid_iterable]],
            ["setMultiple", [$invalid_iterable, ["foo"]]],
            ["deleteMultiple", [$invalid_iterable],]
        ];
    }

    /**
     * Data provider for testing valid cache items.
     *
     * Returns a valid cache item for each data set.
     *
     * @return mixed[][]
     */
    public function data_validCacheItems(): array
    {
        $valid_items = $this->validCacheItems();
        return array_map(fn($value) => [$value], $valid_items);
    }

    /**
     * Data provider for testing expired cache items.
     *
     * Returns a valid TTL, and whether the cache item should expire after two
     * seconds for each data set.
     *
     * @return mixed[][]
     */
    public function data_ttls(): array
    {
        $valid_ttls = $this->validTTLs();

        $data = [];

        foreach ($valid_ttls as $ttl) {
            // Check if TTL expires after two seconds based on its type
            $expired = match (gettype($ttl)) {
                "integer" => $ttl >= 2,
                "object" => (new DateTimeImmutable("now"))->add($ttl)->getTimestamp() - time() >= 2,
                "NULL" => false,
            };

            // Push arguments to data set
            $data[] = [$ttl, $expired];
        }

        return $data;
    }

    /**
     * Data provider for testing setting and deleting multiple cache items.
     *
     * Returns an array of keys, an array of key-value pairs, a generator to yield
     * from an array of key-values pairs, and whether the generator should be used
     * for each test case.
     *
     * @return array
     */
    public function data_multipleValues(): array
    {
        $keys = ["key1", "key2", "key3"];
        $values = array_combine($keys, ["value1", "value2", "value3"]);

        $generator = function ($array) {
            foreach ($array as $key => $value) {
                yield $key => $value;
            }
        };

        $data = [];

        foreach ([false, true] as $use_generator) {
            // Push arguments to data set
            $data[] = [$keys, $values, $generator, $use_generator];
        }

        return $data;
    }

    /**
     * @dataProvider data_invalidKeys
     */
    public function test_invalidKeys(string $method, mixed $key, array $args)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->$method($key, ...$args);
    }

    /**
     * @dataProvider data_invalidTTLs
     */
    public function test_invalidTTLs(string $method, array $args)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->$method(...$args);
    }

    /**
     * @dataProvider data_invalidIterables
     */
    public function test_invalidIterables(string $method, array $args)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->$method(...$args);
    }

    /**
     * @dataProvider data_validCacheItems
     */
    public function test_setGet(mixed $value)
    {
        $this->cache->set("foo", $value);
        $this->assertEquals($value, $this->cache->get("foo"));
    }

    /**
     * @depends test_setGet
     */
    public function test_setGet_notFound()
    {
        $default = "chickpeas";
        $this->assertNull($this->cache->get("not-found"));
        $this->assertEquals($default, $this->cache->get("not-found", $default));
    }

    /**
     * @depends test_setGet
     */
    public function test_setGet_changed()
    {
        $test_class = new class {
            public string $foo = "bar";
        };

        $test_object = new $test_class();
        $this->cache->set("foo", $test_object);
        $test_object->foo = "bar";
        $this->assertEquals("bar", $this->cache->get("foo"));
    }

    /**
     * @depends      test_setGet
     * @depends      test_setGet_notFound
     * @dataProvider data_ttls
     * @group slow
     */
    public function test_set_expire(int|DateInterval|null $ttl, bool $expired)
    {
        $this->cache->set("foo", "bar", $ttl);
        $expected = $expired ? null : "bar";

        // Wait 2 seconds so the cache expires
        sleep(2);

        $this->assertEquals($expected, $this->cache->get("foo"));
    }

    /**
     * @depends test_setGet_notFound
     */
    public function test_delete()
    {
        $this->cache->set("foo", "bar");
        $this->cache->delete("foo");
        $this->assertNull($this->cache->get("foo"));
    }

    /**
     * @depends test_setGet_notFound
     */
    public function test_clear()
    {
        $this->cache->set("foo", "bar");
        $this->cache->clear();
        $this->assertNull($this->cache->get("foo"));
    }

    /**
     * @dataProvider data_multipleValues
     */
    public function test_setGetMultiple(array $keys, array $values, $generator, bool $useGenerator)
    {
        $this->cache->setMultiple($values);
        $result = (array) $this->cache->getMultiple($useGenerator ? $generator($keys) : $keys);

        $this->assertEquals($keys, array_keys($result));

        foreach ($result as $key => $value) {
            $this->assertEquals($values[$key], $value);
        }
    }

    /**
     * @depends      test_setGetMultiple
     * @dataProvider data_ttls
     * @group slow
     */
    public function test_setMultiple_expire(int|DateInterval|null $ttl, bool $expired)
    {
        $keys = ["key1", "key2", "key3"];
        $values = array_combine($keys, ["value1", "value2", "value3"]);

        $this->cache->setMultiple($values, $ttl);

        // Wait 2 seconds so the cache expires
        sleep(2);

        $result = $this->cache->getMultiple($keys);
        $expected = $expired ? array_fill_keys($keys, null) : $values;

        foreach ($result as $key => $value) {
            $this->assertEquals($expected[$key], $value);
        }
    }

    /**
     * @depends      test_setGetMultiple
     * @dataProvider data_multipleValues
     */
    public function test_deleteMultiple(array $keys, array $values, $generator, bool $useGenerator)
    {
        $deleted_keys = [$keys[0], $keys[2]];
        $default = "tea";

        $this->cache->setMultiple($values);
        $this->cache->deleteMultiple($useGenerator ? $generator($deleted_keys) : $deleted_keys);
        $result = $this->cache->getMultiple($keys, $default);

        $expected = array_merge(["key2" => "value2"], array_fill_keys($deleted_keys, $default));

        foreach ($result as $key => $value) {
            $this->assertEquals($expected[$key], $value);
        }
    }

    /**
     * @depends test_setGet
     */
    public function test_has()
    {
        $this->cache->set("foo", "bar");
        $this->assertTrue($this->cache->has("foo"));
        $this->assertFalse($this->cache->has("not-found"));
    }

    /**
     * @depends      test_setGet
     * @dataProvider data_ttls
     * @group slow
     */
    public function test_has_expire(int|DateInterval|null $ttl, bool $expired)
    {
        $this->cache->set("foo", "bar", $ttl);
        $expected = !$expired;

        // Wait 2 seconds so the cache expires
        sleep(2);

        $this->assertEquals($expected, $this->cache->has("foo"));
    }
}
