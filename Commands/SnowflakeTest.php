<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOStatement;

use function is_int;

class SnowflakeTest extends Command
{
    /**
     * @var string
     */
    protected $signature = 'snowflake:test';

    private string $account;
    private string $user;
    private string $password;
    private string $database;
    private string $schema;
    private string $warehouse;
    private string $tableName;
    private array $data;

    public function handle(): void
    {
        $this->account = env('SNOWFLAKE_ACCOUNT');
        $this->user = env('SNOWFLAKE_USER');
        $this->password = env('SNOWFLAKE_PASSWORD');
        $this->database = env('SNOWFLAKE_DATABASE');
        $this->schema = env('SNOWFLAKE_SCHEMA');
        $this->warehouse = env('SNOWFLAKE_WAREHOUSE');

        $txnID = 'a6b68cc8-6908-4ace-9e9b-f0372b49d0a3';
        $companyID = 1;

        $this->tableName = strtoupper(sprintf(
            'refresh_%d_%s',
            $companyID,
            str_replace('-', '_', $txnID)
        ));

        $row = [
            'account' => 'hey now',
            'AMOUNT, USD' => 1234.567,
            'Supplier' => 'Pepsi Co',
            'gl code' => 'bt_sdk',
            'gl description' => 'Transportation'
        ];

        $this->data = [];
        foreach ($row as $key => $value) {
            $this->data[$this->cleanColumnName($key)] = $value;
        }

        /***************************************************************************************************************
         * PDO
         ***************************************************************************************************************/

        // $this->PDO_Test_Select();
        // $this->PDO_Insert_Raw_Data();
        // $this->PDO_Insert_Prepared_Data();

        /***************************************************************************************************************
         * Laravel query builder (using package)
         ***************************************************************************************************************/

        // DB::connection('snowflake')->enableQueryLog();
        $this->Schema_Create_Table();

        // $this->Builder_Test_Raw_Insert();
        // $this->Builder_Insert_Prepared_Data();
        $this->Builder_Insert();

        // $this->Schema_Drop_Table();
        // $q = DB::connection('snowflake')->getQueryLog();
        $debug = 1;
    }

    private function cleanColumnName(string $name): string
    {
        $name = str_replace(' ', '_', $name);

        $name = preg_replace('/\W/', '', $name);

        return strtoupper($name);
    }

    private function PDO_Test_Select(): void
    {
        $pdo = new PDO("snowflake:account=$this->account", $this->user, $this->password);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->query("SELECT 1234");

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            echo "RESULT: " . $row[0] . "\n";
        }

        $pdo = null;
    }

    private function PDO_Insert_Raw_Data(): void
    {
        $pdo = new PDO("snowflake:account=$this->account", $this->user, $this->password);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // won't work without this
        $pdo->query("use warehouse $this->warehouse");

        $sql = "insert into $this->database.$this->schema.$this->tableName ("
               . implode(', ', array_keys($this->data))
               . ") values ('"
               . implode("', '", array_values($this->data))
               . "')";

        $statement = $pdo->query($sql);

        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
            echo "RESULT: " . $row[0] . "\n";
        }

        $pdo = null;
    }

    /**
     * This is the correct native PDO solution
     *
     * @return void
     */
    private function PDO_Insert_Prepared_Data(): void
    {
        echo __METHOD__ . PHP_EOL;

        $pdo = new PDO("snowflake:account=$this->account", $this->user, $this->password);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // set default fetch mode for PDO
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        $pdo->query("use warehouse $this->warehouse");

        $sql = $this->compileInsert("$this->database.$this->schema.$this->tableName", $this->data);

        $statement = $pdo->prepare($sql);

        $this->bindValues($statement, $this->data);

        $res = $statement->execute();

        echo "Result $res\n";

        $pdo = null;
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @param $tableName
     * @param array $values
     *
     * @return string
     */
    public function compileInsert($tableName, array $values): string
    {
        $columns = $this->columnize(array_keys($values));

        $parameters = $this->parameterize($values);

        return "insert into $tableName ($columns) values ($parameters)";
    }

    /**
     * Convert an array of column names into a delimited string.
     *
     * @param array $columns
     *
     * @return string
     */
    public function columnize(array $columns): string
    {
        return implode(', ', $columns);
    }

    /**
     * Create query parameter place-holders for an array.
     *
     * @param array $values
     *
     * @return string
     */
    public function parameterize(array $values): string
    {
        return implode(', ', array_map(static fn() => '?', $values));
    }

    /**
     * Bind values to their parameters in the given statement.
     *
     * @param PDOStatement $statement
     * @param array $bindings
     *
     * @return void
     */
    public function bindValues(PDOStatement $statement, array $bindings): void
    {
        foreach (array_values($bindings) as $key => $value) {
            $statement->bindValue(
                $key + 1,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }

    private function Schema_Create_Table(): void
    {
        Schema::connection('snowflake')
            ->create($this->tableName, function (Blueprint $table) {
                $table->increments('id');

                foreach ($this->data as $column => $value) {
                    $table->string($column);
                }
            });

        $this->info('Table created');
    }

    private function Schema_Drop_Table(): void
    {
        Schema::connection('snowflake')->drop('REFRESH_1_ABBE2312_0F40_4336_8A5B_7596B5364851');
    }

    private function Builder_Test_Raw_Insert(): void
    {
        $res = DB::connection('snowflake')
            ->insert("insert into $this->database.$this->schema.$this->tableName (ACCOUNT, AMOUNT_USD, SUPPLIER, GL_CODE, GL_DESCRIPTION) values ('foo', 123, 'bar', 666, 'Hey now!')");

        $this->info('Result ' . (int) $res);
    }

    private function Builder_Insert_Manual_Bind_Data(): void
    {
        $questionMarks = implode(', ', array_fill(0, count($this->data), '?'));

        $query = 'insert into ? (' . $questionMarks . ') values (' . $questionMarks . ')';

        $res = DB::connection('snowflake')->insert($query, array_merge([$this->tableName], array_keys($this->data), array_values($this->data)));

        $this->info('Result ' . (int) $res);
    }

    private function Builder_Insert(): void
    {
        echo __METHOD__ . PHP_EOL;

        $res = DB::connection('snowflake')
            ->table($this->tableName)
            ->insert($this->data);

        echo "Result $res\n";
    }
}
