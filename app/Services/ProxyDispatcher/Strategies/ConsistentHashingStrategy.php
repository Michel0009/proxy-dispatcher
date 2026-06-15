<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
final class ConsistentHashingStrategy implements DispatcherStrategyInterface
{
    private const RING_KEY       = 'dispatcher:ch:ring';
    private const MEMBERS_KEY    = 'dispatcher:ch:members';
    private const LOCK_KEY       = 'dispatcher:ch:rebuild_lock';
    private const VIRTUAL_NODES  = 150;
    private const LOCK_TTL_MS    = 500;

    public function select(Collection $nodes, ?string $payloadKey = null): ?ServerNode
    {
        $online = $nodes->filter(fn(ServerNode $n) => $n->online)->values();

        if ($online->isEmpty()) {
            return null;
        }

        $this->maybeRebuildRing($online);

        $key      = $payloadKey ?? (string) mt_rand();
        $hashPos  = $this->hash($key);

        $members = Redis::zrangebyscore(
            self::RING_KEY,
            (string) $hashPos,
            '+inf',
            ['limit' => [0, 1]],
        );

        if (empty($members)) {
            $members = Redis::zrange(self::RING_KEY, 0, 0);
        }

        if (empty($members)) {
            return null;
        }

        $nodeId = $this->extractNodeId((string) $members[0]);

        return $online->firstWhere('id', $nodeId);
    }

    private function maybeRebuildRing(Collection $online): void
    {
        $currentIds  = $online->pluck('id')->sort()->values()->implode(',');
        $storedIds   = implode(',', array_map(
            'strval',
            Redis::smembers(self::MEMBERS_KEY) ?: [],
        ));

        $storedArray = explode(',', $storedIds);
        sort($storedArray);
        $storedIds = implode(',', $storedArray);

        if ($currentIds === $storedIds) {
            return;
        }

        $locked = Redis::set(self::LOCK_KEY, '1', 'NX', 'PX', self::LOCK_TTL_MS);

        if (!$locked) {
            return;
        }

        try {
            $this->rebuildRing($online);
        } finally {
            Redis::del(self::LOCK_KEY);
        }
    }

    private function rebuildRing(Collection $online): void
    {
        Redis::del(self::RING_KEY, self::MEMBERS_KEY);

        $pipeline = Redis::pipeline();

        foreach ($online as $node) {
            $pipeline->sadd(self::MEMBERS_KEY, $node->id);

            for ($i = 0; $i < self::VIRTUAL_NODES; $i++) {
                $vnodeKey  = "{$node->id}#vnode{$i}";
                $position  = $this->hash($vnodeKey);

                $pipeline->zadd(self::RING_KEY, $position, $vnodeKey);
            }
        }

        $pipeline->execute();
    }

    private function hash(string $key): int
    {
        return (int) sprintf('%u', crc32($key));
    }

    private function extractNodeId(string $vnode): string
    {
        return (string) explode('#', $vnode)[0];
    }

    public function name(): string
    {
        return 'Consistent Hashing';
    }

    public function description(): string
    {
        return 'Maps payloads to nodes via a virtual hash ring (150 vnodes/server). '
            . 'Ensures the same payload UUID always routes to the same node, '
            . 'minimising cache misses during scaling events. Ring is stored '
            . 'as a Redis sorted set and rebuilt automatically on node changes.';
    }
}
