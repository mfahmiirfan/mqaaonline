<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MQAAItemController extends CI_Controller {
    public $is_token_verify_hookable=TRUE;
    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('MQAAItem','mqaa_item');
    }

    public function index()
    {
        $filter = $this->input->get();

        $data=$this->mqaa_item->findAll($filter);
        if(!$data){
            $data=[];
        }
        
        $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode($data));
    }

    public function show($id)
    {
        $data=$this->mqaa_item->find($id);
        if(!$data){
            $data=(object)[];
        }
        
        $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode($data));
    }


    public function store(){
        $data = $this->input->post();

        if($this->mqaa_item->save($data)){
            $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message'=>'MQAA Item stored successfully'
            ]));
        }
    }

    public function update($id){
        $data = $this->input->post();

        if($this->mqaa_item->update($id,$data)){
            $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message'=>'MQAA Item updated successfully'
            ]));
        }
    }

    public function delete($id){
        if($this->mqaa_item->destroy($id)){
            $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message'=>'MQAA Item deleted successfully'
            ]));
        }
    }
}