<?php

namespace vezdehod\asyncpm\promise\state;

use Fiber;
use GlobalLogger;
use RuntimeException;
use function count;
use function spl_object_id;

/**
 * @internal
 */
class FiberAsyncStateStorage {

    public const MAX_CONCURRENT_STATES = 10_000; // Hmm... Is it enough?
    /** @var array<int, AsyncState<mixed>> */
    private static array $storage = [];

    /**
     * @param AsyncState<mixed> $state
     */
    public static function store(AsyncState $state): void {
        $fiber = Fiber::getCurrent();
        if ($fiber === null || isset(self::$storage[spl_object_id($fiber)])) {
            throw new RuntimeException("AsyncState must be created one time in fiber!");
        }
        self::$storage[spl_object_id($fiber)] = $state;
        static $notified = false;
        if (!$notified && count(self::$storage) >= self::MAX_CONCURRENT_STATES) {
            $notified = true;
            GlobalLogger::get()->warning("Too many concurrent async states are running. Make sure it's not a leak!");
        }
    }

    /**
     * @return AsyncState<mixed>
     */
    public static function get(): AsyncState {
        $fiber = Fiber::getCurrent();
        if ($fiber === null || !isset(self::$storage[spl_object_id($fiber)])) {
            throw new RuntimeException("This fiber not contains AsyncState");
        }
        return self::$storage[spl_object_id($fiber)];
    }

    public static function free(): void {
        $fiber = Fiber::getCurrent();
        if ($fiber === null || !isset(self::$storage[spl_object_id($fiber)])) {
            throw new RuntimeException("This fiber not contains AsyncState");
        }
        unset(self::$storage[spl_object_id($fiber)]);
    }
}