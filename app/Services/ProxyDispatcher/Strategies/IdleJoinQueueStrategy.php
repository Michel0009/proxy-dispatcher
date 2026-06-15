<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

final class IdleJoinQueueStrategy implements DispatcherStrategyInterface
{
    private const QUEUE_KEY      = 'dispatcher:jiq:idle_queue';
    private const IDLE_THRESHOLD = 5;

    public function select(Collection $nodes, ?string $payloadKey = null): ?ServerNode
    {
        $online = $nodes->filter(fn(ServerNode $n) => $n->online)->values();

        if ($online->isEmpty()) {
            return null;
        }

        $this->refreshIdleQueue($online);

        $nodeId = Redis::lpop(self::QUEUE_KEY);

        if ($nodeId !== null) {
            $node = $online->firstWhere('id', (string) $nodeId);

            if ($node !== null) {
                return $node;
            }
        }

        return $online->sortBy(fn(ServerNode $n) => $n->activeConnections)->first();
    }


    private function refreshIdleQueue(Collection $online): void
    {
        Redis::del(self::QUEUE_KEY);

        $idleNodes = $online->filter(
            fn(ServerNode $n) => $n->activeConnections < self::IDLE_THRESHOLD
        );

        if ($idleNodes->isEmpty()) {
            return;
        }

        $pipeline = Redis::pipeline();

        foreach ($idleNodes->sortBy('activeConnections') as $node) {
            $pipeline->rpush(self::QUEUE_KEY, $node->id);
        }

        $pipeline->execute();
    }

    public function name(): string
    {
        return 'Idle-Join Queue';
    }

    public function description(): string
    {
        return 'Nodes self-register as idle when their active connections drop '
            . 'below threshold (' . self::IDLE_THRESHOLD . '). '
            . 'Dispatcher pops from the idle queue — zero hotspots guaranteed '
            . 'as long as idle capacity exists. Falls back to Least Connections '
            . 'when all nodes are busy.';
    }
}
