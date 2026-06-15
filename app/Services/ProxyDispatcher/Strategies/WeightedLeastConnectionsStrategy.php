<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;

final class WeightedLeastConnectionsStrategy implements DispatcherStrategyInterface
{
    public function select(Collection $nodes, ?string $payloadKey = null): ?ServerNode
    {
        $eligible = $nodes
            ->filter(fn(ServerNode $n) => $n->online)
            ->values();

        if ($eligible->isEmpty()) {
            return null;
        }

        return $eligible
            ->sortBy(fn(ServerNode $n) => $n->connectionRatio())
            ->first();
    }

    public function name(): string
    {
        return 'Weighted Least Connections';
    }

    public function description(): string
    {
        return 'Routes to the node with the lowest connection-to-weight ratio '
            . '(Cᵢ / Wᵢ). High-capacity nodes (higher weight) can absorb '
            . 'more connections before being considered "full". '
            . 'Best for heterogeneous server tiers.';
    }
}
