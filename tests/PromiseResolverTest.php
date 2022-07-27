<?php

use PHPUnit\Framework\TestCase;
use vezdehod\asyncpm\promise\PromiseResolver;
use vezdehod\asyncpm\promise\result\FulfilledPromiseResult;
use vezdehod\asyncpm\promise\result\RejectedPromiseResult;

class PromiseResolverTest extends TestCase {

    public function testFulfill(): void {
        $value = mt_rand();
        $resolver = new PromiseResolver();
        $resolver->fulfill($value);
        $this->assertInstanceOf(FulfilledPromiseResult::class, $resolver->getResult());
        $this->assertEquals($resolver->getResult()->getValue(), $value);
    }

    public function testReject(): void {
        $reason = new Exception("Test");
        $resolver = new PromiseResolver();
        $resolver->reject($reason);
        $this->assertInstanceOf(RejectedPromiseResult::class, $resolver->getResult());
        $this->assertEquals($resolver->getResult()->getReason(), $reason);
    }

    public function testIfFailedResolveTwice(): void {
        $this->expectExceptionMessage("Promise can not be resolved twice!");

        $resolver = new PromiseResolver();
        $resolver->reject(new Exception("Test"));
        $resolver->fulfill(5);
    }

    public function testSameResult(): void {
        $resolver = new PromiseResolver();
        $this->assertNull($resolver->getResult());
        $this->assertNull($resolver->promise()->getResult());
        $resolver->fulfill(null);
        $this->assertSame($resolver->getResult(), $resolver->promise()->getResult());
    }
}