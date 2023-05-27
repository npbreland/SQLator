<?php

namespace NPBreland\SQLator\Tests;

/**
 * Builds the schema of the database so that it can be used for testing.
 *
 * It works in three steps:
 *
 * 1. Makes fake data with Faker and stores in the datbase with RedBeanPHP
 * 2. Freezes the schema
 * 3. Wipes the data (except for seasons and terms, which might be useful
 * for reference)
 *
 * The data is wiped so that the tests can start with a clean slate.
 */

use RedBeanPHP\R as R;

class TestDBHandler
{
    /**
     * Build the data for the test schema, freeze the schema, and wipe the data.
     *
     * @return void
     */
    public static function build(): void
    {
        R::setup('mysql:host=sqlator_db;dbname=sqlator_university', 'test', 'example');
        R::nuke(); // Drop all tables in the existing DB

        $faker = \Faker\Factory::create();

        // Createa student
        $student = R::dispense('student');
        $student->firstName = $faker->firstName;
        $student->lastName = $faker->lastName;
        $student->matriculationDate = $faker->date('Y-m-d');

        // Get date 18 years before matriculationDate
        $matriculationDate = new \DateTime($student->matriculationDate);
        $matriculationDate->sub(new \DateInterval('P18Y'));

        // Birthday must be at least 18 years before matriculationDate
        $student->date_of_birth = $faker->date('Y-m-d', $matriculationDate->getTimestamp());
        $student->email = $faker->email;
        R::store($student);

        // Create a course
        $course = R::dispense('course');
        $course->name = $faker->words(3, true);
        R::store($course);

        // Create an instructor
        $instructor = R::dispense('instructor');
        $instructor->firstName = $faker->firstName;
        $instructor->lastName = $faker->lastName;
        $instructor->email = $faker->email;
        R::store($instructor);

        // Create a building
        $building = R::dispense('building');
        $building->name = $faker->lastName . ' Hall';
        R::store($building);

        // Create seasons
        $seasonsRaw = [
            [
                'name' => 'Fall',
                'start_month' => 9,
                'end_month' => 12
            ],
            [
                'name' => 'Spring',
                'start_month' => 1,
                'end_month' => 5
            ],
            [
                'name' => 'Summer',
                'start_month' => 6,
                'end_month' => 8
            ]
        ];

        foreach ($seasonsRaw as $seasonRaw) {
            $season = R::dispense('season');
            $season->name = $seasonRaw['name'];
            $season->start_month = $seasonRaw['start_month'];
            $season->end_month = $seasonRaw['end_month'];
            R::store($season);
        }

        // Create academic terms
        $years = [2023, 2024, 2025];

        $seasons = R::findAll('season');
        foreach ($years as $year) {
            foreach ($seasons as $season) {
                $academicTerm = R::dispense('academicterm');
                $academicTerm->season = $season;
                $academicTerm->year = $year;
                R::store($academicTerm);
            }
        }

        // Create a residence
        $building = R::findOne('building');
        $residence = R::dispense('residence');
        $residence->number = $faker->numberBetween(100, 999);
        $residence->building = $building;
        R::store($residence);

        // Create a classroom
        $classroom = R::dispense('classroom');
        $classroom->number = $faker->numberBetween(100, 999);
        $classroom->building = $building;
        R::store($classroom);

        // Create an offices
        $office = R::dispense('office');
        $office->number = $faker->numberBetween(100, 999);
        $office->building = $building;
        R::store($office);

        // Create a lab
        $lab = R::dispense('lab');
        $lab->number = $faker->numberBetween(100, 999);
        $lab->building = $building;
        R::store($lab);

        // Assign student to a residence. Only one student per residence.
        $residence = R::findOne('residence');
        $student = R::findOne('student');
        $residence->student = $student;
        R::store($residence);

        // Create an offering
        $instructor = R::findOne('instructor');
        $course = R::findOne('course');
        $classroom = R::findOne('classroom');
        $term = R::findOne('academicterm');

        $offering = R::dispense('offering');
        $offering->instructor = $instructor;
        $offering->course = $course;
        $offering->classroom = $classroom;
        $offering->term = $term;
        R::store($offering);

        // Assign a meeting time to the offering
        $offering = R::findOne('offering');
        $meeting = R::dispense('meeting');
        $meeting->offering = $offering;
        $meeting->day = 2; // Monday
        $meeting->start_time = $faker->time('H:i:s');
        $meeting->end_time = $faker->time('H:i:s');
        R::store($meeting);

        // Put the instructor in an office
        $office = R::findOne('office');
        $office->instructor = $instructor;
        R::store($office);

        // Enroll student in course
        $enrollment = R::dispense('enrollment');
        $enrollment->student = $student;
        $enrollment->offering = $offering;
        R::store($enrollment);

        // Assign student a grade for the course
        $enrollment = R::findOne('enrollment');
        $grade = R::dispense('grade');
        $grade->enrollment = $enrollment;
        $grade->grade = $faker->numberBetween(0, 100);
        R::store($grade);

        // Freeze schemas
        R::freeze(true);
        self::wipe();
    }

    /**
     * Wipe data (except for season and terms, which might be used in tests
     * for reference)
     *
     * @return void
     */
    public static function wipe(): void
    {
        R::wipe('office');
        R::wipe('residence');
        R::wipe('lab');
        R::wipe('grade');
        R::wipe('meeting');

        // Can't wipe due to foreign key constraints
        R::trashAll(R::findAll('classroom'));
        R::trashAll(R::findAll('building'));
        R::trashAll(R::findAll('enrollment'));
        R::trashAll(R::findAll('offering'));
        R::trashAll(R::findAll('student'));
        R::trashAll(R::findAll('instructor'));
        R::trashAll(R::findAll('course'));
    }
}
