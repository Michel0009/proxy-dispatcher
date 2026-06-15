<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;

final class LatencyBasedStrategy implements DispatcherStrategyInterface
{
    public function select(Collection $nodes, ?string $payloadKey = null): ?ServerNode
    {
        $eligible = $nodes
            ->filter(fn(ServerNode $n) => $n->online && $n->avgLatencyMs > 0)
            ->values();

        if ($eligible->isEmpty()) {
            return $nodes->filter(fn(ServerNode $n) => $n->online)->first();
        }

        return $eligible->sortBy(fn(ServerNode $n) => $n->avgLatencyMs)->first();
    }

    public function name(): string
    {
        return 'Latency-Based';
    }

    public function description(): string
    {
        return 'Routes to the node with the lowest exponential moving-average '
            . 'response time. Prioritises responsiveness over even distribution. '
            . 'EMA smoothing (α=0.2) prevents thrashing on transient spikes.';
    }
}
