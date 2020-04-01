# JQL

JSON Query Language (JQL - pronounced "jackal") is a query language system written in PHP for querying JSON strings and files. It also supports PHP `stdClass` objects. The "language" is heavily based on SQL, the popular database query language to allow easy adoption and quick learning, but it does have some limitations, simply because some of the advanced features of SQL are impossible to implement into such a system.

Before reading the complete documentation, please check out the [minimal example](#example-min) to better understand how to use JQL.

## Table of Contents:

1. [Installation](#section-install)
2. [Usage](#section-usage)
    - [Selecting Columns](#subsection-selecting)
    - [Updating Columns](#subsection-updating)
    - [Searching Data](#subsection-searching)
    - [Sorting Results](#subsection-sorting)
    - [Limiting Results](#subsection-limiting)
    - [Executing the Query](#subsection-execting)
3. [Function Reference](#section-func-ref)
    - [String functions](#subsection-stringfn)
    - [Numeric functions](#subsection-numfn)
    - [Date functions](#subsection-datefun)
    - [Aggregate functions](#subsection-aggfn)
    - [`update()` Functions](#subsection-updatefn)
4. [Localization](#section-localization)
5. [Examples](#section-examples)
6. [Requirements](#section-requirements)
7. [Roadmap](#section-roadmap)
8. [License](#section-license)


## <a id="section-install"></a> 1. Installation:

The easiest way to install the JQL library is using [Composer](https://getcomposer.org/). To add JQL to your project, simply run the following Composer command from your terminal:

```
$ composer require benmajor/jql
```

Alternatively, download all source files from the `src/` directory of the repository and include them in your project.

## <a id="section-usage"></a> 2. Usage:

JQL is designed to be intuitive for web developers to use; its syntax is loosely based upon SQL, and although the functionality is more limited, the results are as expected. The intention was to build a simple and efficient query language for JSON and PHP data structures. Usage is relatively simple and straightforward, but it is recommended to consult the [examples](#section-examples) in order to get the best results from JQL.

To get started, simply call the JQL constructor with your dataset. The constructor receives a single parameter, which can be any one of the following:

- JSON-encoded string
- String containing a file pointer
- A PHP array of objects

For example, all of the following constructors are valid:

Using a JSON-encoded string:

```php
$jql = new JQL('[{
    'id':      2,
    'name':    'John',
    'surname': 'Doe'
}]');
```

Pointer to a valid JSON file:

```php
$jql = new JQL('MyDataset.json');
```

PHP array of objects / associative arrays:

```php
$jql = new JQL([
  [
    'id'      => 1,
    'name'    => 'John',
    'surname' => 'Doe'
  ]
]);
```

The constructor will return a new JQL object upon which it is possible to perform functions, alternatively, you can run `SELECT`-style queries on the data to return specific columns, and also order the results and limit them by a particular number and/or offset. The following sections will provide details on selecting, updating, ordering and limiting the data.

### <a id="subsection-selecting"></a> Selecting specific columns:

With the JQL object created, you should call the `select()` method to request particular columns from the JSON data. 

Multiple fields should be specified in an array, but you can always request all fields by passing in a string containing `*` to the `select()` function as follows:

```php
$jql->select( '*' );
```

Alternatively, you can specify specific columns to retrieve by passing them in as an array:

```php
$jql->select([ 'name', 'surname' ]);
```

The above will return only the `name` and `surname` fields from each object in the specified dataset. 

In addition to returning specific columns, JQL has built in functions that can be called on fields before selecting them. The majority of JQL's in-built functions are based upon SQL functions, and work in a similar way. **Unfortunately, due to the complexity of SQL and the aim of this library to keep things fast and efficient, it is not possible to call multiple functions in one pass of the data.**

Below is an example `select()` using two function calls:

```php
$jql->select([
  'LCASE(name) AS name_lower',
  'CURRENT_TIMESTAMP() AS timestamp'
]);
```

For a full list of functions and examples, please see the [Function Reference](#section-func-ref) section of this documentation.

JQL is also able to return field aliases, and this can be achieved using the `AS` keyword. This is particularly handy if you need to reference a field by a shorter reference than its original column name. For example in the snippet above, `name_lower` is an alias field which does not exist in the original data structure. Aliases can be used without functions however, for example:

```php
$jql->select([
  'super_long_column_name AS id'
]);
```

### <a id="subsection-updating"></a> Updating specific columns:

In addition to selecting fields, JQL is able to udpate fields in the original data structure as well. This is achieved by using the `update()` method, which is particularly useful when combined with the `where()` method ([see details](#subsection-where)):

```php
$jql->update([
  'name' => 'NAME REPLACED'
]);
```

JQL also has built-in functions that can be used when updating columns. Please see the [Function Reference](#section-func-ref) for more details about functions that can be used with the `update()` method.

### <a id="subsection-where"></a> Searching data using `where()`:

The `where()` method works similarly to SQL's `WHERE` clause, and can be used to quickly filter the results for a `select()` or `update()` method. 

The `where()` method accepts a single string of clauses, separated by either `AND` or `OR`. At the time of writing, JQL does not support combined clauses, such as `AND` and `OR`, only one or the other. 

To filter the results for all records that have an `age` column value > 10, we can use:

```php
$jql->select('forename')->where('age > 10');
```

JQL supports a number of operators for use with the `where()` method as follows:

Operator | Description | Example
--- | --- | ---
`=` | Equal to | `field = 10`
`==` | Identical to (respects case) | `field == 'John Doe'`
`>` | Greater than | `field > 10`
`>=` | Greater than or equal to | `field >= 10`
`<` | Less than | `field < 10`
`<=` | Less than or equal to | `field <= 10`
`!=` | Not equal to | `field != 10`
`<>` | Greater than or less than | `field <> 10`
`LIKE` | Partially matches a string using the `%` wildcar - identical to SQL's `LIKE` clause | `surname LIKE '%wor%'`
`SLIKE` | Similar to `LIKE`, but strictly matches case. | `surname SLIKE 'Maj%'`
`NOT LIKE` | Opposite of `LIKE`; selects all records that DON'T match the specified pattern. | `surname NOT LIKE '%major'`
`NOT SLIKE` | Similar to `NOT LIKE`, but strictly matches case. | `surname NOT SLIKE '%or'`
`IN` | Where specified string exists in a set. | `forename IN (Doe, Major)`
`NOT IN` | Oppose of `IN`. | `forename NOT IN (Doe, Major)`
`CONTAINS` | Called on an array field, checks if the array contains the specified value. | `tags CONTAINS red`
`IS NULL` | Value is `null`. | `age IS NULL`
`IS NOT NULL` | Value is not `null`. | `age IS NOT NULL`
`IS EMPTY` | Array value is empty. | `tags IS EMPTY`
`IS NOT EMPTY` | Opposite of `IS EMPTY`. | `tags IS NOT EMPTY`


### <a id="subsection-sorting"></a> Sorting results:

JQL is able to order data based upon the column values specified. Multiple columns can be used for sorting, and the order can be specified for each column as follows:

```php
$jql->select('forename')->order('surname', 'ASC');
```

The above snippet will order the results in ascending order by the value of the `surname` column. It is possible to order data by making multipl columns to `order()`, for example to sort by `surname` ascending and `age` descending, we can do the following:

```php
$jql->select('forename')->order('surname', 'ASC')->order('age', 'DESC');
```

### <a id="subsection-limiting"></a> Limiting Results:

We can also limit results to a specific number using `limit()`, which can be particularly powerful when combined with `offset()`. For example, the following snippet will retrieve the first two matched records only:

```php
$jql->select('forename')->where('age > 10')->limit(2);
```

It is also possible to specify the offset as a second parameter to the `limit()` method. For example, the following snippet will return 2 results, with the first result being skipped:

```php
$jql->select('forename')->where('age > 10')->limit(2, 1);
```

You can alternatively specify the offset separately using the `offset()` method as follows (the following snippet is identical to the one shown above):

```php
$jql->select('forename')->where('age > 10')->limit(2)->offset(1);
```

### <a id="subsection-executing"></a> Executing the query:

Once we have set up the query using the `select()` and `update()` methods, it's time to actually execute the query. This can be achieved using a number of functions in JQl, depending upon the result required:

**`fetch()`**:<br />
The `fetch()` method will return an array of **all** result matches, limited to the specified limit and offset, and sorted by the specified columns. If no matches are found, the function returns `NULL`.

**`fetchOne()`**:<br />
The `fetchOne()` method will return the first result from the set of matches. It will be returned as an object, not an array. If no matches are found, the function returns `NULL`.

**`fetchAsJSON( $prettyPrint = false )`**:<br />
The `fetchAsJSON` method is the same as `fetch()`, except it returns a JSON-encoded string, rather than a PHP array. This function accepts a single parameter, which can be used to specify whether the returned JSON is pretty-printed or not -- must be a valid boolean (default is `false`).

**`fetchOneAsJSON()`**:<br />
As above, but for the first record.

**`saveAsFile( $pointer )`**:<br />
Saves the resulting data to a JSON file located at `$pointer`.

**`count()`**:<br />
Simply returns an integer showing the number of affected rows.

## <a id="section-func-ref"></a> 3. Function Reference:

All functions can be called on a column that appears in the JSON structure, or a fixed string (much like SQL). For example, the following both produce the same result:

```php
$json = [ 
  [
    'id' => 1,
    'forename' => 'John',
    'surname' => 'Doe'
  ]
];

$jql = new JQL($json);

$jql->select([ 'UPPER(forename)' ]); # Returns JOHN for the first record.
$jql->select([ 'UPPER(John)' ]);     # Returns JOHN for ALL records.
```

### `select()` functions:

The following functions can be used with the `select()` method:

#### <a id="subsection-stringfn"></a> String functions:

Function name | Description
--- | --- 
`APPEND(field, append)` | Appends `append` to the end of the value in `field`.
`CHAR_LENGTH(field)` | Returns the chatacter length of `field` as an integer. 
`CHARACTER_LENGTH(field)` | As above.
`CONCAT(field1, field2)` | Concatenate two fields into a single string.
`CONCAT_WS(field1, field2, str)` | Concatenate two fields into a single string using the specified separator
`DISTINCT(field)` | Returns a list of unique values for `field`. If `field` is an array, the function will merge all arays and return a distinct list. Otherwise, it merges all string values and returns a distinct list.
`FORMAT(field, sf)` | Format the specified field into a float value using the specified number of significant figures; adds decimal points and thousand separators.
`LCASE(field)` | Convert a field to lower case.
`LEFT(field, chars)` | Returns `chars` characters from the left of the specified field.
`LOWER(field)` | Alias of `LCASE`
`LPAD(field, pad, length)` | Pad the specified field with the string specified in `pad` to `length` on the left of the field.
`LTRIM(field)` | Trim any whitespace on the left-hand side of `field`.
`PREPEND(field, prepend)` | Prepends `prepend` to the beginning of the value in `field`.
`REPLACE(field, find, replace)` | Replace all instances of `find` with `replace` in `field`.
`REVERSE(field)` | Reverse the string specified in `field`.
`RIGHT(field, chars)` | Returns `chars` characters from the right of the specified field.
`RPAD(field, pad, length)` | Pad the specified field with the string specified in `pad` to `length` on the right of the field.
`RTRIM(field)` | Trim any whitespace on the right-hand side of `field`.
`SUBSTR(field, chars, offset)` | Returns a substring of `field`, with `chars` characters, starting at `offset`.
`SUBSTRING(field, chars, offset)` | Alias of `SUBSTR()`, see above.
`TRIM(field)` | Trim all whitespace from both sides of `field`.
`UCASE(field)` | Convert the specified field value to uppercase.
`UPPER(field)` | Alias of `UCASE()`, see above.

#### <a id="subsection-numfn"></a> Numeric functions:

Function name | Description
--- | ---
`ABS(field)` | Returns the absolute value of `field`.
`ACOS(field)` | Returns the arc cosine of `field`.
`ASIN(field)` | Returns the arc sine of `field`.
`ATAN(field)` | Returns the arc tangent of `field`.
`CEIL(field)` | Round up `field` to the nearest integer.
`CEILING(field)` | Alias of `CEIL()`, see above. 
`COS(field)` | Returns the cosine of `field`.
`COT(field)` | Returns the cotangent of `field`.
`FLOOR(field)` | Round down `field` to the nearest integer.
`RAND(arg1, arg2)` | Returns a random number using a Mersenne Twister RNG. If only `arg1` is specified, it will be used as a seed for the PRNG. If both `arg1` and `arg2` are specified, they will be used as the minimum and maximum random numbers to generate.
`RANDOM(arg1, arg2)` | Alias of `RAND()`, see above.
`SIN(field)` | Returns the sine of `field`.
`SQRT(field)` | Returns the square root of `field`.
`TAN(field)` | Returns the tangent of `field`.

#### <a id="subsection-datefn"></a> Date functions:

Function name | Description
--- | ---
`CURDATE()` | Returns the current date in `YYYY-MM-DD` format.
`CURRENT_DATE()` | Alias of `CURDATE()`, see above.
`CURTIME()` | Returns the current time in `HH:mm:ss` format.
`CURRENT_TIME()` | Alias of `CURRENT_TIME()`, see above.
`CURRENT_TIMESTAMP()` | Returns the number of seconds that have currently elapsed since the Unix epoch.
`DATE_FORMAT(field, format)` | Format the date held in `field` using `format`. Please note that `format` should match the tokens used for PHP's [`strftime()`](https://www.php.net/manual/en/function.strftime.php) function.
`DAY(field)` | Returns the day of the month for the given date in `field`.
`DAYNAME(field)` | Returns the name of the day for the given date in `field`.
`DAYOFMONTH(field)` | Alias of `DAY()`, see above.
`DAYOFWEEK(field)` | Returns the weekday index for the given date in `field`.
`DAYOFYEAR(field)` | Returns the day of the year for the given date in `field`.
`HOUR(field)` | Returns the number of hours for the given date in `field`.
`LAST_DAY(field)` | Returns the last day for the month of the date in `field`. Can be used to retrieve the number of days for the given date.
`MINUTE(field)` | Returns the number of minutes for the given date in `field`.
`MONTH(field)` | Returns the month number for the given date in `field`.
`MONTHNAME(field)` | Returns the name of the month for the given date in `field`.
`NOW()` | Returns the current date and time in `YYYY-MM-DD HH:mm:ss` format.
`SECOND(field)` | Returns the number of seconds for the given date in `field`.
`TIMESTAMP(field)` | Converts the date in `field` to a Unix timestamp.
`WEEK(field)` | Returns the week number for the given date in `field`.
`YEAR(field)` | Returns the year for the given date in `field`.

#### <a id="subsection-aggfn"></a> Aggregate functions:

Function name | Description
--- | ---
`AVG(field)` | Returns the average value (mean) of `field` from all matched records.
`MAX(field)` | Returns the maximum numeric value of `field` from all matched records.
`MIN(field)` | Returns the minimum numeric value of `field` from all matched records.
`SUM(field)` | Returns the sum value of `field` from all matched records.

### <a id="subsection-updatefn"></a> `update()` functions:

The following functions can be used with the `update()` method:

- `APPEND`
- `CONCAT_WS`
- `LCASE`
- `LEFT`
- `LOWER`
- `LPAD`
- `LTRIM`
- `PREPEND`
- `REPLACE`
- `REVERSE`
- `RIGHT`
- `RPAD`
- `RTRIM`
- `SUBSTR`
- `SUBSTRING`
- `TRIM`
- `UCASE`
- `UPPER`

## <a id="section-localization"></a> 6. Localization:

JQL is able to return the current date and time for the server, as well as month and day names for dates. As such, it has been developed with some built-in localization options that help when working with multi-language applications. If you require the month and day names in another language, you must first call the `setLocale()` method:

```php
$jql->setLocale('nl_NL');
```

The above snippet will set the current locale to `nl_NL`.

To handle different timezones for time functions, simply call `setTimezone()` and specify the required timezone:

```php
$jql->setTimezone('Europe/Paris');
```

## <a id="section-examples"></a> 5. Examples:

This section contains some examples demonstrating the usage of JQL. If you discover during your own coding that any of the functions do not work as expected, please raise an issue via the Github repository.

**All examples below assume the following JSON structure:**

```
[
  {
    'id': 1,
    'forename': 'John',
    'surname':  'Doe',
    'age': 25,
    'birthday': '1995-05-24',
    'tags': [ 'red', 'green' ]
  },
  {
    'id': 2,
    'forename': 'Joe',
    'surname': 'Bloggs',
    'age': 50,
    'birthday': '1955-02-24',
    'tags': [ 'red', 'blue' ]
  },
  {
    'id': 3,
    'forename': 'Foo',
    'surname': 'Bar',
    'age': 12,
    'birthday': '2007-12-09',
    'tags': [ ]
  },
  {
    'id': 4,
    'forename': 'John',
    'surname': 'Boy',
    'age': 19,
    'birthday': '2018-09-09'
  }
]
```

#### <a id="example-min"></a> Minimal Working Example:

Select the first and last name of each user, ordered by their surname and forename:

```php
$jql = new JQL($json);

$jql->select([ 'forename', 'surname' ])
    ->order('surname', 'ASC')
    ->order('forename', 'ASC')
    ->fetch();
```

#### Using functions:

Return the full name for each user by concatenating their `forename` and `surname` fields:

```php
$jql = new JQL($json);

$jql->select([ 'CONCAT_WS(forename, surname, \' \') AS full_name' ])
    ->fetch();
```

#### Using aggregate functions:

Get the average age of all users:

```php
$jql = new JQL($json);

$jql->select([ 'AVG(age) AS average_age' ])
    ->fetchOne();
```

#### Using `where()`:

Retrieve the average age and concatenated full name for all users that are tagged with `red`:

```php
$jql = new JQL($json);

$jql->select([ 'AVG(age) AS average_age', 'CONCAT_WS(forename, surname, \' \') AS full_name' ])
    ->where('tags CONTAINS red')
    ->fetch()
```

#### Updating a record:

Update user 1 to have a new field containing full name, and save the resulting update to a JSON file named `updated.json`:

```php
$jql = new JQL($json);

$jql->update([ 'full_name' => 'CONCAT_WS(forename, surname, \' \') AS full_name' ])
    ->where('id = 1')
    ->saveAsFile('updated.json');
```

#### Date functions:

Select the day on which the users were born, who are called `John` and are older than `18`:

```php
$jql = new JQL($json);

$jql->select([ 'DAYNAME(birthday) AS birth_day' ])
    ->where( 'forename = John AND age > 18' )
    ->fetch();
```

## <a id="section-requirements"></a> 6. Requirements:

JQL is self-contained and does not have any external library or framework dependencies. However, to work as expected, the minimum PHP requirements are shown below:

- PHP version >= 5.6
- PHP `json`  module enabled

## <a id="section-roadmap"></a> 7. Roadmap:


JQL is still in  development, and there's a hefty roadmap ahead of things I'd like to add to the library. If you'd like to contribute to the project, please get in touch!

- Support remote JSON files
- Support multiple where clause types (both `AND` and `OR`)

## <a id="section-license"></a> 8. License:

MIT License

Copyright (c) 2019 Ben Major

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
