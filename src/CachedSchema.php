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

    /**
     * @param TableSchema[] $tableShemas
     * @return array
     */
    private function filterTableSchemas($tableShemas)
    {
        $filteredTables = [];
        //Filter table names if $tableNameFilter is a user callable
        if (is_callable($this->tableNameFilter)) {
            foreach ($tableShemas as $tableSchema) {
                if (call_user_func($this->tableNameFilter, $tableSchema->name, $tableSchema->schemaName))
                    $filteredTables[] = $tableSchema;
            }
        }
        return $filteredTables;
    }

    protected function composeTableNames()
    {
        $tableSchemas = [];
        if (count($this->cachingSchemas) == 0) {
            $tableSchemas = $this->filterTableSchemas($this->getTableSchemas());
        } else {
            //Find tables from given schemas
            foreach ($this->cachingSchemas as $shemaName) {
                $tableSchemas = $this->getTableSchemas($shemaName);
                $tableSchemas = array_merge($tableSchemas, $this->filterTableSchemas($tableSchemas));
            }
        }

        return $tableSchemas;
    }

    /**
     * Builds schema cache
     */
    public function buildSchemaCache()
    {
        Yii::$app->cache->delete($this->getCacheCreationTimeKey());

        $tableShemas = $this->composeTableNames();

        foreach ($tableShemas as $tableShema) {
            if ($this->findColumns($tableShema)) {
                $this->findConstraints($tableShema);

                $key = $this->getTableCacheKey($tableShema);
                Yii::$app->cache->delete($key);
                $result = Yii::$app->cache->add(
                    $key,
                    $tableShema,
                    $this->schemaCacheDuration);

                if (YII_ENV == 'dev' && $result) {
                    Yii::info("Table \"$tableShema->fullName\" has been cached", 'cache');
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
     * Returns table from cache, if found in it, or call [[loadTableSchema()]]
     */
    private function loadTable($name, $fromCacheOnly = false)
    {
        $table = new TableSchema();
        $this->resolveTableNames($table, $name);

        $tablekey = $this->getTableCacheKey($table);
        $tableCached = Yii::$app->cache->get($tablekey);
        if ($tableCached !== false) {
            return [$tableCached, true];
        } else {
            if ($fromCacheOnly !== true)
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
        $tableShemas = $this->composeTableNames();
        $result = [];

        foreach ($tableShemas as $tableShema) {
            list($table, $fromCache) = $this->loadTable($tableShema->fullName, true);
            if ($table !== null) {
                $result[] = $table;
            }
        }


        return $result;
    }
}
