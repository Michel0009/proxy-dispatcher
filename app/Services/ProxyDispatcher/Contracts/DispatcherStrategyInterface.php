<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Contracts;

use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;


interface DispatcherStrategyInterface
{

    public function select(
        Collection $nodes,
        ?string $payloadKey = null,
    ): ?ServerNode;


    public function name(): string;


    public function description(): string;
}
