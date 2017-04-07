<?php

/**
 * Sample Model for Google Analytics (GA) data query.
 * This model does not need to access any GA account.
 * It's whole purpose is to retrieve data from the
 * developer's database for using it in GA queries.
 *
 * @package     CodeIgniter
 * @subpackage  Models
 * @category    Models
 * @author      Armfoot
 */
class Report_model extends CI_Model {

    public function __construct() {
        $this->load->database();
    }

    /**
     * Returns all relevant records' data for Google Analytics querying
     */
    public function get_records($to_date){
        $this->db->select('record_id, title, description, publish_date')
                 ->from('records')
                  // only retrieve records that were published before $to_date
                 ->where("publish_date <= '$to_date'")
                 ->order_by('record_id asc');
        $query = $this->db->get();
        return $query->result_array();
    }
}
