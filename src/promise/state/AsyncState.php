<?php

namespace vezdehod\asyncpm\promise\state;

use Closure;
use RuntimeException;
use Throwable;
use vezdehod\asyncpm\promise\PromiseResolver;
use vezdehod\asyncpm\promise\result\FulfilledPromiseResult;
use vezdehod\asyncpm\promise\result\RejectedPromiseResult;
use function array_pop;

/**
 * @template T
 */
class AsyncState {

    /** @var (Closure(): (FulfilledPromiseResult<T>|RejectedPromiseResult|null))[] */
    private array $runners = [];
    private int $runnerId = 0;

    /**
     * @param array<mixed|AsyncState<mixed>> $args
     * @return AsyncState<mixed>
     */
    public static function stateFromVararg(array &$args): AsyncState {
        $state = array_pop($args);
        if (!($state instanceof self)) {
            throw new RuntimeException("stateFromVararg must be invoked only with variadic args!");
        }

        return $state;
    }

    /**
     * @param PromiseResolver<T> $resolver
     */
    public function __construct(private PromiseResolver $resolver) { }

    /**
     * @param Closure(): (FulfilledPromiseResult<T>|RejectedPromiseResult|null) $runner
     * @return int Runner id
     */
    public function withRunner(Closure $runner): int {
        $result = ($runner)();
        if ($result !== null) {
            $this->resolver->depends($result);
            return -1;
        }
        $this->runners[$this->runnerId] = $runner;
        return $this->runnerId++;
    }

    public function unsubscribe(int $runnerId): void {
        unset($this->runners[$runnerId]);
    }

    /**
     * @param Closure(): bool $condition
     * @param string|Throwable $reason
     */
    public function rejectIf(Closure $condition, string|Throwable $reason = ''): void {
        $this->withRunner(static fn() => $condition() ?
            new RejectedPromiseResult($reason instanceof Throwable ? $reason : new InvalidAsyncStateException($reason)) :
            null
        );
    }

    /**
     * @param Closure(): bool $condition
     * @param T $value
     */
    public function fulfillIf(Closure $condition, mixed $value): void {
        $this->withRunner(static fn() => $condition() ?
            new FulfilledPromiseResult($value) :
            null
        );
    }

    /**
     * @param Closure(): bool $condition
     * @param string|Throwable $reason
     */
    public function requires(Closure $condition, string|Throwable $reason = ''): void {
        $this->rejectIf(static fn() => !$condition(), $reason);
    }

    /**
     * @return bool
     */
    public function tryResolve(): bool {
        if ($this->resolver->getResult() !== null) return true;
        foreach ($this->runners as $runner) {
            $result = ($runner)();
            if ($result !== null) {
                $this->resolver->depends($result);
                return true;
            }
        }
        return false;
    }
}