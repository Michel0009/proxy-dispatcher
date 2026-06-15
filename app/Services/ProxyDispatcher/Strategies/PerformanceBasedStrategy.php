<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;

final class PerformanceBasedStrategy implements DispatcherStrategyInterface
{
    private const MIN_HEALTH_SCORE = 0.1;

    public function select(Collection $nodes, ?string $payloadKey = null): ?ServerNode
    {
        $eligible = $nodes
            ->filter(fn(ServerNode $n) => $n->online)
            ->filter(fn(ServerNode $n) => $n->healthScore() >= self::MIN_HEALTH_SCORE)
            ->values();

        if ($eligible->isEmpty()) {
            $fallback = $nodes
                ->filter(fn(ServerNode $n) => $n->online)
                ->sortByDesc(fn(ServerNode $n) => $n->healthScore())
                ->first();

            return $fallback;
        }

        return $eligible
            ->sortByDesc(fn(ServerNode $n) => $n->healthScore())
            ->first();
    }

    public function name(): string
    {
        return 'Performance-Based';
    }

    public function description(): string
    {
        return 'Routes to the node with the highest composite health index '
            . '(successRate × CPU headroom × latency headroom). '
            . 'Nodes below 10 % health score are isolated automatically. '
            . 'Use the Peak Load chaos trigger to validate Server C isolation.';
    }
}
