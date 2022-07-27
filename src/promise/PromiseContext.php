<?php

namespace vezdehod\asyncpm\promise;

use Closure;
use LogicException;
use vezdehod\asyncpm\promise\result\FulfilledPromiseResult;
use vezdehod\asyncpm\promise\result\IPromiseResult;
use vezdehod\asyncpm\promise\result\RejectedPromiseResult;

/**
 * @template T
 */
class PromiseContext {
    /** @var FulfilledPromiseResult<T>|RejectedPromiseResult|null */
    private ?IPromiseResult $result = null;
    /** @var (Closure(FulfilledPromiseResult<T>|RejectedPromiseResult): void)[] */
    private array $handlers = [];

    /**
     * @param FulfilledPromiseResult<T>|RejectedPromiseResult $result
     */
    public function setResult(IPromiseResult $result): void {
        if ($this->result !== null) throw new LogicException("Promise can not be resolved twice!");
        $this->result = $result;
        foreach ($this->handlers as $handler) {
            $handler($this->result);
        }
        $this->handlers = [];
    }

    /**
     * @return FulfilledPromiseResult<T>|RejectedPromiseResult|null
     */
    public function getResult(): ?IPromiseResult { return $this->result; }

    /**
     * @param Closure(FulfilledPromiseResult<T>|RejectedPromiseResult): void $handler
     */
    public function onResult(Closure $handler): void {
        if ($this->result !== null) {
            $handler($this->result);
            return;
        }
        $this->handlers[] = $handler;
    }
}