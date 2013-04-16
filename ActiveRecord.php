<?php
if (!defined('IN_CMS')) { exit(); }

/**
 * ActiveRecord
 * 
 * The ActiveRecord helper for Wolf CMS is intended to improve the default
 * Record class that comes with Wolf CMS. The class ActiveRecord extends the
 * Record class, adding a find() method in order to easily build advanced
 * queries. It also provides eager loading possiblities in order to reduce the
 * N + 1 problem. ActiveRecord is loosely inspired by the PHPActiveRecord
 * project. PHP 5.3+ is required.
 * 
 * @package     Helpers
 * 
 * @author      Nic Wortel <nic.wortel@nth-root.nl>
 * @copyright   Nic Wortel, 2012
 * @version     0.1.0
 */
class ActiveRecord extends Record {
    
    static $belongs_to = array();
    static $has_one = array();
    static $has_many = array();
    
    /**
     * Finds an object or array of objects based on supplied arguments.
     * 
     * Usage:
     * $obj = Class::find(array(
     *     'select'     => 'column1, column2', // (or leave out)
     *     'from'       => 'table_name', // (or leave out)
     *     'joins'      => 'INNER JOIN table_name2 ON table_name2.column = table_name.id', // (or leave out)
     *     'group_by'   => 'column2', // (or leave out)
     *     'having'     => 'column2 = value', // (or leave out)
     *     'order_by'   => 'column3', // (or leave out)
     *     'limit'      => 10, // (or leave out)
     *     'offset'     => 20 // (or leave out)
     * ));
     * 
     * @param mixed $args   Array of arguments
     * @return mixed        Object or array of objects
     */
    public static function find($args = null) {
        $class_name = get_called_class();
        $table_name = self::tableNameFromClassName($class_name);
        
        $select   = isset($args['select']) ? trim($args['select']) : '';
        $from     = isset($args['from']) ? trim($args['from']) : '';
        $joins    = isset($args['joins']) ? trim($args['joins']) : '';
        $group_by = isset($args['group']) ? trim($args['group']) : '';
        $having   = isset($args['having']) ? trim($args['having']) : '';
        $order_by = isset($args['order']) ? trim($args['order']) : '';
        $limit    = isset($args['limit']) ? (int) $args['limit'] : 0;
        $offset   = isset($args['offset']) ? (int) $args['offset'] : 0;
        
        $params = array();
        
        if (isset($args['where'])) {
            if (is_string($args['where'])) {
                $where = trim($args['where']);
                
            }
            elseif (is_array($args['where'])) {
                $where = trim(array_shift($args['where']));
                $params = $args['where'];
            }
        }
        
        if (isset($args['include'])) {
            $include  = is_array($args['include']) ? $args['include'] : array();
        }

        // Prepare query parts
        $select_string      = empty($select) ? 'SELECT *' : "SELECT $select";
        $from_string        = empty($from) ? "FROM $table_name" : "FROM $from";
        $joins_string       = empty($joins) ? '' : $joins;
        $where_string       = empty($where) ? '' : "WHERE $where";
        $group_by_string    = empty($group_by) ? '' : "GROUP BY $group_by";
        $having_string      = empty($having) ? '' : "HAVING $having";
        $order_by_string    = empty($order_by) ? '' : "ORDER BY $order_by";
        $limit_string       = $limit > 0 ? "LIMIT $limit" : '';
        $offset_string      = $offset > 0 ? "OFFSET $offset" : '';
        
        $sql = "$select_string $from_string $joins_string $where_string $group_by_string $having_string $order_by_string $limit_string $offset_string";
        
        if (count($params) > 0) {
            Record::logQuery($sql . ' | params: ' . join(', ', $params));
        }
        else {
            Record::logQuery($sql);
        }
        
        $stmt = self::$__CONN__->prepare($sql);
        if (!$stmt->execute($params)) {
            return false;
        }
        
        $objects = array();
        $ids = array();

        // Run!
        if ($limit == 1) {
            if ($object = $stmt->fetchObject($class_name)) {
            
                $objects[] = $object;
                if (isset($object->id)) {
                    $ids[] = $object->id;
                }
                
                $return = $object;
                
            }
        }
        else {
            $objects = array();
            $id = 1;
            while ($object = $stmt->fetchObject($class_name))
            {
                //$objects[$object->id] = $object;
                $objects[] = $object;
                if (isset($object->id)) {
                    $ids[] = $object->id;
                }
                else {
                    $ids[] = $id;
                    $id++;
                }
            }
            
            $return = $objects;
        }
        
        if (isset($include) && count($include) > 0) {
            
            foreach ($include as $include_name => $include_include) {
                
                if (is_numeric($include_name)) {
                    $include_name = $include_include;
                    $include_include = '';
                }
                
                if (isset($class_name::$has_many[$include_name])) {
                    $include_class = $class_name::$has_many[$include_name]['class_name'];
                    $include_table = $include_class::TABLE_NAME;
                    $foreign_key = $class_name::$has_many[$include_name]['foreign_key'];
                    
                    $select     = isset($class_name::$has_many[$include_name]['select']) ? trim($class_name::$has_many[$include_name]['select']) : '';
                    $through    = isset($class_name::$has_many[$include_name]['through']) ? trim($class_name::$has_many[$include_name]['through']) : '';
                    $through_singular   = isset($class_name::$has_many[$include_name]['through_singular']) ? trim($class_name::$has_many[$include_name]['through_singular']) : '';
                    $joins      = isset($class_name::$has_many[$include_name]['joins']) ? trim($class_name::$has_many[$include_name]['joins']) : '';
                    $where      = isset($class_name::$has_many[$include_name]['where']) ? trim($class_name::$has_many[$include_name]['where'][0]) . ' AND ' : '';
                    $group_by   = isset($class_name::$has_many[$include_name]['group']) ? trim($class_name::$has_many[$include_name]['group']) : '';
                    $having     = isset($class_name::$has_many[$include_name]['having']) ? trim($class_name::$has_many[$include_name]['having']) : '';
                    $order_by   = isset($class_name::$has_many[$include_name]['order']) ? trim($class_name::$has_many[$include_name]['order']) : '';
                    
                    if ($through != '') {
                        if (isset($class_name::$has_many[$through])) {
                            $join_class = $class_name::$has_many[$through]['class_name'];
                            $join_table = $join_class::TABLE_NAME;
                            
                            $joins .= isset($join_class::$has_many[$include_name]['joins']) ? trim($join_class::$has_many[$include_name]['joins']) : '';
                            
                            if (isset($include_class::$has_many[$through])) {
                                $joins .= ' INNER JOIN ' . $join_table . ' ON ' . $join_table . '.' . $include_class::$has_many[$through]['foreign_key'] . ' = ' . $include_table . '.id';
                            }
                            else {
                                $joins .= ' INNER JOIN ' . $join_table . ' ON ' . $join_table . '.id' . ' = ' . $include_table . '.' . $include_class::$belongs_to[$through_singular]['foreign_key'];
                            }
                            $where .= $join_table . '.' . $foreign_key . ' IN (' . join(',', $ids) . ')';
                        }
                    }
                    else {
                        $where .= $include_table . '.' . $foreign_key . ' IN (' . join(',', $ids) . ')';
                    }
                    
                    if (count($ids) > 0) {
                        
                        if ($select != '' && $through != '') {
                            $select .= ', ' . $join_table . '.' . $foreign_key . ' AS ' . $foreign_key;
                        }
                        elseif ($select != '') {
                            $select = $select;
                        }
                        elseif ($through != '') {
                            $select = '`' . $include_table . '`.*, ' . $join_table . '.' . $foreign_key . ' AS ' . $foreign_key;
                        }
                        else {
                            $select = '';
                        }
                        
                        $includedModels = $include_class::find(
                            array(
                                'select' => $select,
                                'joins' => $joins,
                                'where' => $where,
                                'group' => $group_by,
                                'having' => $having,
                                'order' => $order_by,
                                'include' => $include_include
                            )
                        );
                    }
                    else {
                        $includedModels = array();
                    }
                    
                    foreach ($objects as $key => $object) {
                        if (is_array($includedModels)) {
                            foreach ($includedModels as $includedModel) {
                                if ($through != '') {
                                    if ($includedModel->$foreign_key == $object->id) {
                                        if (isset($objects[$key]->$include_name)) {
                                            $arr = $objects[$key]->$include_name;
                                        }
                                        else {
                                            $arr = array();
                                        }
                                        $arr[] = $includedModel;
                                        $objects[$key]->$include_name = $arr;
                                    }
                                }
                                else {

                                    if ($includedModel->$foreign_key == $object->id) {
                                        if (isset($objects[$key]->$include_name)) {
                                            $arr = $objects[$key]->$include_name;
                                        }
                                        else {
                                            $arr = array();
                                        }
                                        $arr[] = $includedModel;
                                        $objects[$key]->$include_name = $arr;
                                    }

                                }
                            }
                        }
                        
                        if (!isset($objects[$key]->$include_name)) {
                            $objects[$key]->$include_name = array();
                        }
                        
                        $objects[$key]->__construct();
                        
                    }
                }
                elseif (isset($class_name::$belongs_to[$include_name])) {
                    $include_class = $class_name::$belongs_to[$include_name]['class_name'];
                    $foreign_key = $class_name::$belongs_to[$include_name]['foreign_key'];
                    
                    $foreign_keys = array();
                    foreach ($objects as $object) {
                        if (isset($object->$foreign_key)) {
                            $foreign_keys[] = $object->$foreign_key;
                        }
                    }
                    
                    if (count($foreign_keys) > 0) {
                        $includedModels = $include_class::find(array('where' => 'id IN (' . join(',', $foreign_keys) . ')', 'include' => $include_include));
                    }
                    else {
                        $includedModels = array();
                    }
                    
                    foreach ($objects as $key => $object) {
                        
                        foreach ($includedModels as $includedModel) {
                            if ($includedModel->id == $object->$foreign_key) {
                                $objects[$key]->$include_name = $includedModel;
                            }
                        }
                        
                        $objects[$key]->__construct();
                    }
                }
                else {
                    break;
                }
                
                $array = array();  
            }
        }
        
        if ($limit == 1) {
            $objects = array_shift($objects);
            
        }
        
        return $objects;
    }
    
    /**
     * Returns a record based on it's id.
     * 
     * Default method so that you don't have to create one for every model you write.
     * Can of course be overwritten by a custom findById() method (for instance when you want to include another model)
     * 
     * @param int $id       Object's id
     * @return              Single object
     */
    public static function findById($id) {
        return self::find(array(
            'where' => array('id = ?', $id),
            'limit' => 1
        ));
    }
    
}