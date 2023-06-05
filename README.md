# SQLator
An AI tool for translating human language into SQL queries. Level up your business users into power users!

## Install

`composer require npbreland/sqlator`

## Overview

SQLator takes a user command or question and turns it into an SQL query. Take a simple example:

```
Question: Give me the first names of all students.
SQL: SELECT first_name FROM students
```

This can empower business users who don't know SQL by allowing them to directly 
query for the information they need. Alternatively, SQLator can be used by programmers
to help them quickly determine the queries they need to write in their code, or to give
them a query to start with that they can then modify, enhancing their productivity.

## How it Works

SQLator works by sending the query to a language models, including with it
the table schema for your database. Currently, it only works with OpenAI's chat
models and with MySQL. I want to support other models and databases in the future,
however. You will need an API key from OpenAI. If you don't already have one,
you can sign up for API access on their [website](https://openai.com/).

## How to Use

First, here is an example of instantiating the SQLator class.

```
$SQLator = new SQLator(
    client: $client,
    open_AI_key: 'your-api-key',
    model: 'gpt-3.5-turbo', // Try GPT-4 if you can get access!
    DB_host: 'localhost',
    DB_name: 'my_db'
    DB_user: 'walter_white',
    DB_pass: 'example',
);
```

_Please look at the [constructor](https://github.com/npbreland/SQLator/blob/main/src/SQLator.php#L34) 
for the full list of options, including enabling write access._

`$client` must be a PSR-18-compliant HTTP client, such as [Guzzle](https://github.com/guzzle/guzzle) or [Symfony's
HTTP client](https://github.com/symfony/http-client). [Here is a list on packagist.](https://packagist.org/providers/psr/http-client-implementation)
Note: if you are using Laravel, its HTTP client is a wrapper around Guzzle, so it will work.

There are two ways to use SQLator.

1. **With execution.** SQLator will produce SQL from the given query and run it on your database.
**Note: The default is to allow only read access (i.e. SELECT queries), and I 
highly recommend to keep this setting for production use. Please only allow 
write access if you know what you are doing and your end users know what they 
are doing.**

`$result = $this->SQLator->commandToResult('Give me all students.');`

This should return an array of students.

2. **Without execution.** SQLator will only return the SQL it produces. This
would mostly be useful to developers and database administrators.

`$result = $this->SQLator->commandToSQL('Give me all students.');`

This should return SQL like:

`SELECT * FROM students`

## Limitations and suggested fixes/workarounds

### Schemas
The AI is not magic, so its resolution skills will be hampered if your schemas
are not set up well. For best results, I recommend setting up foreign keys where
applicable and adding comments to your columns, especially where their meaning
is not straightforward. These two steps should aid the AI, just like they would
aid a human.

### Token maxes
Language models have a maximum number of tokens that can be supplied. GPT-3.5-turbo
accepts up to 4,096 tokens. This may mean that for large schema aggregates you
run out of tokens in your prompt. GPT-4 can handle much more, so definitely
try it out if you have access. I am planning to improve the tool to reduce this
problem, perhaps by shortening the schema readouts to only the information that
is absolutely needed by the language model, and/or allowing the user to specify
the tables they want to include in SQLator's scope.

### Complex queries
SQLator is surprisingly powerful even with just GPT-3.5-turbo. However, I have
noticed that it becomes less reliable the more complex queries become. You may
be able to improve its resolution abilities by passing in additional information
about your data to the `$additional_prompt` parameter in the SQLator constructor.
If you have access to GPT-4, you may see a great improvement in its abilities.
 
## Error handling

Currently I have defined three different exceptions that you may catch and handle
as you see fit.

**OnlySelectException:** Thrown when the AI resolves a command to a non-SELECT query
and SQLator is in read-only mode. The exception will contain the original user command
and the response from the AI.

**NotSingleSelectStatement:** Thrown when the AI resolves a command to something
that is not a single select statement and SQLator is in read-only mode. This
could occur if the user query somehow resolves to multiple queries. The exception
will contain the original user command and the response from the AI.

**AI_APIException:** Thrown when there is a ClientException to the request to the
AI endpoint. The exception will contain the HTTP error code and the HTTP response 
body encoded in JSON.

## Testing

PHPUnit is included as a dev dependency, so you can run the suite of tests I
have written using it. To bring up the testing environment, use `docker-compose up`.
See the [docker-compose.yml](https://github.com/npbreland/SQLator/blob/main/docker-compose.yml)
for more details. You may need to change the ports if you already have something
running on the defaults. To run the tests, you can run this:

`docker exec sqlator_www ./vendor/bin/phpunit tests`

This will run the command inside the sqlator_www container, which you'll need to 
do for the Docker network DNS resolution to work properly.

Note that since we are working with the output of a language model, tests may
occasionally fail. I have reduced the likelihood of variable responses by 
decreasing the "temperature" in the model's settings, but it could still happen.
You may also get 429 "Too Many Request" errors if you run the tests too much in 
a short span of time.

Feel free to contribute more tests if you like. I am using
the ORM [RedBeanPHP](https://www.redbeanphp.com/index.php) to seed the test database.
I have added a short `sleep` in between tests to reduce this.

## Contributing

Please feel free to contribute! You can have a look at the [TODO.md](https://github.com/npbreland/SQLator/blob/main/TODO.md) 
to see what I am considering so far, but if you have other ideas I am definitely open to them!

