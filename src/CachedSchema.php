<?php
/**
 * @author Neconix (prostoe@gmail.com)
 */

namespace neconix\yii2oci8;


use Yii;

use yii\db\TableSchema;

/**
 * CachedSchema is the class for retrieving metadata from an Oracle database with caching table schema
 *
 * @property string $lastInsertID The row ID of the last row inserted, or the last value retrieved from the
 * sequence object. This property is read-only.
 *
 */
class CachedSchema extends \yii\db\oci\Schema
{
    /**
     * @var array Array of strings with schema names for caching
     */
    public $cachingSchemas = [];

    /**
     * @var string Cache key name
     */
    protected $userCacheName = 'UserSchemaCache';

    /**
     * @var int $schemaCacheDuration the number of seconds in which the cached value will expire. 0 means never expire.
     */
    public $schemaCacheDuration = 0;

    /**
     * @var callable|null Function for filtering table names after selected schemas reading but before caching
     */
    public $tableNameFilter = null;

    protected function composeTableNames()
    {
        $tableNames = [];

        if (count($this->cachingSchemas) == 0) {
            $tableNames = $this->tableNames;
        } else {
            //Find tables from given schemas
            foreach ($this->cachingSchemas as $shemaName) {
                $tableNames = array_merge($tableNames, $this->getTableNames($shemaName));
            }
        }

        //Filter table names if needed
        if (is_callable($this->tableNameFilter)) {
            return array_filter($tableNames, $this->tableNameFilter);
        } else {
            return $tableNames;
        }
    }

    /**
     * Build schema cache
     */
    public function buildSchemaCache()
    {
        Yii::$app->cache->delete($this->getCacheCreationTimeKey());

        $tableNames = $this->composeTableNames();

        foreach ($tableNames as $tableName) {
            $table = new TableSchema();
            $this->resolveTableNames($table, $tableName);

            if ($this->findColumns($table)) {
                $this->findConstraints($table);

                $key = $this->getTableCacheKey($table);
                Yii::$app->cache->delete($key);
                $result = Yii::$app->cache->add(
                    $key,
                    $table,
                    $this->schemaCacheDuration);

                if (YII_ENV == 'dev' && $result) {
                    Yii::info("Table \"$tableName\" has been cached", 'cache');
                }
            }
        }
        Yii::$app->cache->add($this->getCacheCreationTimeKey(), microtime(true));
    }

    /**
     * Return caching time or false if no schema cache exists
     * @return false|double
     */
    public function getCacheTime()
    {
        return Yii::$app->cache->get($this->getCacheCreationTimeKey());
    }

    /**
     * @inheritdoc
     */
    public function loadTableSchema($name)
    {
        $table = new TableSchema();
        $this->resolveTableNames($table, $name);

        $tablekey = $this->getTableCacheKey($table);
        $tableCached = Yii::$app->cache->get($tablekey);
        if ($tableCached !== false) {
            return $tableCached;
        } else {
            if (YII_ENV == 'dev')
                Yii::error("Table \"$name\" schema cache not found. " .
                    "You must rebuild schema cache, see Schema::buildSchemaCache().", 'Schema::loadTableSchema()');
            return parent::loadTableSchema($name);
        }
    }

    private function getTableCacheKey(TableSchema $table)
    {
        $tablekey = $this->getUserSchemaCacheKey();
        $tablekey[] = $table->schemaName;
        $tablekey[] = $table->name;
        return $tablekey;
    }

    private function getUserSchemaCacheKey()
    {
        return [$this->db->dsn, $this->db->username, $this->userCacheName];
    }

    private function getCacheCreationTimeKey()
    {
        return [$this->getUserSchemaCacheKey(), $this->userCacheName, 'CreationTime'];
    }

    public function getIsCached()
    {
        return Yii::$app->cache->exists($this->getCacheCreationTimeKey());
    }

    public function getAllCachedTables()
    {
        $tableNames = $this->composeTableNames();
        $result = [];
        foreach ($tableNames as $tableName) {
            $result[] = $this->loadTableSchema($tableName);
        }
        return $result;
    }
}
