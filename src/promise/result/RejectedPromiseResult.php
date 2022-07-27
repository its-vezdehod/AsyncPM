<?php

namespace vezdehod\asyncpm\promise\result;

use Throwable;

class RejectedPromiseResult {
    public function __construct(private Throwable $reason) { }

    public function getReason(): Throwable { return $this->reason; }
}