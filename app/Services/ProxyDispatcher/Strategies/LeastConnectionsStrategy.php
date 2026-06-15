<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;


final class LeastConnectionsStrategy implements DispatcherStrategyInterface
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
            ->sortBy(fn(ServerNode $n) => $n->activeConnections)
            ->first();
    }

    public function name(): string
    {
        return 'Least Connections';
    }

    public function description(): string
    {
        return 'Routes each payload to the node with the fewest active connections. '
            . 'Greedy algorithm — locally optimal at each decision point. '
            . 'O(N) scan across all nodes. The chaos Peak Load trigger (Server C '
            . 'at 155 connections) will immediately isolate it via this strategy.';
    }
}
