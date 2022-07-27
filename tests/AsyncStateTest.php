<?php

use PHPUnit\Framework\TestCase;
use vezdehod\asyncpm\promise\Async;
use vezdehod\asyncpm\promise\Promise;
use vezdehod\asyncpm\promise\PromiseResolver;
use vezdehod\asyncpm\promise\result\FulfilledPromiseResult;
use vezdehod\asyncpm\promise\result\RejectedPromiseResult;
use vezdehod\asyncpm\promise\state\AsyncState;
use vezdehod\asyncpm\promise\state\InvalidAsyncStateException;

class AsyncStateTest extends TestCase {

    public function testPlainAsyncState(): void {
        $resolver = new PromiseResolver();
        $state = new AsyncState($resolver);
        $state->rejectIf(fn() => false, 'Test');
        $this->assertFalse($state->tryResolve());

        $state->fulfillIf(fn() => true, 5);
        $this->assertTrue($state->tryResolve());
        $this->assertInstanceOf(FulfilledPromiseResult::class, $resolver->getResult());
        $this->assertEquals(5, $resolver->getResult()->getValue());
    }

    public function testInCoroutine(): void {
        $promise = Promise::coroutine(function (AsyncState $state): Generator {
            $ref = 0;
            $state->fulfillIf(function () use (&$ref) {
                $ref++;
                return false;
            }, 1);
            $state->fulfillIf(function () use (&$ref) {
                $ref++;
                return false;
            }, 2);

            $this->assertEquals(2, $ref);
            yield Promise::fulfilled(null);
            $this->assertEquals(4, $ref);

            $state->fulfillIf(fn() => true, 3);

            return yield Promise::fulfilled(4);
        });

        $this->assertInstanceOf(FulfilledPromiseResult::class, $promise->getResult());
        $this->assertEquals(3, $promise->getResult()->getValue());


        $promise = Promise::coroutine(function (AsyncState $state): Generator {
            $ref = 0;
            $state->rejectIf(function () use (&$ref) {
                return $ref++ > 1;
            }, new InvalidAsyncStateException('Test!'));
            $this->assertEquals(1, $ref);

            $exception = null;
            try {
                yield Promise::rejected(new InvalidAsyncStateException('Test'));
            } catch (Throwable $exception) {
                $this->assertEquals("Test", $exception->getMessage());
            }
            $this->assertNotNull($exception);

            return yield Promise::fulfilled(4);
        });
        $this->assertInstanceOf(RejectedPromiseResult::class, $promise->getResult());
        $this->assertEquals("Test!", $promise->getResult()->getReason()->getMessage());
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testInFiber(): void {
        $promise = Promise::async(function (AsyncState $state): int {
            $ref = 0;
            $state->fulfillIf(function () use (&$ref) {
                $ref++;
                return false;
            }, 1);
            $state->fulfillIf(function () use (&$ref) {
                $ref++;
                return false;
            }, 2);

            $this->assertEquals(2, $ref);
            Promise::await(Promise::fulfilled(null));
            $this->assertEquals(4, $ref);

            $state->fulfillIf(fn() => true, 3);

            return Promise::await(Promise::fulfilled(4));
        });
        $this->assertInstanceOf(FulfilledPromiseResult::class, $promise->getResult());
        $this->assertEquals(3, $promise->getResult()->getValue());

        $promise = Promise::async(function (AsyncState $state): int {
            $ref = 0;
            $state->rejectIf(function () use (&$ref) {
                return $ref++ > 1;
            }, new InvalidAsyncStateException('Test!'));
            $this->assertEquals(1, $ref);

            $exception = null;
            try {
                Promise::await(Promise::rejected(new InvalidAsyncStateException('Test')));
            } catch (Throwable $exception) {
                $this->assertEquals("Test", $exception->getMessage());
            }
            $this->assertNotNull($exception);

            return Promise::await(Promise::fulfilled(4));
        });
        $this->assertInstanceOf(RejectedPromiseResult::class, $promise->getResult());
        $this->assertEquals("Test!", $promise->getResult()->getReason()->getMessage());
    }

    public function testThenCoroutine(): void {
        $promise = Promise::fulfilled(1)->then(function (int $v, AsyncState $state): Generator {
            $state->fulfillIf(fn() => true, $v + 1);
            return yield Promise::fulfilled(4);
        });

        $this->assertInstanceOf(FulfilledPromiseResult::class, $promise->getResult());
        $this->assertEquals(2, $promise->getResult()->getValue());
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testThenAsyncAwait(): void {
        $promise = Promise::fulfilled(1)->then(#[Async] function (int $v, AsyncState $state): int {
            $state->fulfillIf(fn() => true, $v + 1);
            return Promise::await(Promise::fulfilled(4));
        });

        $this->assertInstanceOf(FulfilledPromiseResult::class, $promise->getResult());
        $this->assertEquals(2, $promise->getResult()->getValue());
    }
}