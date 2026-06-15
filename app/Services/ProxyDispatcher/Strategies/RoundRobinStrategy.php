<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;


final class RoundRobinStrategy implements DispatcherStrategyInterface
{
    private const REDIS_KEY = 'dispatcher:rr:index';

    public function select(Collection $nodes, ?string $payloadKey = null): ?ServerNode
    {
        $online = $nodes->filter(fn(ServerNode $n) => $n->online)->values();

        if ($online->isEmpty()) {
            return null;
        }

        $index = (int) Redis::incr(self::REDIS_KEY);
        $count = $online->count();

        if ($index >= $count * 10_000) {
            Redis::set(self::REDIS_KEY, 0);
        }

        return $online->get($index % $count);
    }

    public function name(): string
    {
        return 'Round Robin';
    }

    public function description(): string
    {
        return 'Cycles through nodes sequentially. O(1) cost. Blind to load — '
            . 'best only when all requests have equal processing cost.';
    }
}
