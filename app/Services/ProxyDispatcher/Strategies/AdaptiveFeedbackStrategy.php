<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher\Strategies;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\ServerNode;
use Illuminate\Support\Collection;

final class AdaptiveFeedbackStrategy implements DispatcherStrategyInterface
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
            ->sortByDesc(fn(ServerNode $n) => $n->successRate)
            ->sortBy(function (ServerNode $n) use ($eligible) {
                return round($n->successRate, 2) === round(
                    $eligible->max(fn(ServerNode $x) => $x->successRate),
                    2
                ) ? $n->avgLatencyMs : PHP_FLOAT_MAX;
            })
            ->first();
    }

    public function name(): string
    {
        return 'Adaptive Feedback';
    }

    public function description(): string
    {
        return 'Routes to the node with the highest rolling success rate. '
            . 'Nodes with high error rates are automatically deprioritised '
            . 'and recover gradually as errors clear — self-healing routing.';
    }
}
