<?php

namespace Simon\Shared\Application;

use Closure;

interface UnitOfWork
{
    public function run(Closure $operation): mixed;
}
