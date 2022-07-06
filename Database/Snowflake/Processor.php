<?php

namespace App\Database\Snowflake;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

class Processor extends BaseProcessor
{
    /**
     * @param $tableName
     *
     * @return string
     */
    public static function wrapTable($tableName): string
    {
        if ($tableName instanceof Blueprint) {
            $tableName = $tableName->getTable();
        }

        if (! env('SNOWFLAKE_COLUMNS_CASE_SENSITIVE', false)) {
            $tableName = Str::upper($tableName);
        }

        return $tableName;
    }

    /**
     * Process the results of a column listing query.
     *
     * @param array $results
     *
     * @return array
     */
    public function processColumnListing($results): array
    {
        return array_map(function ($result) {
            return ((object) $result)->column_name;
        }, $results);
    }

    /**
     * Process an "insert get ID" query.
     *
     * @param Builder $query
     * @param string $sql
     * @param array $values
     * @param string|null $sequence
     *
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null): int
    {
        $connection = $query->getConnection();

        $connection->insert($sql, $values);

        $wrappedTable = $query->getGrammar()->wrapTable($query->from);

        $result = $connection->selectOne('select * from ' . $wrappedTable . ' at(statement=>last_query_id())');
        // hacky.... TODO we should fix this proper way...
        $id = array_values((array) $result)[0];

        return is_numeric($id) ? (int) $id : $id;
    }
}
