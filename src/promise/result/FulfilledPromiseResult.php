<?php

namespace vezdehod\asyncpm\promise\result;

/**
 * @template T
 */
class FulfilledPromiseResult {
    /**
     * @param T $value
     */
    public function __construct(private mixed $value) { }

    /**
     * @return T
     */
    public function getValue(): mixed { return $this->value; }
}