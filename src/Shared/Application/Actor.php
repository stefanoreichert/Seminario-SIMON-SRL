<?php

namespace Simon\Shared\Application;

final readonly class Actor
{
    public function __construct(public int $id, public string $name, public ?string $salesIdentifier = null) {}
}
