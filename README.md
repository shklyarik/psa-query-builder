# PSA Query Builder

A simple and powerful query builder for PHP and MySQL.

## Installation

Install the library using [Composer](https://getcomposer.org/):

```bash
composer require shklyarik/psa-query-builder
```

## Usage

### Connecting to the database

```php
<?php

require_once 'vendor/autoload.php';

use Psa\Qb\Db;

$db = new Db('localhost', 'user', 'password', 'database');
```

### Selecting data

```php
// Get all users
$users = $db->from('users')->all();

// Get one user
$user = $db->from('users')->where(['id' => 1])->one();

// Get all active users
$activeUsers = $db->from('users')->where(['status' => 'active'])->all();

// Get all users with the name "John"
$johns = $db->from('users')->where(['like', 'name', 'John'])->all();

// Get all users with an ID greater than 10
$users = $db->from('users')->where(['>', 'id', 10])->all();

// Get all users with an ID between 1 and 10
$users = $db->from('users')->where(['between', 'id', 1, 10])->all();

// Get all users with an ID in a list
$users = $db->from('users')->where(['in', 'id', [1, 2, 3]])->all();
```

### Inserting data

```php
$userId = $db->from('users')->insert([
    'name' => 'John Doe',
    'email' => 'john.doe@example.com',
]);
```

### Updating data

```php
$db->from('users')->where(['id' => 1])->update([
    'name' => 'Jane Doe',
]);
```

### Deleting data

```php
$db->from('users')->where(['id' => 1])->delete();
```

### Joins

```php
// Get all posts and their authors
$posts = $db->from('posts')
    ->select(['posts.*', 'users.name as author_name'])
    ->leftJoin('users', 'users.id = posts.user_id')
    ->all();
```

### Aggregation

```php
// Get the number of users
$userCount = $db->from('users')->count();

// Get the total number of posts for each user
$postCounts = $db->from('posts')
    ->select(['user_id', 'COUNT(*) as post_count'])
    ->groupBy('user_id')
    ->all();
```

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

[MIT](https://choosealicense.com/licenses/mit/)
