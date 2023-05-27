<?php

declare(strict_types=1);

namespace NPBreland\SQLator\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as Client;
use Dotenv\Dotenv;

use Tectalic\OpenAi as OpenAi;

use RedBeanPHP\R as R;

use NPBreland\SQLator\SQLator;
use NPBreland\SQLator\Exceptions\ClarificationException;
use NPBreland\SQLator\Exceptions\BadCommandException;
use NPBreland\SQLator\Exceptions\MaliciousException;
use NPBreland\SQLator\Exceptions\OnlySelectException;
use NPBreland\SQLator\Exceptions\DbException;
use NPBreland\SQLator\Exceptions\NotSingleStatementException;
use NPBreland\SQLator\Exceptions\AiApiException;

require_once 'TestDBHandler.php';

class SQLatorTest extends TestCase
{
    protected SQLator $sqlator;
    protected \Faker\Generator $faker;

    public static function setUpBeforeClass(): void
    {
        TestDBHandler::build();
    }

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

        $this->faker = \Faker\Factory::create();
    }

    public function testReadLevel1()
    {
        $students = R::dispense('student', 10);
        R::storeAll($students);

        $aiResult = $this->sqlator->command('Give me all students.');
        $this->assertEquals(10, count($aiResult));
    }

    public function testReadLevel2()
    {
        $students = R::dispense('student', 10);
        foreach ($students as $student) {
            $student->date_of_birth = $this->faker->dateTime()->format('Y-5-d');
            R::store($student);
        }
        $aiResult = $this->sqlator->command('Give me all students with a birthday in May.');
        $this->assertEquals(10, count($aiResult));
    }

    public function testReadComplexity3()
    {
        $students = R::dispense('student', 5);
        R::storeAll($students);

        $building = R::dispense('building');
        $building->name = 'Gulgowski Hall';
        $id = R::store($building);
        $building = R::load('building', $id);

        $residences = R::dispense('residence', 5);
        foreach ($residences as $residence) {
            $residence->building = $building;
            $residence->number = $this->faker->numberBetween(100, 999);
            $student = R::findOne('student');
            $residence->student = $student;
            R::store($residence);
        }

        $command = 'Give me all students who reside in Gulgowski Hall.';
        /*
        $sql = <<<SQL
            SELECT *
              FROM student
                   JOIN residence
                   ON student.id = residence.student_id

                   JOIN building
                   ON residence.building_id = building.id
             WHERE building.name = 'Gulgowski Hall'
SQL;
         */
        $aiResult = $this->sqlator->command($command);
        $this->assertEquals(5, count($aiResult));
    }

    public function testSuccessfulSimpleWrite()
    {
        $this->sqlator->read_only = false;

        $first_name = 'Walter';
        $last_name = 'White';

        $this->sqlator->command("Insert a new student named $first_name $last_name.");
        $dbCount = R::count('student', 'first_name = ? AND last_name = ?', [
            $first_name,
            $last_name
        ]);

        $this->assertEquals(1, $dbCount);
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

    public function tearDown(): void
    {
        // Needed to reset the client for each test
        $reflection = new \ReflectionClass(OpenAi\Manager::class);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue(null);

        // Wait to avoid rate limiting
        sleep(1);

        // Clear the database
        TestDBHandler::wipe();
    }
}
