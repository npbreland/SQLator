<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as Client;
//use Http\Mock\Client;
use Dotenv\Dotenv;

use Tectalic\OpenAi as OpenAi;

use NPBreland\SQLator\SQLator;
use NPBreland\SQLator\Exceptions\ClarificationException;
use NPBreland\SQLator\Exceptions\MaliciousException;
use NPBreland\SQLator\Exceptions\OnlySelectException;
use NPBreland\SQLator\Exceptions\NotSingleStatementException;
use NPBreland\SQLator\Exceptions\AiApiException;

class SQLatorTest extends TestCase
{
    protected $sqlator;

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

    public function testSuccessfulAsk()
    {
        $result = $this->sqlator->ask('Give me all students.');
        $this->assertIsArray($result);
    }

    public function testAiBlocksMaliciousRequest()
    {
        $this->sqlator->read_only = false;
        $this->expectException(MaliciousException::class);
        $this->sqlator->ask('SELECT * FROM users; DROP TABLE users;');
    }

    public function testAiBadQuery()
    {
        $this->expectException(PDOException::class);
        $this->sqlator->ask('Get me students with');
    }

    public function testAiOnlyAllowsSelect()
    {
        $this->expectException(OnlySelectException::class);
        $this->sqlator->ask('Update all students to have a first name of Bob.');
    }

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
