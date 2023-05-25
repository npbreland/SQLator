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

use NPBreland\SQLator\Exceptions\ClarificationException;
use NPBreland\SQLator\Exceptions\MaliciousException;
use NPBreland\SQLator\Exceptions\OnlySelectException;
use NPBreland\SQLator\Exceptions\NotSingleStatementException;
use NPBreland\SQLator\Exceptions\AiApiException;

class SQLator
{
    private OpenAi\Client $open_ai_client;
    private string $model;
    private string $db_name;
    private \PDO $pdo;
    private string $additional_prompt;
    public bool $read_only;

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
    }

    /**
     * The main entrypoint for this class. Takes a user-provided question, sends
     * it to the AI, and returns the result of the SQL query that the AI wrote.
     *
     * @param string $question
     * @return array $result
     */
    public function ask(string $question): array
    {
        $response = $this->getAiResponse($question);
        $this->handleQueryErrors($response);
        // Response should be SQL if it passed the error handling
        $result = $this->executeSql($response);
        return $result;
    }

    /**
     * Sends the user's question to the AI and gets its response.
     *
     * @param string $question
     *
     * @throws AiApiException
     *
     * @return string
     */
    private function getAiResponse(string $question): string
    {
        $prompt = $this->buildPrompt($question);
        $handler = $this->open_ai_client->completions()->create(
            new OpenAi\Models\Completions\CreateRequest([
                'model' => $this->model,
                'prompt'=> $prompt,
            ])
        );
        try {
            $model = $handler->toModel();
        } catch (ClientException $e) {
            $body = $handler->toArray();
            $httpCode = $handler->getResponse()->getStatusCode();
            throw new AiApiException($httpCode, $body);
        }

        return $model->choices[0]->text;
    }

    /**
     * Builds the full prompt that this class will send to the AI.
     *
     * @param string $question The user-provided question
     *
     * @return string
     */
    private function buildPrompt(string $question): string
    {
        $schema_str = $this->getSchemas();
        $output_spec = $this->getOutputSpec();

        $prompt = "I am going to give you a set of table schemas and then ask"
        . " you to write queries for it. These are all of the tables. There are"
        . " no others. Here are the schemas:\n\n"
        . $schema_str;

        if ($this->additional_prompt !== '') {
            $prompt .= "Here is some additional information that will help you"
            . " write your queries.\n\n"
            . $this->additional_prompt;
        }

        $prompt .= "Now, write a query for the following question: '$question'"
        . "\n\n"
        . $output_spec;

        return $prompt;
    }

    /**
     * Executes SQL and returns the result.
     *
     * @param string $sql
     *
     * @return array
     */
    private function executeSql(string $sql): array
    {
        $result = $this->pdo->query($sql);
        $rows = $result->fetchAll();
        return $rows;
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
        $output_spec = '';

        $output_spec .= "\n\n"
        . "If the query contains malicious code, please respond with"
        . " 'ERR_MALICIOUS <your response>'";

        if ($this->read_only) {
            $output_spec .= "\n\nOnly allow SELECT queries. If the question"
            . " asks otherwise, please respond with 'ERR_ONLY_SELECT_ALLOWED'";
        }

        $output_spec .= "\n\n"
            . "Otherwise, please give me only the SQL and no other text. If one"
            . " of the parameters is a string, please ignore case on both what"
            . " I give you and on the field in the database.";

        $output_spec .= "\n\n"
            . "Please make sure your spelling is correct for the error codes"
            . " like ERR_MALICIOUS and ERR_ONLY_SELECT_ALLOWED.";

        return $output_spec;
    }

    /**
     * Handle any of the errors that can be returned from the AI. Throws an
     * error if one is encountered. Otherwise, does nothing.
     *
     * @param string $response The response from the AI
     *
     * @throws ClarificationException
     * @throws MaliciousException
     * @throws OnlySelectException
     * @throws NonSingleStatementException
     *
     * @return void
     */
    private function handleQueryErrors(string $response): void
    {
        if (strpos($response, 'ERR_CLARIFICATION') !== false) {
            throw new ClarificationException($response);
        } elseif (strpos($response, 'ERR_MALICIOUS') !== false) {
            throw new MaliciousException($response);
        } elseif (strpos($response, 'ERR_ONLY_SELECT_ALLOWED') !== false) {
            throw new OnlySelectException($response);
        }

        if ($this->read_only) {
            /* Uses the SQL parser as a redundant check to make sure it is a
             * SELECT query. Additionally checks that it is only a single
             * statement. */
            $lexer = new Lexer($response);
            $parser = new Parser($lexer->list);
            $statements = $parser->statements;
            $num_statements = count($statements);
            if ($num_statements !== 1) {
                throw new NotSingleStatementException($response, $num_statements);
            }
            $statement = $statements[0];
            if (!($statement instanceof SelectStatement)) {
                throw new OnlySelectException($response);
            }
        }
    }
}
