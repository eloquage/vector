<?php

namespace Eloquage\Vector;

/**
 * Primary entrypoint for eloquage/vector.
 *
 * Pure-PHP implementation lives here. Optional TypePHP/native acceleration
 * can be added under native/ later without changing this public API.
 */
final class Vector
{
    public function name(): string
    {
        return 'vector';
    }
}
