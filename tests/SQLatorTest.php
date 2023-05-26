<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as Client;
//use Http\Mock\Client;
use Dotenv\Dotenv;

use Tectalic\OpenAi as OpenAi;

use NPBreland\SQLator\SQLator;
use NPBreland\SQLator\Exceptions\ClarificationException;
use NPBreland\SQLator\Exceptions\BadCommandException;
use NPBreland\SQLator\Exceptions\MaliciousException;
use NPBreland\SQLator\Exceptions\OnlySelectException;
use NPBreland\SQLator\Exceptions\DbException;
use NPBreland\SQLator\Exceptions\NotSingleStatementException;
use NPBreland\SQLator\Exceptions\AiApiException;

class SQLatorTest extends TestCase
{
    protected SQLator $sqlator;

    protected function setUp(): void
    {
        $client = new Client();

        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        $this->sqlator = new SQLator(
            client: $client,
            open_ai_key: $_ENV['OPENAI_API_KEY'],
            model: $_ENV['OPENAI_MODEL'],
            db_host: 'sqlator_db',
            db_name: 'sqlator_university',
            db_user: 'test',
            db_pass: 'example',
        );
    }

    /*
    public function testReadComplexity1()
    {
        $aiResult = $this->sqlator->command('Give me all students.');
        $stmt = $this->sqlator->pdo->query('SELECT * FROM student');
        $dbResult = $stmt->fetchAll();
        $this->assertEquals($dbResult, $aiResult);
    }

    public function testReadComplexity2()
    {
        $aiResult = $this->sqlator->command('Give me all students with a birthday in May.');
        $stmt = $this->sqlator->pdo->query('SELECT * FROM student WHERE MONTH(date_of_birth) = 5');
        $dbResult = $stmt->fetchAll();
        $this->assertEquals(count($dbResult), count($aiResult));
    }
     */

    public function testReadComplexity3()
    {
        $command = 'Give me all students who reside in Gulgowski Hall.';
        $sql = <<<SQL
            SELECT *
              FROM student
                   JOIN residence
                   ON student.id = residence.student_id
                    
                   JOIN building
                   ON residence.building_id = building.id
             WHERE building.name = 'Gulgowski Hall'
SQL;
        $aiResult = $this->sqlator->command($command);
        $stmt = $this->sqlator->pdo->query($sql);
        $dbResult = $stmt->fetchAll();
        $this->assertEquals(count($dbResult), count($aiResult));
    }

    /*
    public function testSuccessfulSimpleWrite()
    {
        $this->sqlator->read_only = false;

        $first_name = 'Walter';
        $last_name = 'White';

        $deleteQuery = "DELETE FROM student WHERE first_name = '$first_name' AND last_name = '$last_name'";

        // Clear any existing students with the same name
        $this->sqlator->pdo->query($deleteQuery);

        $this->sqlator->command("Insert a new student named $first_name $last_name.");
        $stmt = $this->sqlator->pdo->query("SELECT * FROM student WHERE first_name = '$first_name' AND last_name = '$last_name'");
        $dbResult = count($stmt->fetchAll());
        $this->assertEquals(1, $dbResult);

        // Clean up
        $this->sqlator->pdo->query($deleteQuery);
    }

    public function testAiNoResults()
    {
        $this->expectException(NotSingleStatementException::class);
        $this->sqlator->command('Get me students with');
    }

    public function testAiOnlyAllowsSelect()
    {
        $this->expectException(OnlySelectException::class);
        $this->sqlator->command('Update all students to have a first name of Bob.');
    }
     */

    public function tearDown(): void
    {
        // Needed to reset the client for each test
        $reflection = new ReflectionClass(OpenAi\Manager::class);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue(null);

        // Wait to avoid rate limiting
        sleep(1);
    }
}
