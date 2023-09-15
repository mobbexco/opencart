<?php

/**
 * Db Class
 * 
 * This class alow the Mobbex php-plugins-sdk interact with platform database.
 */
class MobbexDb extends Model
{
    /** DB Tables prefix */
    public $prefix = DB_PREFIX;

    /**
     * Executes a sql query & return the results.
     * 
     * @param string $sql
     * 
     * @return bool|array
     */
    public function query($sql)
    {
        //Get the result
        $result = $this->db->query($sql);

        //Return the data result of the result
        if (preg_match('#^\s*\(?\s*(select|show|explain|describe|desc)\s#i', $sql))
            return $result->rows;

        //Return bool if there aren't data
        return $result;
    }
}