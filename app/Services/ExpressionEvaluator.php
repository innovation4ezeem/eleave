<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * A small, safe arithmetic expression evaluator — NO use of eval().
 *
 * Supports: numbers (int/decimal), variable identifiers, the operators
 * + - * / and parentheses, plus unary minus. Used for admin-defined
 * performance scoring formulas, e.g. "(achieved / objective) * 50".
 *
 * Grammar (recursive descent):
 *   expr   = term (('+' | '-') term)*
 *   term   = factor (('*' | '/') factor)*
 *   factor = '-' factor | '(' expr ')' | number | identifier
 */
class ExpressionEvaluator
{
    /** @var array<int, array{type: string, value: string}> */
    private array $tokens = [];

    private int $pos = 0;

    /** @var array<string, float> */
    private array $vars = [];

    private bool $divisionByZero = false;

    /**
     * Evaluate an expression given a map of variable name => value.
     *
     * @param  array<string, float|int>  $vars
     *
     * @throws InvalidArgumentException on malformed expression or unknown identifier
     */
    public function evaluate(string $expression, array $vars): float
    {
        $this->tokens = $this->tokenize($expression);
        $this->pos = 0;
        $this->vars = array_map(fn ($v) => (float) $v, $vars);
        $this->divisionByZero = false;

        $result = $this->parseExpr();

        if ($this->pos < count($this->tokens)) {
            throw new InvalidArgumentException('Unexpected token near the end of the expression.');
        }

        return $result;
    }

    /**
     * Validate an expression against a set of allowed variable keys.
     * Returns an error message, or null if the expression is valid.
     *
     * @param  array<int, string>  $allowedKeys
     */
    public function validate(string $expression, array $allowedKeys): ?string
    {
        if (trim($expression) === '') {
            return 'Expression cannot be empty.';
        }

        $vars = [];
        foreach ($allowedKeys as $key) {
            $vars[$key] = 1.0;
        }

        try {
            // Detect unknown identifiers by tokenizing and checking each identifier.
            foreach ($this->tokenize($expression) as $token) {
                if ($token['type'] === 'ident' && ! array_key_exists($token['value'], $vars)) {
                    return "Unknown variable \"{$token['value']}\". Allowed: ".implode(', ', $allowedKeys).'.';
                }
            }

            $this->evaluate($expression, $vars);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @return array<int, array{type: string, value: string}>
     */
    private function tokenize(string $expr): array
    {
        $tokens = [];
        $len = strlen($expr);
        $i = 0;

        while ($i < $len) {
            $ch = $expr[$i];

            if (ctype_space($ch)) {
                $i++;
                continue;
            }

            if (str_contains('+-*/()', $ch)) {
                $tokens[] = ['type' => 'op', 'value' => $ch];
                $i++;
                continue;
            }

            if (ctype_digit($ch) || $ch === '.') {
                $num = '';
                while ($i < $len && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                    $num .= $expr[$i];
                    $i++;
                }
                if (! is_numeric($num)) {
                    throw new InvalidArgumentException("Invalid number \"{$num}\".");
                }
                $tokens[] = ['type' => 'num', 'value' => $num];
                continue;
            }

            if (ctype_alpha($ch) || $ch === '_') {
                $ident = '';
                while ($i < $len && (ctype_alnum($expr[$i]) || $expr[$i] === '_')) {
                    $ident .= $expr[$i];
                    $i++;
                }
                $tokens[] = ['type' => 'ident', 'value' => $ident];
                continue;
            }

            throw new InvalidArgumentException("Invalid character \"{$ch}\" in expression.");
        }

        return $tokens;
    }

    private function parseExpr(): float
    {
        $value = $this->parseTerm();

        while ($this->currentIsOp('+') || $this->currentIsOp('-')) {
            $op = $this->tokens[$this->pos]['value'];
            $this->pos++;
            $right = $this->parseTerm();
            $value = $op === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    private function parseTerm(): float
    {
        $value = $this->parseFactor();

        while ($this->currentIsOp('*') || $this->currentIsOp('/')) {
            $op = $this->tokens[$this->pos]['value'];
            $this->pos++;
            $right = $this->parseFactor();

            if ($op === '*') {
                $value *= $right;
            } else {
                if ($right == 0.0) {
                    // Division by zero yields 0 (documented behaviour) rather than an error,
                    // so an empty/zero denominator simply scores that component as 0.
                    $value = 0.0;
                } else {
                    $value /= $right;
                }
            }
        }

        return $value;
    }

    private function parseFactor(): float
    {
        if ($this->currentIsOp('-')) {
            $this->pos++;

            return -$this->parseFactor();
        }

        if ($this->currentIsOp('(')) {
            $this->pos++;
            $value = $this->parseExpr();
            if (! $this->currentIsOp(')')) {
                throw new InvalidArgumentException('Missing closing parenthesis.');
            }
            $this->pos++;

            return $value;
        }

        $token = $this->tokens[$this->pos] ?? null;

        if ($token === null) {
            throw new InvalidArgumentException('Unexpected end of expression.');
        }

        if ($token['type'] === 'num') {
            $this->pos++;

            return (float) $token['value'];
        }

        if ($token['type'] === 'ident') {
            $this->pos++;
            if (! array_key_exists($token['value'], $this->vars)) {
                throw new InvalidArgumentException("Unknown variable \"{$token['value']}\".");
            }

            return $this->vars[$token['value']];
        }

        throw new InvalidArgumentException("Unexpected token \"{$token['value']}\".");
    }

    private function currentIsOp(string $op): bool
    {
        $token = $this->tokens[$this->pos] ?? null;

        return $token !== null && $token['type'] === 'op' && $token['value'] === $op;
    }
}
