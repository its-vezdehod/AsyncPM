<?php


use PHPUnit\Framework\TestCase;
use vezdehod\asyncpm\promise\Async;
use vezdehod\asyncpm\promise\Promise;
use vezdehod\asyncpm\promise\result\FulfilledPromiseResult;
use vezdehod\asyncpm\promise\result\RejectedPromiseResult;

class PromiseTest extends TestCase {

    public function testOnCompletion(): void {
        $value = mt_rand();
        $flag = false;
        Promise::fulfilled($value)->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($value, &$flag): void {
            $this->assertInstanceOf(FulfilledPromiseResult::class, $result);
            $this->assertEquals($value, $result->getValue());
            $flag = true;
        });
        $this->assertTrue($flag);

        $reason = new Exception("Test");
        $flag = false;
        Promise::rejected($reason)->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($reason, &$flag): void {
            $this->assertInstanceOf(RejectedPromiseResult::class, $result);
            $this->assertEquals($reason, $result->getReason());
            $flag = true;
        });
        $this->assertTrue($flag);

        $value = mt_rand();
        $flag = false;
        Promise::fulfilled($value)->onCompletion(function (?int $actual, ?Throwable $exception) use ($value, &$flag): void {
            $this->assertNull($exception);
            $this->assertEquals($value, $actual);
            $flag = true;
        });
        $this->assertTrue($flag);

        $reason = new Exception("Test");
        $flag = false;
        Promise::rejected($reason)->onCompletion(function (?int $actual, ?Throwable $exception) use ($reason, &$flag): void {
            $this->assertNull($actual);
            $this->assertEquals($reason, $exception);
            $flag = true;
        });
        $this->assertTrue($flag);
    }

    public function testThenPure(): void {
        $value = mt_rand();
        $add = mt_rand();
        Promise::fulfilled($value)->then(function (int $actual) use ($add, $value): int {
            $this->assertEquals($value, $actual);
            return $value + $add;
        })->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($add, $value): void {
            $this->assertInstanceOf(FulfilledPromiseResult::class, $result);
            $this->assertEquals($value + $add, $result->getValue());
        });
    }

    public function testThenCoroutine(): void {
        $value = mt_rand();
        $add = mt_rand();
        Promise::fulfilled($value)->then(function (int $actual) use ($add, $value): Generator {
            $this->assertEquals($value, $actual);
            $newValue = yield Promise::fulfilled($actual + $add);
            $this->assertEquals($value + $add, $newValue);
            return $newValue;
        })->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($add, $value): void {
            $this->assertInstanceOf(FulfilledPromiseResult::class, $result);
            $this->assertEquals($value + $add, $result->getValue());
        });
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testThenAsync(): void {
        $value = mt_rand();
        $add = mt_rand();
        Promise::fulfilled($value)->then(#[Async] function (int $actual) use ($add, $value): int {
            $this->assertNotNull(Fiber::getCurrent());

            $this->assertEquals($value, $actual);
            $newValue = Promise::await(Promise::fulfilled($actual + $add));
            $this->assertEquals($value + $add, $newValue);
            return $newValue;
        })->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($add, $value): void {
            $this->assertInstanceOf(FulfilledPromiseResult::class, $result);
            $this->assertEquals($value + $add, $result->getValue());
        });
    }
}
