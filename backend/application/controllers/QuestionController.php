<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class QuestionController extends CI_Controller {
    public $is_token_verify_hookable=TRUE;
    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('Question','question');
        $this->load->model('QuestionGroup','question_group');
        $this->load->model('MQAAItem','mqaa_item');
    }

    public function getAuditQuestions()
    {
        $mqaaItemId = $this->input->get('mqaa_item_id');

        $data=$this->mqaa_item->find($mqaaItemId);
        if($data){
            $data['question_groups']=[];
            $questionGroup = $this->question_group->getQuestionGroups($mqaaItemId);
            foreach($questionGroup as $row){
                $row['questions']= $this->question->getQuestions($row['id']);
                array_push($data['question_groups'],$row);
            }
        }else{
            $data=(object)[];
        }
        
        $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode($data));
    }
}