<?php

namespace vezdehod\asyncpm\promise\result;

/**
 * @template T
 */
class FulfilledPromiseResult implements IPromiseResult {
    /**
     * @param T $value
     */
    public function __construct(private mixed $value) { }

    /**
     * @return T
     */
    public function getValue(): mixed { return $this->value; }
}