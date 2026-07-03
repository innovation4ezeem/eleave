<?php

namespace App\Services;

use App\Models\PerformanceComponent;

class PerformanceScoreService
{
    public function __construct(private ExpressionEvaluator $evaluator)
    {
    }

    /**
     * Compute the score for each active component from the given input values.
     *
     * @param  array<string, float|int>  $values  input key => value
     * @return array{scores: array<string, float>, total: float}
     */
    public function compute(array $values): array
    {
        $scores = [];
        $total = 0.0;

        foreach (PerformanceComponent::active()->orderBy('sort_order')->get() as $component) {
            try {
                $score = round($this->evaluator->evaluate($component->expression, $values), 2);
            } catch (\Throwable $e) {
                // A malformed stored expression scores 0 rather than breaking the whole entry.
                $score = 0.0;
            }

            $scores[$component->name] = $score;
            $total += $score;
        }

        return [
            'scores' => $scores,
            'total' => round($total, 2),
        ];
    }
}
