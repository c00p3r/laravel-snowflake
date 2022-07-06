<?php

namespace App\Database\Snowflake\Grammar;

use App\Database\Snowflake\Processor;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Str;

use function count;

/**
 * This code is shared between the Query and Schema grammar.
 * Mainly for correcting the values and columns.
 *
 * Values: are wrapped within single quotes.
 * Columns and Table names: are wrapped within double quotes.
 */
trait GrammarHelper
{
    /**
     * Convert an array of column names into a delimited string.
     *
     * @param array $columns
     *
     * @return string
     */
    public function columnize(array $columns)
    {
        return implode(', ', array_map([$this, 'wrapColumn'], $columns));
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param Expression|string $table
     *
     * @return string
     */
    public function wrapTable($table): string
    {
        $table = Processor::wrapTable($table);

        if (method_exists($this, 'isExpression') && !$this->isExpression($table)) {
            // @phpstan-ignore-next-line
            return $this->wrap($this->getTablePrefix() . $table, true);
        }

        return $this->getValue($table);
    }

    /**
     * Get the value of a raw expression.
     *
     * @param Expression $expression
     *
     * @return string
     */
    public function getValue($expression): string
    {
        return $expression instanceof Expression ? $expression->getValue() : $expression;
    }

    /**
     * Wrap the given value segments.
     *
     * @param array $segments
     *
     * @return string
     */
    protected function wrapSegments($segments): string
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            return 0 === $key && count($segments) > 1
                ? $this->wrapTable($segment)
                // Original ->wraValue, but this is always called for columns segments
                : $this->wrapColumn($segment);
        })->implode('.');
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param ColumnDefinition|string $value
     *
     * @return string
     */
    protected function wrapColumn($column): string
    {
        if ($column instanceof ColumnDefinition) {
            $column = $column->get('name');
        }

        if ('*' !== $column) {
            if (!env('SNOWFLAKE_COLUMNS_CASE_SENSITIVE', false)) {
                return str_replace('"', '', Str::upper($column));
            }

            return '"' . str_replace('"', '""', $column) . '"';
        }

        return $column;
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param string $value
     *
     * @return string
     */
    protected function wrapValue($value): string
    {
        if ('*' !== $value) {
            return "'" . str_replace("'", "''", $value) . "'";
        }

        return $value;
    }
}
