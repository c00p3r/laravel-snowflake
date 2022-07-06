<?php

namespace App\Database\Snowflake;

use App\Database\Snowflake\Grammar\QueryGrammar;
use App\Database\Snowflake\Grammar\SchemaGrammar;
use Illuminate\Database\Connection as BaseConnection;
use PDO;

/**
 * Thanks to https://github.com/yoramdelangen/laravel-pdo-odbc
 */
class Connection extends BaseConnection
{
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        $pdo = null;
        $PDO = new PDO("snowflake:account={$config['account']};database={$config['database']};schema={$config['schema']};warehouse={$config['warehouse']};", $config['username'], $config['password']);

        $PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        parent::__construct($PDO, $database, $tablePrefix, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaBuilder()
    {
        if (!$this->schemaGrammar) {
            $this->useDefaultSchemaGrammar();
        }

        return new SchemaBuilder($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultSchemaGrammar()
    {
        $schemaGrammar = $this->getConfig('options.grammar.schema');

        if ($schemaGrammar) {
            return new $schemaGrammar();
        }

        return new SchemaGrammar();
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultQueryGrammar()
    {
        $queryGrammar = $this->getConfig('options.grammar.query');

        if ($queryGrammar) {
            return new $queryGrammar();
        }

        return new QueryGrammar();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultPostProcessor(): Processor
    {
        $processor = $this->getConfig('options.processor');
        if ($processor) {
            return new $processor();
        }

        return new Processor();
    }
}
