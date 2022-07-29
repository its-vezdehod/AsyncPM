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
    public static function store(Fiber $fiber, AsyncState $state): void {// @phpstan-ignore-line
        if (isset(self::$storage[spl_object_id($fiber)])) {
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
    public static function get(Fiber $fiber): AsyncState { // @phpstan-ignore-line
        return self::$storage[spl_object_id($fiber)] ?? throw new RuntimeException("This fiber not contains AsyncState");
    }

    public static function free(Fiber $fiber): void { // @phpstan-ignore-line
        if (!isset(self::$storage[spl_object_id($fiber)])) {
            throw new RuntimeException("This fiber not contains AsyncState");
        }
        unset(self::$storage[spl_object_id($fiber)]);
    }
}