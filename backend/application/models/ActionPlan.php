<?php
class ActionPlan extends CI_Model {

    private $_table ='action_plan';

    public function findAll()
    {
            $query = $this->db->get($this->_table);
            return $query->result_array();
    }

    public function find($id)
    {
            $query = $this->db->get_where($this->_table, array('id' => $id));
            return $query->row_array();
    }

    public function save($data)
    {
            $this->db->insert($this->_table, $data);
    }

    public function saveBatch($data)
    {
            return $this->db->insert_batch($this->_table, $data);
    }

    public function update($id,$data)
    {
            return $this->db->update($this->_table, $data, array('id' =>$id));
    }

    public function destroy($id)
    {
            $this->db->delete($this->_table, array('id' => $id));
    }

    public function getFollowedUpStatus($id){
        $query = $this->db->get_where('action_followed_up_status_v', array('audit_trx_id' => $id));
            return $query->row_array();
    }

}