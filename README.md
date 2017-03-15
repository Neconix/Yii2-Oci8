# Yii2-Oci8
Yii2 OCI8 extension which uses well written [yajra/pdo-via-oci8](https://github.com/yajra/pdo-via-oci8) 
with optional full table schema caching. Supported PHP7.

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

    /* A session configuration example */
        $q = <<<SQL
begin
  execute immediate 'alter session set NLS_SORT=BINARY_CI';
  execute immediate 'alter session set NLS_TERRITORY=AMERICA';
  -- ATTENSION: A 'NLS_COMP=LINGUISTIC' option is slow down queries;
    -- execute immediate 'alter session set NLS_COMP=LINGUISTIC';
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

To enable caching for all tables in a schema add lines below in a database connection configuration `db.php`:

```php
    ...
    //Disabling Yii2 schema cache
    'enableSchemaCache' => false
    
    //Defining a cache schema component
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
To build schema cache after a connection opens:

```php
    'on afterOpen' => function($event) 
    {
        $event->sender->createCommand($q)->execute();

        /* @var $schema \neconix\yii2oci8\CachedSchema */
        $schema = $event->sender->getSchema();

        if (!$schema->isCached)
            //Rebuild schema cache
            $schema->buildSchemaCache();
    },
```