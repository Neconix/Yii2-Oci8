<?php
/**
 * @author Neconix (prostoe@gmail.com)
 */

namespace neconix\yii2oci8;

use Yii;

use yii\caching\TagDependency;
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
     * @var string|null Cache unique dependency tag name. Cache data updated and cleared by it's value.
     * If is `null` then evaluated as username and DSN pair by default.
     */
    public $cacheTagName = null;

    /**
     * @var int $schemaCacheDuration the number of seconds in which the cached value will expire. 0 means never expire.
     */
    public $schemaCacheDuration = 0;

    /**
     * @var callable|null Function for filtering table names after selected schemas reading but before caching
     */
    public $tableNameFilter = null;

    public function init()
    {
        if ($this->cacheTagName === null) {
            $this->cacheTagName = "[{$this->db->username}],[{$this->db->dsn}]";
        }
    }

    /**
     * Searching table names in a default schema or in [[cachingSchemas]]
     * @return array
     */
    private function queryTableSchemas()
    {
        $tableSchemas = [];
        if (count($this->cachingSchemas) === 0) {
            $tableSchemas = $this->findTableSchemas();
        } else {
            //Find tables from given schemas
            foreach ($this->cachingSchemas as $shemaName) {
                $tableSchemas = array_merge($tableSchemas, $this->findTableSchemas($shemaName));
            }
        }

        return $tableSchemas;
    }

    /**
     * Searching table schemas in a default or specified schema
     * @param string|null $schema
     * @return array
     */
    private function findTableSchemas($schema = null)
    {
        $tableNames = $this->getTableNames($schema, true);

        if (is_callable($this->tableNameFilter)) {
            $tableNames = array_filter($tableNames,
                function($tableName) use ($schema) {
                    return call_user_func($this->tableNameFilter, $tableName, $schema);
                }
            );
        }

        $tableSchemas = array_map(
            function($tableName) use ($schema) {
                $tableSchema = new TableSchema();
                $tableNameFull = $schema === null ? $tableName : "$schema.$tableName";
                $this->resolveTableNames($tableSchema, $tableNameFull);
                return $tableSchema;
            },
            $tableNames);

        return $tableSchemas;
    }

    /**
     * Builds schema cache
     */
    public function buildSchemaCache()
    {
        $this->cleanupCache();

        $tableSchemas = $this->queryTableSchemas();

        foreach ($tableSchemas as $tableSchema) {
            if ($this->findColumns($tableSchema)) {
                $this->findConstraints($tableSchema);

                $key = $this->getTableCacheKey($tableSchema);
                //Yii::$app->cache->delete($key);
                $result = Yii::$app->cache->set(
                    $key,
                    $tableSchema,
                    $this->schemaCacheDuration,
                    new TagDependency(['tags' => $this->cacheTagName])
                );

                if (YII_ENV_DEV) {
                    if ($result === true)
                        Yii::info("Table \"$tableSchema->fullName\" has been cached", 'Schema cache');
                    else
                        Yii::error("Caching of \"$tableSchema->fullName\" table failed", 'Schema cache');
                }
            }
        }

        Yii::$app->cache->set(
            $this->getCacheCreationTimeKey(),
            microtime(true),
            $this->schemaCacheDuration,
            new TagDependency(['tags' => $this->cacheTagName]));
    }

    /**
     * Cleanup schema cache by deleting all cached objects tagged by [[cacheTagName]]
     */
    public function cleanupCache()
    {
        TagDependency::invalidate(Yii::$app->cache, $this->cacheTagName);
    }

    /**
     * Returns table from cache. if $searchInCacheOnly is `false` then call [[loadTableSchema()]].
     */
    private function loadTable($name, $searchInCacheOnly = false)
    {
        $table = new TableSchema();
        $this->resolveTableNames($table, $name);

        $tablekey = $this->getTableCacheKey($table);
        $cachedTableSchema = Yii::$app->cache->get($tablekey);
        if ($cachedTableSchema !== false) {
            return [$cachedTableSchema, true];
        } else {
            if ($searchInCacheOnly !== true)
                return [parent::loadTableSchema($name), false];
            else
                return [null, false];
        }
    }

    /**
     * @inheritdoc
     */
    public function loadTableSchema($name)
    {
        list($table, $fromCache) = $this->loadTable($name);
        if ($fromCache === true) {
            return $table;
        } else {
            if (YII_ENV_DEV)
                Yii::error("Table \"$name\" not found in schema cache. " .
                    "You must rebuild schema cache, see Schema::buildSchemaCache().", 'Schema::loadTableSchema()');
            return parent::loadTableSchema($name);
        }
    }

    /**
     * Returns base cache key array
     * @return array
     */
    private function getUserSchemaCacheKey()
    {
        return [$this->cacheTagName];
    }

    private function getCacheCreationTimeKey()
    {
        $key = $this->getUserSchemaCacheKey();
        $key[] = '_SchemaCache_CreationTime';
        return $key;
    }

    public function getIsCached()
    {
        return Yii::$app->cache->exists($this->getCacheCreationTimeKey());
    }

    private function getTableCacheKey(TableSchema $table)
    {
        $tablekey = $this->getUserSchemaCacheKey();
        $tablekey[] = $table->schemaName;
        $tablekey[] = $table->name;
        return $tablekey;
    }

    /**
     * Return caching time or false if no schema cache exists
     * @return false|double
     */
    public function getCacheTime()
    {
        $v = Yii::$app->cache->get($this->getCacheCreationTimeKey());
        return $v;
    }

    public function getAllCachedTables()
    {
        $tableSchemas = $this->queryTableSchemas();
        $result = [];

        foreach ($tableSchemas as $tableSchema) {
            list($table, $fromCache) = $this->loadTable($tableSchema->fullName, true);
            if ($table !== null) {
                $result[] = $table;
            }
        }

        return $result;
    }
}
