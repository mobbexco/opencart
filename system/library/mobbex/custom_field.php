<?php

/**
 * Custom Field Class.
 * 
 * Its allows to store various Mobbex data in DB. 
 */
class MobbexCustomField extends Model
{
    public $table = DB_PREFIX . "mobbex_custom_fields";
    
    /**
     * Get a Mobbex custom field from db.
     * 
     * @param int $row_id
     * @param string $object
     * @param string $fieldName
     * @param string $searchedColumn
     * 
     * @return mixed
     */
    public function get($row_id, $object, $fieldName, $searchedColumn = 'data')
    {
        $result = $this->db->query("SELECT * FROM $this->table WHERE row_id='$row_id' AND object='$object' AND field_name='$fieldName'");

        return !empty($result->rows[0][$searchedColumn]) ? $result->rows[0][$searchedColumn] : false;
    }

    /**
     * Save data in Mobbex custom field.
     * 
     * @param int $row_id
     * @param string $object
     * @param string $fieldName
     * @param string $searchedColumn
     * 
     * @return bool
     */
    public function save($row_id, $object, $fieldName, $data)
    {
        //Update data if already exists
        if($this->get($row_id, $object, $fieldName))
            return $this->db->query("UPDATE $this->table SET data = '$data' WHERE row_id = $row_id AND object = '$object' AND field_name = '$fieldName';");
        
        //Insert data
        return $this->db->query("INSERT INTO $this->table (`row_id`, `object`, `field_name`, `data`) VALUES ($row_id, '$object', '$fieldName', '$data');");
    }
}
