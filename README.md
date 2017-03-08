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
   "require": {
     "neconix/yii2-oci8": "1.*"
   }
```

And then run `composer update`.

**Yii2 configuration example for an Oracle database**

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

**Caching features**

To enable caching for all tables in a schema add lines below in database connection configuration:

```php
    ...
    'cachedSchema' => [
        'class' => 'neconix\yii2oci8\CachedSchema',
        // Optional, dafault is current connection schema.
        'cachingSchemas' => ['HR', 'SCOTT'],
        // Optional. This callback must return true for a table name if it need to be cached.
        'tableNameFilter' => function ($tableName) {
            //Cache everything but the EMP table from HR and SCOTT schemas
            return $tableName != 'EMP';
        }
    ],
    ...
```

Table schemas saves to the default Yii2 cache component.
To build schema cache after the connection is open:

```php
    'on afterOpen' => function($event) 
    {
        $event->sender->createCommand($q)->execute();

        /* @var $schema \neconix\yii2oci8\CachedSchema */
        $schema = Yii::$app->oraclepdo->getSchema();

        if (!$schema->isCached)
            //Rebuild schema cache
            $schema->buildSchemaCache();
    },
```