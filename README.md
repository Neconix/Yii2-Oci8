# Yii2-Oci8
Yii2 OCI8 extension which uses well written [yajra/pdo-via-oci8](https://github.com/yajra/pdo-via-oci8) 
with some useful but optional extras. Supported PHP7.

**Supported**
- Yii 2.x;
- yajra/pdo-via-oci8 1.x;
- \>= PHP 5.4;
- \>= PHP 7.0.

**Installation**

Add to your `composer.json` file:

```
  "repositories": [
     {
       "type": "vcs",
       "url": "https://github.com/Neconix/Yii2-Oci8.git"
     }
   ],
   
   "require": {
     "neconix/yii2-oci8": "1.*"
   }
```

And then run `composer update`.

**Yii2 configuration example for an oracle database**

Yii2 configuration:

```php
$config = [
    ...
    'components' => [
        ...
        'db' => require(__DIR__ . '/db.php'),
        ...
    ]
];
```

Database configuration in `db.php`:

```php
return [
    'class' => 'neconix\yii2oci8\Oci8Connection',
    'dsn' => 'oci:dbname=//192.168.0.1:1521/db.local;charset=AL32UTF8;',
    'username' => 'user',
    'password' => 'pass',
    'attributes' => [ PDO::ATTR_PERSISTENT => true ],
    'enableSchemaCache' => true, //Oracle dictionaries is too slow :(, enable caching
    'schemaCacheDuration' => 60 * 60, //1 hour
    'on afterOpen' => function($event) {

        $q = <<<SQL
begin
  execute immediate 'alter session set NLS_COMP=LINGUISTIC';
  execute immediate 'alter session set NLS_SORT=BINARY_CI';
  execute immediate 'alter session set NLS_TERRITORY=AMERICA';
end;
SQL;
        $event->sender->createCommand($q)->execute();
    }
];
```

**Example**

Feel free to use Yii2 `ActiveRecord` methods:

```php
$cars = Car::find()->where(['YEAR' => '1939'])->indexBy('ID')->all();
```

Getting a raw database handler and working with it:

```php
$dbh = Yii::$app->db->getDbh();
$stmt = oci_parse($dbh, "select * from DEPARTMENTS where NAME = :name");
$name = 'NYPD';
oci_bind_by_name($stmt, ':name', $name);
oci_execute($stmt);
...
//fetching result
```
