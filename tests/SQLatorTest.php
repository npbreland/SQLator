<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as Client;
use Dotenv\Dotenv;

use Tectalic\OpenAi as OpenAi;

use RedBeanPHP\R as R;

use NPBreland\SQLator\SQLator;
use NPBreland\SQLator\Exceptions\OnlySelectException;
use NPBreland\SQLator\Exceptions\NotSingleStatementException;

class SQLatorTest extends TestCase
{
    protected SQLator $SQLator;
    protected \Faker\Generator $faker;

    public static function setUpBeforeClass(): void
    {
        TestDataHandler::build();
    }

    protected function setUp(): void
    {
        $client = new Client();

        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        $this->SQLator = new SQLator(
            client: $client,
            open_AI_key: $_ENV['OPENAI_API_KEY'],
            model: $_ENV['OPENAI_MODEL'],
            DB_host: 'sqlator_db',
            DB_name: 'sqlator_university',
            DB_user: 'test',
            DB_pass: 'example',
        );

        $this->faker = \Faker\Factory::create();
    }

    public function testReadLevel1()
    {
        $students = R::dispense('student', 10);
        R::storeAll($students);

        $AI_result = $this->SQLator->commandToResult('Give me all students.');
        $this->assertEquals(10, count($AI_result));
    }

    public function testReadLevel2()
    {
        $students = R::dispense('student', 10);
        foreach ($students as $student) {
            $student->date_of_birth = $this->faker->dateTime()->format('Y-5-d');
            R::store($student);
        }
        $AI_result = $this->SQLator->commandToResult('Give me all students with a birthday in May.');
        $this->assertEquals(10, count($AI_result));
    }

    public function testReadLevel3()
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
        $AI_result = $this->SQLator->commandToResult($command);
        $this->assertEquals(5, count($AI_result));
    }

    public function testReadLevel4()
    {
        $instructor = R::dispense('instructor');

        $instructor->first_name = 'Walter';
        $instructor->last_name = 'White';
        R::store($instructor);

        $course = R::dispense('course');
        $course->name = 'Chemistry';
        R::store($course);

        $classroom = R::dispense('classroom');
        $classroom->number = 101;
        R::store($classroom);

        $term = R::findOne('academicterm');

        $offering = R::dispense('offering');
        $offering->instructor = $instructor;
        $offering->course = $course;
        $offering->classroom = $classroom;
        $offering->term = $term;
        R::store($offering);

        // Assign a meeting time to the offering
        $meeting = R::dispense('meeting');
        $meeting->offering = $offering;
        $meeting->day = 2; // Monday
        $meeting->start_time = $this->faker->time('H:i:s');
        $meeting->end_time = $this->faker->time('H:i:s');
        R::store($meeting);

        $students = R::dispense('student', 5);
        for ($i = 0; $i < 3; $i++) {
            $enrollment = R::dispense('enrollment');
            $enrollment->student = $students[$i];
            $enrollment->offering = $offering;
            R::store($enrollment);
        }

        $command = 'Give me all students enrolled in a class taught by Walter White.';
        $AI_result = $this->SQLator->commandToResult($command);
        $this->assertEquals(3, count($AI_result));
    }

    public function testSpecifyColumns()
    {
        $students = R::dispense('student', 3);
        $students[0]->first_name = 'Walter';
        $students[0]->last_name = 'White';
        $students[0]->date_of_birth = '1959-09-07';

        $students[1]->first_name = 'Jesse';
        $students[1]->last_name = 'Pinkman';
        $students[1]->date_of_birth = '1984-09-24';

        $students[2]->first_name = 'Skyler';
        $students[2]->last_name = 'White';
        $students[2]->date_of_birth = '1970-08-11';
        R::storeAll($students);

        $command = 'Give me the last names and birthdays of all students.';

        $AI_result = $this->SQLator->commandToResult($command);
        $this->assertEquals([
            [
                'last_name' => 'White',
                'date_of_birth' => '1959-09-07',
                0 => 'White',
                1 => '1959-09-07'
            ],
            [
                'last_name' => 'Pinkman',
                'date_of_birth' => '1984-09-24',
                0 => 'Pinkman',
                1 => '1984-09-24'
            ],
            [
                'last_name' => 'White',
                'date_of_birth' => '1970-08-11',
                0 => 'White',
                1 => '1970-08-11'
            ]
        ], $AI_result);
    }

    public function testCount()
    {
        $students = R::dispense('student', 3);
        $students[0]->first_name = 'Walter';
        $students[0]->last_name = 'White';
        $students[0]->date_of_birth = '1959-09-07';

        $students[1]->first_name = 'Jesse';
        $students[1]->last_name = 'Pinkman';
        $students[1]->date_of_birth = '1984-09-24';

        $students[2]->first_name = 'Skyler';
        $students[2]->last_name = 'White';
        $students[2]->date_of_birth = '1970-08-11';
        R::storeAll($students);

        $command = 'How many students are there?';

        $AI_result = $this->SQLator->commandToResult($command);
        $AI_count = $AI_result[0][0];
        $this->assertEquals(3, $AI_count);
    }


    public function testWriteLevel1()
    {
        $this->SQLator->read_only = false;

        $first_name = 'Walter';
        $last_name = 'White';

        $this->SQLator->commandToResult("Insert a new student named $first_name $last_name.");
        $DB_count = R::count('student', 'first_name = ? AND last_name = ?', [
            $first_name,
            $last_name
        ]);

        $this->assertEquals(1, $DB_count);
    }

    public function testAlterTable()
    {
        $this->SQLator->read_only = false;
        $this->SQLator->commandToResult("Create a new student field for favorite color.");
        $result = $this->SQLator->pdo->query('SHOW COLUMNS FROM student LIKE "favorite_color"');
        $this->assertEquals(1, $result->rowCount());
    }

    public function testNoResults()
    {
        $this->expectException(NotSingleStatementException::class);
        $this->SQLator->commandToResult('Get me students with');
    }

    public function testOnlyAllowsSelect()
    {
        $this->expectException(OnlySelectException::class);
        $this->SQLator->commandToResult('Update all students to have a first name of Bob.');
    }

    public function tearDown(): void
    {
        // Needed to reset the client for each test
        $reflection = new \ReflectionClass(OpenAi\Manager::class);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue(null);

        // Wait to avoid rate limiting
        sleep(3);

        // Clear the database
        TestDataHandler::wipe();
    }

    /**
     * Test fails with GPT-3.5. Try GPT-4?
     *
     */
    /*
    public function testAverage()
    {
        $students = R::dispense('student', 40);
        R::storeAll($students);

        $instructor = R::dispense('instructor');
        R::store($instructor);

        $course = R::dispense('course');
        R::store($course);

        $classroom = R::dispense('classroom');
        R::store($classroom);

        $offerings = R::dispense('offering', 4);
        R::storeAll($offerings);

        $enrollments = R::dispense('enrollment', 40);

        // 3 students in the first class
        for ($i = 0; $i < 3; $i++) {
            $enrollments[$i]->student = $students[$i];
            $enrollments[$i]->offering = $offerings[0];
        }

        // 2 students in the second class
        for ($i = 3; $i < 5; $i++) {
            $enrollments[$i]->student = $students[$i];
            $enrollments[$i]->offering = $offerings[1];
        }

        // 5 students in the third class
        for ($i = 5; $i < 10; $i++) {
            $enrollments[$i]->student = $students[$i];
            $enrollments[$i]->offering = $offerings[2];
        }

        // 30 students in the fourth class
        for ($i = 10; $i < 40; $i++) {
            $enrollments[$i]->student = $students[$i];
            $enrollments[$i]->offering = $offerings[3];
        }

        R::storeAll($enrollments);

        $command = 'What is the average number of students per class?';

        $AI_result = $this->SQLator->commandToResult($command);

        $AI_avg = $AI_result[0][0];
        $this->assertEquals(10, $AI_avg);
    }
     */
}
