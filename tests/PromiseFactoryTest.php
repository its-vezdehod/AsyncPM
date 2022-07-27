<?php

use PHPUnit\Framework\TestCase;
use vezdehod\asyncpm\promise\Promise;
use vezdehod\asyncpm\promise\PromiseResolver;
use vezdehod\asyncpm\promise\result\FulfilledPromiseResult;
use vezdehod\asyncpm\promise\result\RejectedPromiseResult;

class PromiseFactoryTest extends TestCase {

    public function testFulfilled(): void {
        $value = mt_rand();
        $promise = Promise::fulfilled($value);
        $this->assertInstanceOf(FulfilledPromiseResult::class, $promise->getResult());
        $this->assertEquals($value, $promise->getResult()->getValue());
    }

    public function testRejected(): void {
        $reason = new Exception("Test");
        $promise = Promise::rejected($reason);
        $this->assertInstanceOf(RejectedPromiseResult::class, $promise->getResult());
        $this->assertEquals($reason, $promise->getResult()->getReason());
    }

    public function testAllResults(): void {
        $late = new PromiseResolver();
        Promise::allResults([
            'a' => Promise::fulfilled(1),
            'b' => Promise::fulfilled(2),
            'c' => Promise::fulfilled(3),
            'd' => $late->promise(),
            'e' => Promise::fulfilled(5),
        ])->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result): void {
            $this->assertEquals(['a', 'b', 'c', 'e', 'd'], array_keys($result->getValue()));
            $this->assertEquals([1, 2, 3, 5, 4], array_map(static fn($v) => $v->getValue(), array_values($result->getValue())));
        });
        $late->fulfill(4);
    }

    public function testAll(): void {
        $late = new PromiseResolver();
        Promise::all([
            'a' => Promise::fulfilled(1),
            'b' => Promise::fulfilled(2),
            'c' => Promise::fulfilled(3),
            'd' => $late->promise(),
            'e' => Promise::fulfilled(5),
        ])->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result): void {
            $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3, 'e' => 5, 'd' => 4], $result->getValue());
        });
        $late->fulfill(4);

        $reason = new Exception("Test");
        Promise::all([
            'a' => Promise::fulfilled(1),
            'b' => Promise::rejected($reason),
        ])->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($reason): void {
            $this->assertEquals($reason, $result->getReason());
        });
    }

    public function testAny(): void {
        $late = new PromiseResolver();
        Promise::any([
            'a' => $late->promise(),
            'b' => Promise::fulfilled(2),
            'c' => Promise::fulfilled(3),
        ])->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result): void {
            $this->assertEquals(2, $result->getValue());
        });
        $late->fulfill(1);
    }

    public function testRace(): void {
        $late = new PromiseResolver();
        Promise::race([
            'a' => $late->promise(),
            'b' => Promise::fulfilled(2),
            'c' => Promise::fulfilled(3),
        ])->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result): void {
            $this->assertEquals(2, $result->getValue());
        });
        $late->fulfill(1);

        $late = new PromiseResolver();
        Promise::race([
            'a' => Promise::rejected(new Exception("Test")),
            'b' => Promise::rejected(new Exception("Test")),
            'c' => Promise::rejected(new Exception("Test"))
        ])->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result): void {
            $this->assertEquals("All promises rejected", $result->getReason()->getMessage());
        });
        $late->fulfill(1);
    }

    public function testCoroutine(): void {
        Promise::coroutine(function (): Generator {
            $this->assertEquals(1, yield Promise::fulfilled(1));
            $this->assertEquals(2, yield Promise::fulfilled(2));
            $this->assertEquals(3, yield Promise::fulfilled(3));
            $exception = null;
            try {
                yield Promise::rejected(new Exception("Test"));
            } catch (Throwable $exception) {
                $this->assertEquals("Test", $exception->getMessage());
            }
            $this->assertNotNull($exception);
            return yield Promise::fulfilled(4);
        })->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result): void {
            $this->assertEquals(4, $result->getValue());
        });
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testAsyncAwait(): void {
        Promise::async(function (int $a, int $b): int {
            $this->assertEquals([1, 2], [$a, $b]);
            $this->assertEquals(1, Promise::await(Promise::fulfilled(1)));
            $this->assertEquals(2, Promise::await(Promise::fulfilled(2)));
            $this->assertEquals(3, Promise::await(Promise::fulfilled(3)));
            $exception = null;
            try {
                Promise::await(Promise::rejected(new Exception("Test")));
            } catch (Throwable $exception) {
                $this->assertEquals("Test", $exception->getMessage());
            }
            $this->assertNotNull($exception);
            return Promise::await(Promise::fulfilled(4));
        }, 1, 2)->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result): void {
            $this->assertEquals(4, $result->getValue());
        });
    }
}