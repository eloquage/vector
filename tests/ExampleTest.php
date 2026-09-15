<?php

use Eloquage\Vector\Vector;

it('bootstraps the package entrypoint', function () {
    $instance = new Vector;

    expect($instance->name())->toBe('vector');
});
