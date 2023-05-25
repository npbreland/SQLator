<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use RedBeanPHP\R as R;

require 'vendor/autoload.php';

R::setup('mysql:host=sqlator_db;dbname=sqlator_university', 'test', 'example');
R::nuke();

$faker = Faker\Factory::create();

// Create 500 students
for ($i = 0; $i < 500; $i++) {
    $student = R::dispense('student');
    $student->firstName = $faker->firstName;
    $student->lastName = $faker->lastName;
    $student->matriculationDate = $faker->date('Y-m-d');

    // Get date 18 years before matriculationDate
    $matriculationDate = new DateTime($student->matriculationDate);
    $matriculationDate->sub(new DateInterval('P18Y'));

    // Birthday must be at least 18 years before matriculationDate
    $student->date_of_birth = $faker->date('Y-m-d', $matriculationDate->getTimestamp());
    $student->email = $faker->email;
    R::store($student);
}

// Create 50 courses
for ($i = 0; $i < 50; $i++) {
    $course = R::dispense('course');
    $course->name = $faker->words(3, true);
    R::store($course);
}

// Create 50 instructors
for ($i = 0; $i < 50; $i++) {
    $instructor = R::dispense('instructor');
    $instructor->firstName = $faker->firstName;
    $instructor->lastName = $faker->lastName;
    $instructor->email = $faker->email;
    R::store($instructor);
}

// Create 10 buildings
for ($i = 0; $i < 10; $i++) {
    $building = R::dispense('building');
    $building->name = $faker->lastName . ' Hall';
    R::store($building);
}

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

// Create 100 residences
// TODO: put residences in resident halls
$buildings = R::findAll('building');
for ($i = 0; $i < 100; $i++) {
    $residence = R::dispense('residence');
    $residence->number = $faker->numberBetween(100, 999);
    $residence->building = $faker->randomElement($buildings);
    R::store($residence);
}

// Create 100 classrooms
for ($i = 0; $i < 100; $i++) {
    $classroom = R::dispense('classroom');
    $classroom->number = $faker->numberBetween(100, 999);
    $classroom->building = $faker->randomElement($buildings);
    R::store($classroom);
}

// Create 100 offices
for ($i = 0; $i < 100; $i++) {
    $office = R::dispense('office');
    $office->number = $faker->numberBetween(100, 999);
    $office->building = $faker->randomElement($buildings);
    R::store($office);
}

// Create 100 labs
for ($i = 0; $i < 100; $i++) {
    $lab = R::dispense('lab');
    $lab->number = $faker->numberBetween(100, 999);
    $lab->building = $faker->randomElement($buildings);
    R::store($lab);
}

// Assign student to a residence. Only one student per residence.
$residences = R::findAll('residence');
$students = R::findAll('student');
foreach ($residences as $residence) {
    $student = $faker->randomElement($students);
    $residence->student = $student;
    R::store($residence);
}

// Assign each instructor to 1-3 courses
$instructors = R::findAll('instructor');
$courses = R::findAll('course');
$classrooms = R::findAll('classroom');
$terms = R::findAll('academicterm');
foreach ($instructors as $instructor) {
    $randomCourses = $faker->randomElements($courses, $faker->numberBetween(1, 3));
    foreach ($randomCourses as $course) {
        $offering = R::dispense('offering');
        $offering->instructor = $instructor;
        $offering->course = $course;
        $offering->classroom = $faker->randomElement($classrooms);
        $offering->term = $faker->randomElement($terms);
        R::store($offering);
    }
}

$days_of_week = [ 1, 2, 3, 4, 5, 6, 7 ];
$offerings = R::findAll('offering');
foreach ($offerings as $offering) {
    $randomDays = $faker->randomElements($days_of_week, $faker->numberBetween(1, 5));
    foreach ($randomDays as $day) {
        $meeting = R::dispense('meeting');
        $meeting->offering = $offering;
        $meeting->day = $day;
        $meeting->start_time = $faker->time('H:i:s');
        $meeting->end_time = $faker->time('H:i:s');
        R::store($meeting);
    }
}

// Put an instructor in each office
$offices = R::findAll('office');
foreach ($offices as $office) {
    $instructor = $faker->randomElement($instructors);
    $office->instructor = $instructor;
    R::store($office);
}

// Enroll each student in 1-5 courses
$students = R::findAll('student');
$offerings = R::findAll('offering');
foreach ($students as $student) {
    $randomOfferings = $faker->randomElements($offerings, $faker->numberBetween(1, 5));
    foreach ($randomOfferings as $offering) {
        $enrollment = R::dispense('enrollment');
        $enrollment->student = $student;
        $enrollment->offering = $offering;
        R::store($enrollment);
    }
}

// Assign students a grade for each course they are enrolled in
$enrollments = R::findAll('enrollment');
foreach ($enrollments as $enrollment) {
    $grade = R::dispense('grade');
    $grade->enrollment = $enrollment;
    $grade->grade = $faker->numberBetween(0, 100);
    R::store($grade);
}

R::freeze(true);
