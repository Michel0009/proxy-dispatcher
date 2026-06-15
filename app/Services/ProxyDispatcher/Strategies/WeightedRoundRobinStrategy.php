<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;


final class WeightedRoundRobinStrategy implements DispatcherStrategyInterface
{
    private const REDIS_KEY = 'dispatcher:wrr:index';

    public function select(Collection $nodes, ?string $payloadKey = null): ?ServerNode
    {
        $online = $nodes->filter(fn(ServerNode $n) => $n->online)->values();

        if ($online->isEmpty()) {
            return null;
        }

        $totalWeight = $online->sum(fn(ServerNode $n) => $n->weight);

        if ($totalWeight === 0) {
            $index = (int) Redis::incr(self::REDIS_KEY) % $online->count();
            return $online->get($index);
        }

        $current = (int) Redis::incr(self::REDIS_KEY) % $totalWeight;

        if ((int) Redis::get(self::REDIS_KEY) >= $totalWeight * 10_000) {
            Redis::set(self::REDIS_KEY, 0);
        }


        $accumulated = 0;

        foreach ($online as $node) {
            $accumulated += $node->weight;

            if ($current < $accumulated) {
                return $node;
            }
        }

        return $online->last();
    }

    public function name(): string
    {
        return 'Weighted Round Robin';
    }

    public function description(): string
    {
        return 'Distributes traffic proportional to static node weights. '
            . 'Ideal when nodes have different hardware capacity tiers.';
    }
}
