<?php

namespace vezdehod\asyncpm\promise;

use Throwable;
use vezdehod\asyncpm\promise\result\FulfilledPromiseResult;
use vezdehod\asyncpm\promise\result\RejectedPromiseResult;

/**
 * @template T
 */
class PromiseResolver {
    /** @var Promise<T> */
    private Promise $promise;
    /** @var PromiseContext<T> */
    private PromiseContext $context;

    public function __construct() {
        $this->context = new PromiseContext();
        $this->promise = new Promise($this->context);
    }

    /**
     * @param T $value
     */
    public function fulfill(mixed $value): void { $this->context->setResult(new FulfilledPromiseResult($value)); }

    public function reject(Throwable $exception): void { $this->context->setResult(new RejectedPromiseResult($exception)); }

    /**
     * @param FulfilledPromiseResult<T>|RejectedPromiseResult $result
     */
    public function depends(FulfilledPromiseResult|RejectedPromiseResult $result): void {
        if ($result instanceof RejectedPromiseResult) {
            $this->reject($result->getReason());
        } else {
            $this->fulfill($result->getValue());
        }
    }

    /**
     * @return FulfilledPromiseResult<T>|RejectedPromiseResult|null
     */
    public function getResult(): FulfilledPromiseResult|RejectedPromiseResult|null { return $this->context->getResult(); }

    /**
     * @return Promise<T>
     */
    public function promise(): Promise { return $this->promise; }
}