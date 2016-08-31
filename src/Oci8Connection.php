<?php
/**
 * The OCI8 connection class for Yii2
 *
 * @author Neconix (prostoe@gmail.com)
 */

namespace neconix\yii2oci8;

use yii\db\Connection;

use ReflectionClass;
use PDOException;

/**
 * Class Oci8Connection
 * @package app\components
 */
class Oci8Connection extends Connection
{
    public $pdoClass = 'Yajra\Pdo\Oci8';

    /**
     * Creates the PDO instance from Yajra\Pdo\Oci8 component
     * @exception PDOException
     * @return \PDO the pdo instance
     */
    protected function createPdoInstance()
    {
        //Empty attributes property cases exception in Yajra\Pdo\Oci8::__construct() method
        if (!is_array($this->attributes))
            $this->attributes = [];

        try {
            return parent::createPdoInstance();
        } catch(PDOException $e) {
            throw $e;
        }
    }

    /**
     * Returns private database handler from the OCI8 PDO class instance
     * @return resource Oci8 resource handler
     */
    public function getDbh() {
        $prop = (new ReflectionClass($this->pdoClass))->getProperty('dbh');
        $prop->setAccessible(true);
        return $prop->getValue($this->masterPdo);
    }
}