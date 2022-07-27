<?php

namespace vezdehod\asyncpm\promise;

use Attribute;

/**
 * Closures in {@see Promise::then} and {@see Promise::catch}  with this attribute will be run in async context (fiber)
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Async {

}