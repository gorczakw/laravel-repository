<?php

declare(strict_types=1);

namespace Gorczakw\LaravelRepository\Contracts;

interface Filterable
{
    public function getFilterable(): array;
}
