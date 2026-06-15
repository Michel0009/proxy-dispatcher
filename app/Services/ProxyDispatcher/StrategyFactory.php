<?php

declare(strict_types=1);

namespace App\Services\ProxyDispatcher;

use App\Services\ProxyDispatcher\Contracts\DispatcherStrategyInterface;
use App\Services\ProxyDispatcher\Strategies\AdaptiveFeedbackStrategy;
use App\Services\ProxyDispatcher\Strategies\ConsistentHashingStrategy;
use App\Services\ProxyDispatcher\Strategies\IdleJoinQueueStrategy;
use App\Services\ProxyDispatcher\Strategies\LatencyBasedStrategy;
use App\Services\ProxyDispatcher\Strategies\LeastConnectionsStrategy;
use App\Services\ProxyDispatcher\Strategies\PerformanceBasedStrategy;
use App\Services\ProxyDispatcher\Strategies\RoundRobinStrategy;
use App\Services\ProxyDispatcher\Strategies\ServerMeshStrategy;
use App\Services\ProxyDispatcher\Strategies\SmoothWeightedRoundRobinStrategy;
use App\Services\ProxyDispatcher\Strategies\WeightedLeastConnectionsStrategy;
use App\Services\ProxyDispatcher\Strategies\WeightedRoundRobinStrategy;
use InvalidArgumentException;


final class StrategyFactory
{

    public const STRATEGIES = [
        'round_robin'                => RoundRobinStrategy::class,
        'weighted_round_robin'       => WeightedRoundRobinStrategy::class,
        'smooth_weighted_round_robin' => SmoothWeightedRoundRobinStrategy::class,
        'consistent_hashing'         => ConsistentHashingStrategy::class,
        'adaptive_feedback'          => AdaptiveFeedbackStrategy::class,
        'latency_based'              => LatencyBasedStrategy::class,
        'performance_based'          => PerformanceBasedStrategy::class,
        'server_mesh'                => ServerMeshStrategy::class,
        'idle_join_queue'            => IdleJoinQueueStrategy::class,
        'least_connections'          => LeastConnectionsStrategy::class,
        'weighted_least_connections' => WeightedLeastConnectionsStrategy::class,
    ];

    public static function make(string $key): DispatcherStrategyInterface
    {
        if (!array_key_exists($key, self::STRATEGIES)) {
            throw new InvalidArgumentException(
                "Unknown dispatcher strategy [{$key}]. "
                    . 'Valid options: ' . implode(', ', array_keys(self::STRATEGIES))
            );
        }

        $class = self::STRATEGIES[$key];

        return new $class();
    }

    public static function catalog(): array
    {
        $catalog = [];

        foreach (self::STRATEGIES as $key => $class) {
            $instance = new $class();

            $catalog[$key] = [
                'key'         => $key,
                'name'        => $instance->name(),
                'description' => $instance->description(),
            ];
        }

        return $catalog;
    }
}
