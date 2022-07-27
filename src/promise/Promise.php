<?php

namespace vezdehod\asyncpm\promise;

use Closure;
use Generator;
use ReflectionFunction;
use Throwable;
use vezdehod\asyncpm\promise\result\FulfilledPromiseResult;
use vezdehod\asyncpm\promise\result\IPromiseResult;
use vezdehod\asyncpm\promise\result\RejectedPromiseResult;
use vezdehod\asyncpm\promise\state\AsyncState;

// Sorry for this ugly hack D:
// TODO: autoload for composer, hacky load for PMMP
require_once __DIR__ . '/functions.php';

/**
 * @template T
 */
class Promise {

    use PromisesFactoryTrait;

    /**
     * @param PromiseContext<T> $context
     * @internal
     */
    public function __construct(private PromiseContext $context) { }

    /**
     * @template R
     * @param (Closure(T, AsyncState<T>=): (R|Generator<mixed, Promise<mixed>, mixed, R>)) $then
     * @return Promise<R>
     */
    public function then(Closure $then): Promise {
        $resolver = new PromiseResolver();

        $this->onCompletionResult(static function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($resolver, $then) {
            if ($result instanceof RejectedPromiseResult) {
                $resolver->reject($result->getReason());
            } else {
                self::processHandler($then, $resolver, $result->getValue());
            }
        });

        return $resolver->promise();
    }

    /**
     * @template R
     * @param (Closure(Throwable, AsyncState<T>=): (R|Generator<mixed, Promise<mixed>, mixed, R>)) $catch
     * @return Promise<R>
     */
    public function catch(Closure $catch): Promise {
        $resolver = new PromiseResolver();

        $this->onCompletionResult(function (FulfilledPromiseResult|RejectedPromiseResult $result) use ($resolver, $catch) {
            if ($result instanceof FulfilledPromiseResult) {
                $resolver->fulfill($result->getValue());
            } else {
                self::processHandler($catch, $resolver, $result->getReason());
            }
        });

        return $resolver->promise();
    }

    /**
     * @template R
     * @template V
     * @param (Closure(V, AsyncState<R>=): (R|Generator<mixed, Promise<mixed>, mixed, R>)) $next
     * @param PromiseResolver<R> $resolver
     * @param V $value
     */
    private static function processHandler(Closure $next, PromiseResolver $resolver, mixed $value): void {
        try {
            if (count((new ReflectionFunction($next))->getAttributes(Async::class)) > 0) {
                /** @var Closure(V): R $next */
                self::async($next, $value)->onCompletionResult(fn($result) => $resolver->depends($result));
                return;
            }

            $value = $next($value, $state = new AsyncState($resolver));
            if ($value instanceof Generator) {
                self::resolveCoroutinePromise($state, $resolver, $value);
                return;
            }
            $resolver->fulfill($value);
        } catch (Throwable $exception) {
            $resolver->reject($exception);
        }
    }

    /**
     * @param Closure(FulfilledPromiseResult<T>|RejectedPromiseResult): void $handler
     */
    public function onCompletionResult(Closure $handler): void { $this->context->onResult($handler); }

    /**
     * @param Closure(T|null, Throwable|null): void $handler
     */
    public function onCompletion(Closure $handler): void {
        $this->context->onResult(static function(IPromiseResult $result) use ($handler) {
            $handler($result instanceof FulfilledPromiseResult ? $result->getValue() : null, $result instanceof RejectedPromiseResult ? $result->getReason() : null);
        });
    }

    /**
     * @return FulfilledPromiseResult<T>|RejectedPromiseResult|null
     */
    public function getResult(): ?IPromiseResult { return $this->context->getResult(); }
}