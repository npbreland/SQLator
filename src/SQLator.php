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
use NPBreland\SQLator\Exceptions\AI_APIException;
use NPBreland\SQLator\Exceptions\DBException;

class SQLator
{
    private OpenAi\Client $open_AI_client;
    private string $model;
    private string $DB_name;
    private string $additional_prompt;
    public \PDO $pdo;
    public bool $read_only;
    public bool $ignore_case_on_select;

    public function __construct(
        ClientInterface $client,
        string $open_AI_key,
        string $model,
        string $DB_host,
        string $DB_name,
        string $DB_user = '',
        string $DB_pass = '',
        string $DB_port = '',
        string $unix_socket = '',
        string $charset = 'utf8mb4',
        string $additional_prompt = '',
        bool $ignore_case_on_select = true,
        bool $read_only = true
    ) {
        $this->open_AI_client = OpenAi\Manager::build(
            $client,
            new OpenAi\Authentication($open_AI_key)
        );
        $this->model = $model;
        $this->DB_name = $DB_name;

        $dsn = "mysql:host=$DB_host;port=$DB_port;dbname=$DB_name;"
            ."unix_socket=$unix_socket;charset=$charset";

        $this->pdo = new \PDO($dsn, $DB_user, $DB_pass);
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
    public function commandToResult(string $command): array|int
    {
        $response = $this->getAIResponse($command);
        $this->handleQueryErrors($command, $response);
        // Response should be SQL if it passed the error handling
        $result = $this->executeSQL($response);
        return $result;
    }

    /**
     * Second entrypoint for this class. Takes a user-provided command, sends
     * back the SQL without executing it.
     *
     * @param string $command
     * @return string $SQL
     */
    public function commandToSQL(string $command): string
    {
        $response = $this->getAIResponse($command);
        $this->handleQueryErrors($command, $response);
        return $response;
    }

    /**
     * Sends the user's command to the AI and gets its response.
     *
     * @param string $command
     *
     * @throws AI_APIException
     *
     * @return string
     */
    private function getAIResponse(string $command): string
    {
        $prompt = $this->buildPrompt($command);
        $handler = $this->open_AI_client->chatCompletions()->create(
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
                'temperature' => 0, // 0 is highly deterministic
            ])
        );
        try {
            $model = $handler->toModel();
        } catch (ClientException $e) {
            $body = $handler->toArray();
            $httpCode = $handler->getResponse()->getStatusCode();
            throw new AI_APIException($httpCode, $body);
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
     * @param string $SQL
     * @throws DBException
     * @return array|int
     */
    private function executeSQL(string $SQL): array|int
    {
        try {
            $result = $this->pdo->query($SQL);
            $rows = $result->fetchAll();
            if (count($rows) > 0) {
                return $rows;
            }
            return $result->rowCount();
        } catch (\PDOException $e) {
            throw new DBException($SQL, 0, $e);
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
                $table_name = $table["Tables_in_$this->DB_name"];

                // Get the "CREATE TABLE" statement for the current table
                $SQL = "SHOW CREATE TABLE ".$table_name;
                $result = $this->pdo->query($SQL);

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
