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
     * Inputs may be partially filled: keys with a null/empty value are treated
     * as "not yet entered". A component whose expression references a missing
     * input is scored null (pending) and excluded from the total, so a partial
     * entry shows a partial score that completes once the remaining values are
     * filled in.
     *
     * @param  array<string, float|int|null>  $values  input key => value (null = blank)
     * @return array{scores: array<string, float|null>, total: float, complete: bool}
     */
    public function compute(array $values): array
    {
        // Only pass through inputs that actually have a value.
        $vars = [];
        foreach ($values as $key => $value) {
            if ($value !== null && $value !== '') {
                $vars[$key] = (float) $value;
            }
        }

        $scores = [];
        $total = 0.0;
        $complete = true;

        foreach (PerformanceComponent::active()->orderBy('sort_order')->get() as $component) {
            try {
                // Throws if the expression references an input that hasn't been entered.
                $score = round($this->evaluator->evaluate($component->expression, $vars), 2);
                $scores[$component->name] = $score;
                $total += $score;
            } catch (\Throwable $e) {
                // Missing input (or malformed expression) — leave this component pending.
                $scores[$component->name] = null;
                $complete = false;
            }
        }

        return [
            'scores' => $scores,
            'total' => round($total, 2),
            'complete' => $complete,
        ];
    }
}
