<?php

namespace vezdehod\asyncpm\promise;

/**
 * @see Promise::await
 *
 * @template R
 * @param Promise<R> $awaitable
 * @return R
 */
function await(Promise $awaitable): mixed {
    return Promise::await($awaitable);
}