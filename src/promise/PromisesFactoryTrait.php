<?php

namespace vezdehod\asyncpm\promise;

use Closure;
use Fiber;
use Generator;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;
use vezdehod\asyncpm\promise\result\FulfilledPromiseResult;
use vezdehod\asyncpm\promise\result\RejectedPromiseResult;
use vezdehod\asyncpm\promise\state\AsyncState;
use vezdehod\asyncpm\promise\state\FiberAsyncStateStorage;
use vezdehod\asyncpm\promise\state\InvalidAsyncStateException;
use function count;
use function gettype;

trait PromisesFactoryTrait {

    /**
     * Returns a fulfilled promise
     * @template R
     * @param R $value
     * @return Promise<R>
     */
    public static function fulfilled(mixed $value): Promise {
        $resolver = new PromiseResolver();
        $resolver->fulfill($value);
        return $resolver->promise();
    }

    /**
     * Returns a rejected promise
     * @param Throwable $reason
     * @return Promise<mixed>
     */
    public static function rejected(Throwable $reason): Promise {
        $resolver = new PromiseResolver();
        $resolver->reject($reason);
        return $resolver->promise();
    }

    /**
     * Returns a promise that will contain an array of FulfilledPromiseResult or RejectedPromiseResult
     * @template R
     * @param array<array-key, Promise<R>> $promises
     * @return Promise<array<array-key, FulfilledPromiseResult<R>|RejectedPromiseResult>>
     */
    public static function allResults(array $promises): Promise {
        $count = count($promises);
        $gigachad = new PromiseResolver();
        $rs = [];

        foreach ($promises as $k => $promise) {
            $promise->onCompletionResult(static function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($k, $gigachad, $count, &$rs) {
                $rs[$k] = $result;
                if (count($rs) === $count) {
                    $gigachad->fulfill($rs);
                }
            });
        }
        if ($count === 0) {
            $gigachad->fulfill($rs);
        }


        return $gigachad->promise();
    }

    /**
     * Returns a promise that will contain an array of values (or a rejected promise if one of the promises was rejected)
     * @template R
     * @param Promise<R>[] $promises
     * @return Promise<R[]>
     */
    public static function all(array $promises): Promise {
        $resolver = new PromiseResolver();

        self::allResults($promises)->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($resolver): void {
            if ($result instanceof RejectedPromiseResult) {
                $resolver->reject($result->getReason());
                return;
            }
            $resolveWith = [];
            foreach ($result->getValue() as $i => $inner) {
                if ($inner instanceof RejectedPromiseResult) {
                    $resolver->reject($inner->getReason());
                    return;
                }
                $resolveWith[$i] = $inner->getValue();
            }
            $resolver->fulfill($resolveWith);
        });

        return $resolver->promise();
    }

    /**
     * Returns a promise that will be rejected or fulfilled with first rejected/promise reason/value
     * @template R
     * @param Promise<R>[] $promises
     * @return Promise<FulfilledPromiseResult<R>|RejectedPromiseResult>
     */
    public static function any(array $promises): Promise {
        $gigachad = new PromiseResolver();
        foreach ($promises as $promise) {
            if ($promise->getResult() !== null) {
                $gigachad->depends($promise->getResult());
                return $gigachad->promise();
            }
        }

        foreach ($promises as $promise) {
            $promise->onCompletionResult(static function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($gigachad): void {
                if ($gigachad->getResult() !== null) {
                    return;
                }
                $gigachad->depends($result);
            });
        }

        return $gigachad->promise();
    }

    /**
     * Returns a promise that will contain a first fulfilled value
     * @template R
     * @param Promise<R>[] $promises
     * @return Promise<R>
     */
    public static function race(array $promises): Promise {
        if (count($promises) === 0) {
            throw new InvalidArgumentException("At least one promise should be passed");
        }

        $gigachad = new PromiseResolver();
        foreach ($promises as $promise) {
            if ($promise->getResult() instanceof FulfilledPromiseResult) {
                $gigachad->fulfill($promise->getResult()->getValue());
                return $gigachad->promise();
            }
        }

        $left = count($promises);
        foreach ($promises as $promise) {
            $promise->onCompletionResult(static function (FulfilledPromiseResult|RejectedPromiseResult $result) use (&$left, $gigachad): void {
                if ($gigachad->getResult() !== null) {
                    return;
                }
                if ($result instanceof FulfilledPromiseResult) {
                    $gigachad->fulfill($result->getValue());
                }
                if (--$left <= 0) {
                    $gigachad->reject(new LogicException("All promises rejected"));
                }
            });
        }

        return $gigachad->promise();
    }

    /**
     * Converts promised coroutine to Promise
     * @template R
     * @param (Closure(mixed=, AsyncState<R>=): Generator<mixed, Promise<mixed>, mixed, R>) $generator
     * @param mixed ...$values
     * @return Promise<R>
     */
    public static function coroutine(Closure $generator, mixed ...$values): Promise {
        $resolver = new PromiseResolver();
        $state = new AsyncState($resolver);
        $values[] = $state;
        $generator = $generator(...$values);
        if (!($generator instanceof Generator)) {
            throw new InvalidArgumentException("Closure(): Generator excepted, Closure(): " . gettype($generator) . " provided");
        }

        self::resolveCoroutinePromise($state, $resolver, $generator);

        return $resolver->promise();
    }

    /**
     * Wraps closure into closure that contains Fiber and returns Promise that will be resolved when Fiber reach end
     *
     * @template R
     * @param Closure(mixed=, AsyncState<R>=): R $asyncable
     * @return Closure(mixed=): Promise<R>
     */
    public static function wrapAsync(Closure $asyncable): Closure {
        return static function (mixed ...$values) use ($asyncable): Promise {
            $resolver = new PromiseResolver();
            $fiber = new Fiber(function () use ($asyncable, $resolver, $values): void { // @phpstan-ignore-line
                try {
                    FiberAsyncStateStorage::store($state = new AsyncState($resolver));
                    $values[] = $state;
                    $v = $asyncable(...$values);
                    if ($resolver->getResult() === null) {
                        $resolver->fulfill($v);
                    }
                } catch (Throwable $exception) {
                    if ($resolver->getResult() === null) {
                        $resolver->reject($exception);
                    }
                } finally {
                    FiberAsyncStateStorage::free();
                }
            });

            try {
                $fiber->start(); // @phpstan-ignore-line
            } catch (Throwable $exception) {
                $resolver->reject($exception);
            }

            return $resolver->promise();
        };
    }

    /**
     * Immediately calls {@see self::wrapAsync} with provided arguments
     *
     * @template R
     * @param Closure(mixed=, AsyncState<R>=): R $asyncable
     * @param mixed ...$values
     * @return Promise<R>
     */
    public static function async(Closure $asyncable, mixed ...$values): Promise { return self::wrapAsync($asyncable)(...$values); }

    /**
     * Suspends await fiber until promise fulfilled/rejected
     *
     * @template R
     * @param Promise<R> $awaitable
     * @return R
     */
    public static function await(Promise $awaitable): mixed {
        $fiber = Fiber::getCurrent(); // @phpstan-ignore-line
        if ($fiber === null) throw new RuntimeException("await allowed only in async scope");

        $result = $awaitable->getResult();
        if ($result !== null) {
            if (FiberAsyncStateStorage::get()->tryResolve()) {
                throw new InvalidAsyncStateException("Should be already resolved");
            }
            if ($result instanceof RejectedPromiseResult) {
                throw $result->getReason();
            }

            return $result->getValue();
        }

        $awaitable->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($fiber) {
            if (!FiberAsyncStateStorage::get()->tryResolve()) {
                if ($result instanceof RejectedPromiseResult) {
                    $fiber->throw($result->getReason()); // @phpstan-ignore-line
                } else {
                    $fiber->resume($result->getValue()); // @phpstan-ignore-line
                }
            }
        });

        return Fiber::suspend(); // @phpstan-ignore-line
    }

    /**
     * @template R
     * @param AsyncState<R> $state
     * @param PromiseResolver<R> $resolver
     * @param Generator<mixed, Promise<mixed>, mixed, R> $generator
     */
    private static function resolveCoroutinePromise(AsyncState $state, PromiseResolver $resolver, Generator $generator): void {
        $promise = $generator->current();
        if (!$generator->valid()) {
            $resolver->fulfill($generator->getReturn());
            return;
        }

        if (!($promise instanceof self)) {
            throw new InvalidArgumentException("Only promises can be yield'ed!");
        }

        $promise->onCompletionResult(static function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($state, $generator, $resolver) {
            if ($state->tryResolve()) {
                return;
            }
            try {
                if ($result instanceof RejectedPromiseResult) {
                    $generator->throw($result->getReason());
                } else {
                    $generator->send($result->getValue());
                }
                self::resolveCoroutinePromise($state, $resolver, $generator);
            } catch (Throwable $exception) {
                $resolver->reject($exception);
            }
        });
    }

}