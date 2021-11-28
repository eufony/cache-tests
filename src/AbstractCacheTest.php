<?php

namespace Eufony\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Provides an abstract PSR-6 implementation tester.
 */
abstract class AbstractCacheTest extends TestCase
{
    /**
     * The PSR-6 cache implementation to test.
     *
     * @var \Psr\Cache\CacheItemPoolInterface $cache
     */
    protected CacheItemPoolInterface $cache;

    /**
     * Returns a new instance of a PSR-6 cache implementation to test.
     *
     * @return \Psr\Cache\CacheItemPoolInterface
     */
    abstract public function getCache(): CacheItemPoolInterface;
}
