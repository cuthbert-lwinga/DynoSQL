# SQL Query Builder and Database Interaction Classes

This project provides a set of PHP classes for building SQL queries and interacting with a MySQL database. It offers a fluent interface for constructing queries, managing database connections, and performing CRUD operations on database tables.

## Table of Contents

- [Features](#features)
- [Classes](#classes)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Contributing](#contributing)
- [License](#license)

## Features

- Fluent interface for building SQL queries
- Object-oriented representation of database tables and columns
- Connection management with singleton pattern
- CRUD operations (Create, Read, Update, Delete)
- Support for joins, where clauses, ordering, and pagination
- Automatic loading of table structure from the database

## Classes

1. **QueryBuilder**: Constructs SQL queries using a fluent interface.
2. **SqlColumn**: Represents a column in a database table.
3. **SqlConnection**: Manages database connections using a singleton pattern.
4. **SqlTable**: Represents a database table and provides CRUD operations.
5. **DatabaseConfig**: Contains database connection configuration.

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/your-repo-name.git
   ```
2. Include the necessary files in your PHP project.

## Usage

### Building a Query

```php
$query = (new QueryBuilder('users'))
    ->select(['id', 'name', 'email'])
    ->where('age', '>', 18)
    ->orderBy('name', 'ASC')
    ->limit(10);

$sql = $query->toSql();
$bindings = $query->getBindings();
```

### Executing a Query

```php
$connection = SqlConnection::getInstance();
$result = $query->execute($connection);
```

### Working with Tables

```php
$usersTable = new SqlTable('users');

// Insert
$userId = $usersTable->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Select
$users = $usersTable->select(['name', 'email'], ['age' => 30], 10, 0, ['name' => 'ASC']);

// Update
$usersTable->update(['age' => 31], ['id' => $userId]);

// Delete
$usersTable->delete(['id' => $userId]);
```

## Configuration

Update the `DatabaseConfig.php` file with your database credentials:

```php
abstract class DatabaseConfig
{
    const host = "your_host";
    const name = "your_database_name";
    const username = "your_username";
    const password = "your_password";
}
```



