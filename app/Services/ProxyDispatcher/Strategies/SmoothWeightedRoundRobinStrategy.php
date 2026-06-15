<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

final class SmoothWeightedRoundRobinStrategy implements DispatcherStrategyInterface
{
    private const REDIS_HASH = 'dispatcher:swrr:weights';

    public function select(Collection $nodes, ?string $payloadKey = null): ?ServerNode
    {
        $online = $nodes->filter(fn(ServerNode $n) => $n->online)->values();

        if ($online->isEmpty()) {
            return null;
        }

        $totalWeight = $online->sum(fn(ServerNode $n) => $n->weight);

        if ($totalWeight === 0) {
            return $online->first();
        }

        // Retry loop handles the rare WATCH-triggered transaction abort.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $result = $this->atomicSelectAndUpdate($online, $totalWeight);

            if ($result !== null) {
                return $result;
            }
        }

        return $online->first();
    }

    private function atomicSelectAndUpdate(
        Collection $online,
        int $totalWeight,
    ): ?ServerNode {
        Redis::watch(self::REDIS_HASH);

        $stored = Redis::hgetall(self::REDIS_HASH) ?: [];

        $currentWeights = [];

        foreach ($online as $node) {
            $currentWeights[$node->id] = (int) ($stored[$node->id] ?? 0) + $node->weight;
        }

        $selectedId     = null;
        $highestWeight  = PHP_INT_MIN;

        foreach ($currentWeights as $id => $cw) {
            if ($cw > $highestWeight) {
                $highestWeight = $cw;
                $selectedId    = $id;
            }
        }

        if ($selectedId !== null) {
            $currentWeights[$selectedId] -= $totalWeight;
        }

        $pipeline = Redis::multi();

        foreach ($currentWeights as $id => $cw) {
            $pipeline->hset(self::REDIS_HASH, $id, (string) $cw);
        }

        $executed = $pipeline->exec();

        if ($executed === null) {
            return null;
        }

        return $online->firstWhere('id', $selectedId);
    }

    public function name(): string
    {
        return 'Smooth Weighted Round Robin';
    }

    public function description(): string
    {
        return 'Nginx-style WRR that prevents burst traffic to the highest-weight '
            . 'node. Spreads requests evenly while still honouring capacity ratios. '
            . 'State (per-node current weights) is persisted atomically in Redis.';
    }
}
