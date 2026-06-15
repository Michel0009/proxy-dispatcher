<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;


final class ServerMeshStrategy implements DispatcherStrategyInterface
{
    private const CIRCUIT_THRESHOLD = 0.1;

    public function select(Collection $nodes, ?string $payloadKey = null): ?ServerNode
    {
        $eligible = $nodes
            ->filter(fn(ServerNode $n) => $n->online && $n->meshWeight >= self::CIRCUIT_THRESHOLD)
            ->values();

        if ($eligible->isEmpty()) {
            return null;
        }

        $pool = [];

        foreach ($eligible as $node) {
            $slots = (int) max(1, round($node->meshWeight * 100));

            for ($i = 0; $i < $slots; $i++) {
                $pool[] = $node->id;
            }
        }

        if (empty($pool)) {
            return $eligible->first();
        }

        $selectedId = $pool[array_rand($pool)];

        return $eligible->firstWhere('id', $selectedId);
    }

    public function name(): string
    {
        return 'Server Mesh';
    }

    public function description(): string
    {
        return 'Simulates Istio/Envoy sidecar-aware routing. Nodes with higher '
            . 'mesh weight (reported by their simulated sidecar) receive '
            . 'proportionally more traffic. Nodes below 10 % mesh weight '
            . 'are circuit-broken and receive no traffic.';
    }
}
