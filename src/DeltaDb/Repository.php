<?php

namespace DeltaDb;


use DeltaDb\Adapter\AdapterInterface;

class Repository implements RepositoryInterface
{
    const METHOD_SET = 'set';
    const METHOD_GET = 'get';
    const FILTER_IN = 'input';
    const FILTER_OUT = 'output';

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    protected $dba = DbaStorage::DBA_DEFAULT;

    protected $metaInfo = [
        'tableName' => [
            'class'  => 'Entity',
            'id'     => 'id_field',
            'fields' => [
                'field_name' => [
                    'set'        => 'setMethodInEntity',
                    'get'        => 'getMethodInEntity',
                    'filters'    => [
                        'input'  => 'filterFromDbToEntity',
                        'output' => 'filterFromEntityDb',
                    ],
                    'validators' => [

                    ]
                ]
            ]
        ]
    ];

    protected $selfCache = [];

    public function setSelfCache($id, $data)
    {
        $this->selfCache[$id] = $data;
    }

    public function getSelfCache($id)
    {
        return (!isset($this->selfCache[$id])) ? null : $this->selfCache[$id];
    }


    public function getEntityClass($table = null)
    {
        $meta = $this->getMetaInfo();
        if (is_null($table)) {
            $table = $this->getTableName();
        }
        return $meta[$table]['class'];
    }

    /**
     * @param mixed $dba
     */
    public function setDba($dba)
    {
        $this->dba = $dba;
    }

    /**
     * @return mixed
     */
    public function getDba()
    {
        return $this->dba;
    }

    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function getAdapter()
    {
        if (is_null($this->adapter)) {
            $this->adapter = DbaStorage::getDba($this->getDba());
        }
        return $this->adapter;
    }

    /**
     * @return array
     */
    public function getMetaInfo()
    {
        return $this->metaInfo;
    }

    public function getTableName($entity = null)
    {
        $entityClass = (is_null($entity)) ? null : is_object($entity) ? '\\' .get_class($entity) : $entity;
        $cacheId = "tableName|{$entityClass}|";
        if ($tableName = $this->getSelfCache($cacheId)) {
            return $tableName;
        }
        $meta = $this->getMetaInfo();
        $tables = array_keys($meta);
        $tableName = null;
        if (is_null($entityClass)) {
            $tableName = reset($tables);
        } else {
            foreach ($tables as $table) {
                if ($meta[$table]['class'] === $entityClass) {
                    $tableName = $table;
                    break;
                }
            }
        }
        $this->setSelfCache($cacheId, $tableName);
        return $tableName;
    }

    public function getIdField($table)
    {
        $meta = $this->getMetaInfo();
        return $meta[$table]['id'];
    }

    public function getFields($table)
    {
        $meta = $this->getMetaInfo();
        $fields = array_keys($meta[$table]['fields']);
        return $fields;
    }

    public function getFieldMeta($table, $field)
    {
        $meta = $this->getMetaInfo();
        if (!isset($meta[$table]['fields'][$field])) {
            return null;
        }
        return $meta[$table]['fields'][$field];
    }

    public function getFieldMethod($table, $field, $method)
    {
        $fieldMeta = $this->getFieldMeta($table, $field);
        if ((!is_array($fieldMeta) || empty($fieldMeta)) || (!isset($fieldMeta["set"]) && !isset($fieldMeta["get"]))) {
            return $method . ucfirst($field);
        }
        if (!isset($fieldMeta[$method])) {
            return null;
        }
        $fieldMethod = $fieldMeta[$method];
        return $fieldMethod;
    }

    public function getFieldFilter($table, $field, $filter)
    {
        $meta = $this->getMetaInfo();
        if (!isset($meta[$table]['fields'][$field]['filters'][$filter])) {
            return null;
        }
        $fieldFilter = $meta[$table]['fields'][$field]['filters'][$filter];
        return $fieldFilter;
    }

    public function getFieldValidators($table, $field)
    {
        $meta = $this->getMetaInfo();
        if (!isset($meta[$table]['fields'][$field]['validators'])) {
            return null;
        }
        $validators = $meta[$table]['fields'][$field]['validators'];
        return $validators;
    }

    public function validateField($entity, $field, $value)
    {
        $table = $this->getTableName($entity);
        $validators = $this->getFieldValidators($table, $field);
        foreach($validators as $validator) {
            if (method_exists($entity, $validator)) {
                $result = $entity->{$validator}($value);
                if ($result === false) {
                    return false;
                }
            }
        }
        return true;
    }

    public function setField(EntityInterface $entity, $field, $value)
    {
        $table = $this->getTableName($entity);
        $setMethod = $this->getFieldMethod($table, $field, self::METHOD_SET);
        $inputFilter = $this->getFieldFilter($table, $field, self::FILTER_IN);
        if (!is_null($inputFilter) && method_exists($entity, $inputFilter)) {
            $value = $entity->{$inputFilter}($value);
        }

        if (!is_null($setMethod) && method_exists($entity, $setMethod)) {
            return $entity->{$setMethod}($value);
        }
        return false;
    }

    public function getField(EntityInterface $entity, $field)
    {
        $table = $this->getTableName($entity);
        $getMethod = $this->getFieldMethod($table, $field, self::METHOD_GET);
        $outputFilter = $this->getFieldFilter($table, $field, self::FILTER_OUT);
        if (is_null($getMethod) || !method_exists($entity, $getMethod)) {
            return null;
        }
        $value =  $entity->{$getMethod}();
        if (!is_null($outputFilter) && method_exists($entity, $outputFilter)) {
            $value = $entity->{$outputFilter}($value);
        }
        return $value;
    }

    public function findRaw(array $criteria = [], $table = null)
    {
        $adapter = $this->getAdapter();
        if (is_null($table)) {
            $table = $this->getTableName();
        }
        $data = $adapter->selectBy($table, $criteria);
        return $data;
    }

    public function saveRaw(array $fields, $table = null)
    {
        if (is_null($table)) {
            $table = $this->getTableName();
        }
        $idName = $this->getIdField($table);
        if (isset($fields[$idName]) && !empty($fields[$idName])) {
            return $this->updateRaw($fields, $table);
        } else {
            $result = $this->insertRaw($fields, $table);
            if (empty($result)) {
                return false;
            }
            return $result;
        }
    }

    public function insertRaw(array $fields, $table = null)
    {
        $adapter = $this->getAdapter();
        if (is_null($table)) {
            $table = $this->getTableName();
        }
        $idField = $this->getIdField($table);
        if (isset($fields[$idField]) || array_key_exists($idField, $fields)) {
            unset($fields[$idField]);
        }
        return $adapter->insert($table, $fields, $idField);
    }

    public function updateRaw($fields, $table = null)
    {
        $adapter = $this->getAdapter();
        if (is_null($table)) {
            $table = $this->getTableName();
        }
        $idField = $this->getIdField($table);
        $id = $fields[$idField];
        unset($fields[$idField]);
        return $adapter->update($table, $fields, [$idField => $id]);
    }

    public function deleteById($id, $table = null)
    {
        $adapter = $this->getAdapter();
        if (is_null($table)) {
            $table = $this->getTableName();
        }
        $idField = $this->getIdField($table);
        return $adapter->delete($table, [$idField => $id]);
    }

    public function create(array $data = null, $entityClass = null)
    {
        if (is_null($entityClass)) {
            $entityClass = $this->getEntityClass();
        }
        $entity = new $entityClass;
        if (!is_null($data)) {
            $this->load($entity, $data);
        }
        return $entity;
    }

    public function save(EntityInterface $entity)
    {
        $data = $this->reserve($entity);
        $table = $this->getTableName($entity);
        $idField = $this->getIdField($table);
        if (isset($data[$idField]) && !empty($data[$idField])) {
            return $this->updateRaw($data, $table);
        } else {
            $result = $this->insertRaw($data, $table);
            if (!$result) {
                return false;
            }
            $this->setField($entity, $idField, $result);
            return true;
        }
    }

    public function delete(EntityInterface $entity)
    {
        $table= $this->getTableName();
        $idName = $this->getIdField($table);
        $id = $this->getField($entity, $idName);
        if (empty($id)) {
            return false ;
        }
        return $this->deleteById($id, $table);
    }

    public function find(array $criteria = [], $entityClass = null)
    {
        if (is_null($entityClass)) {
            $entityClass = $this->getEntityClass();
        }
        $table = $this->getTableName($entityClass);
        $data = $this->findRaw($criteria, $table);
        $items = [];
        foreach($data as $row) {
            $items[] = $this->create($row, $entityClass);
        }
        return $items;
    }

    public function findOne(array $criteria = [], $entityClass = null)
    {
        $items = $this->find($criteria, $entityClass);
        if (empty($items)) {
            return null;
        }
        return reset($items);
    }

    public function findById($id, $entityClass = null)
    {
        $table = $this->getTableName($entityClass);
        $idName = $this->getIdField($table);
        $items = $this->find([$idName=>$id], $entityClass);
        if (empty($items)) {
            return null;
        }
        return reset($items);
    }

    public function load(EntityInterface $entity, array $data)
    {
        $table = $this->getTableName();
        $fields = $this->getFields($table);
        $fields = array_flip($fields);
        $data = array_intersect_key($data, $fields);
        foreach($data as $field=>$value) {
            $this->setField($entity, $field, $value);
        }
    }

    public function reserve(EntityInterface $entity)
    {
        $table = $this->getTableName();
        $fields = $this->getFields($table);
        $data = [];
        foreach($fields as $field) {
            $data[$field] = $this->getField($entity, $field);
        }
        return $data;
    }
}