<?php
/**
 * User: Vasiliy Shvakin (orbisnull) zen4dev@gmail.com
 */

namespace DeltaDb\Adapter;


use DeltaUtils\ArrayUtils;

abstract class AbstractAdapter implements AdapterInterface
{
    protected $connection;
    protected $dsn;
    protected $params = [];

    function __construct($dsn = null, $params = [])
    {
        if (!is_null($dsn)) {
            $this->setDsn($dsn);
        }
        if (!empty($params)) {
            $this->setParams($params);
        }
    }

    /**
     * @param mixed $dsn
     */
    public function setDsn($dsn)
    {
        $this->dsn = $dsn;
    }

    public function getDsn()
    {
        return $this->dsn;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function IsConnect()
    {
        return !is_null($this->connection);
    }

    /**
     * @param mixed $connection
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return mixed
     */
    public function getConnection()
    {
        return $this->connection;
    }

    public function getOrderBy($orderBy)
    {
        $orderStr = "";
        if (!is_null($orderBy)) {
            if (is_array($orderBy)) {
                if (ArrayUtils::getArrayType($orderBy) === -1) {
                    $orderField = $orderBy[0];
                    $orderDirect = $orderBy[1];
                    $orderStr = " order by {$orderField} {$orderDirect}";
                } else {
                    foreach ($orderBy as $key => $value) {
                        $orderStr = " order by {$key} {$value}";
                    }
                }
            } else {
                $orderStr = " order by {$orderBy}";
            }
        }

        return $orderStr;
    }

} 