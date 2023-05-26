<?php

declare(strict_types=1);

namespace NPBreland\SQLator;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Tectalic\OpenAi as OpenAi;
use Tectalic\OpenAi\ClientException;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;

use Psr\Http\Client\ClientInterface;

use NPBreland\SQLator\Exceptions\BadCommandException;
use NPBreland\SQLator\Exceptions\MaliciousException;
use NPBreland\SQLator\Exceptions\OnlySelectException;
use NPBreland\SQLator\Exceptions\NotSingleStatementException;
use NPBreland\SQLator\Exceptions\AiApiException;
use NPBreland\SQLator\Exceptions\DbException;

class SQLator
{
    private OpenAi\Client $open_ai_client;
    private string $model;
    private string $db_name;
    private string $additional_prompt;
    public \PDO $pdo;
    public bool $read_only;
    public bool $ignore_case_on_select;

    public function __construct(
        ClientInterface $client,
        string $open_ai_key,
        string $model,
        string $db_host,
        string $db_name,
        string $db_user = '',
        string $db_pass = '',
        string $db_port = '',
        string $unix_socket = '',
        string $charset = 'utf8mb4',
        string $additional_prompt = '',
        bool $ignore_case_on_select = true,
        bool $read_only = true
    ) {
        $this->open_ai_client = OpenAi\Manager::build(
            $client,
            new OpenAi\Authentication($open_ai_key)
        );
        $this->model = $model;
        $this->db_name = $db_name;

        $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;"
            ."unix_socket=$unix_socket;charset=$charset";

        $this->pdo = new \PDO($dsn, $db_user, $db_pass, [
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
        $this->additional_prompt = $additional_prompt;
        $this->read_only = $read_only;
        $this->ignore_case_on_select = $ignore_case_on_select;
    }

    /**
     * The main entrypoint for this class. Takes a user-provided command, sends
     * it to the AI, and returns the result of the SQL query that the AI wrote.
     *
     * @param string $command
     * @return array|int $result
     */
    public function command(string $command): array|int
    {
        $response = $this->sendCommandToAi($command);
        $this->handleQueryErrors($command, $response);
        // Response should be SQL if it passed the error handling
        $result = $this->executeSql($response);
        return $result;
    }

    /**
     * Sends the user's command to the AI and gets its response.
     *
     * @param string $command
     *
     * @throws AiApiException
     *
     * @return string
     */
    private function sendCommandToAi(string $command): string
    {
        $prompt = $this->buildPrompt($command);
        $handler = $this->open_ai_client->chatCompletions()->create(
            new OpenAi\Models\ChatCompletions\CreateRequest([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a translator that translates human language commands into SQL queries.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ],
                ],
                'temperature' => 0,
            ])
        );
        try {
            $model = $handler->toModel();
        } catch (ClientException $e) {
            $body = $handler->toArray();
            $httpCode = $handler->getResponse()->getStatusCode();
            throw new AiApiException($httpCode, $body);
        }

        return $model->choices[0]->message->content;
    }

    /**
     * Builds the prompt that this class will send to the AI.
     *
     * @param string $command
     * @return string
     */
    private function buildPrompt($command): string
    {
        $schema_str = $this->getSchemas();
        $output_spec = $this->getOutputSpec();

        $prompt = "Below are a set of MySQL table schemas. You will respond to user commands or queries with SQL querying that database."
        . $schema_str;

        if ($this->additional_prompt !== '') {
            $prompt .= "Here is some additional information that will help you"
            . " write your queries.\n\n"
            . $this->additional_prompt;
        }
        $prompt .= "\nWrite a query for the following command/query: '$command'";

        $prompt .= ""
        . "\n\n"
        . $output_spec;

        return $prompt;
    }

    /**
     * Executes SQL and returns the result. Returns an array if the query is
     * SELECT, and the number of affected otherwise.
     *
     * @param string $sql
     * @throws DbException
     * @return array|int
     */
    private function executeSql(string $sql): array|int
    {
        try {
            $result = $this->pdo->query($sql);
            $rows = $result->fetchAll();
            if (count($rows) > 0) {
                return $rows;
            }
            return $result->rowCount();
        } catch (\PDOException $e) {
            throw new DbException($sql, 0, $e);
        }
    }

    /**
     * Returns a string containing the schemas of all of the tables in the db.
     *
     * @return string
     */
    private function getSchemas(): string
    {
        $tables_result = $this->pdo->query('SHOW TABLES');
        $schema_str = '';

        if ($tables_result->rowCount() > 0) {
            // Loop through each table
            while ($table = $tables_result->fetch()) {
                $table_name = $table["Tables_in_$this->db_name"];

                // Get the "CREATE TABLE" statement for the current table
                $sql = "SHOW CREATE TABLE ".$table_name;
                $result = $this->pdo->query($sql);

                if ($result->rowCount() > 0) {
                    $row = $result->fetch();
                    $schema_str .= $row['Create Table'] . "\n";
                }
            }
        }

        return $schema_str;
    }

    /**
     * Returns the text that tells the AI how to output its response.
     *
     * @return string
     */
    private function getOutputSpec(): string
    {
        $output_spec = "Respond with only the valid SQL.";

        if ($this->ignore_case_on_select) {
            $output_spec .= " For SELECT queries, ignore case on both the input"
                . " and on what it is matching on in the database.";
        }

        return $output_spec;
    }

    /**
     * Handle any of the errors that can be returned from the AI. Throws an
     * error if one is encountered. Otherwise, does nothing.
     *
     * @param string $command The original user command
     * @param string $response The response from the AI
     *
     * @throws BadCommandException
     * @throws OnlySelectException
     * @throws NonSingleStatementException
     *
     * @return void
     */
    private function handleQueryErrors(string $command, string $response): void
    {
        if ($this->read_only) {
            /* Uses the SQL parser as a redundant check to make sure it is a
             * SELECT query. Additionally checks that it is only a single
             * statement. */
            $lexer = new Lexer($response);
            $parser = new Parser($lexer->list);
            $statements = $parser->statements;
            $num_statements = count($statements);
            if ($num_statements !== 1) {
                throw new NotSingleStatementException($command, $response, $num_statements);
            }
            $statement = $statements[0];
            if (!($statement instanceof SelectStatement)) {
                throw new OnlySelectException($command, $response);
            }
        }
    }
}
