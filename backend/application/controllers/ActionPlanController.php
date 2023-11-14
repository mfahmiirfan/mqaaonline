<?php
defined('BASEPATH') or exit('No direct script access allowed');

class ActionPlanController extends CI_Controller
{
    public $is_token_verify_hookable = TRUE;
    public $methods_vss_token_verify_hookable = ['store'];
    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('ActionPlan', 'action_plan');
        $this->load->model('AuditTransaction', 'audit_transaction');
    }

    public function store()
    {
        $stream = $this->security->xss_clean($this->input->raw_input_stream);
        $json = json_decode($stream, true);

        //today
        $datetime = new DateTime();
        $timezone = new DateTimeZone('Asia/Jakarta');
        $datetime->setTimezone($timezone);

        $isEmpty = true;
        $ret = false;
        if ($json['action_plans']) {
            $isEmpty = false;
            $ret = $this->action_plan->saveBatch($json['action_plans']);
        }

        // echo $isEmpty;exit;

        if ($ret || $isEmpty) {
            $data=[
                'reviewed_at' => $datetime->format('Y-m-d H:i:s'),
                'vss_id' => $json['vss_id']
            ];
            if($isEmpty){
                $data['completed_at']=$data['reviewed_at'];
            }
            $this->audit_transaction->update($json['audit_trx_id'], $data);
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message' => 'Review saved successfully!',
            ]));
    }

    public function update()
    {
        $json = json_decode($this->input->post('json'), true);

        //today
        $datetime = new DateTime();
        $timezone = new DateTimeZone('Asia/Jakarta');
        $datetime->setTimezone($timezone);

        $id = $json['id'];
        $actual = $json['actual'];
        $isImplemented = $json['is_implemented'];
        $auditTrxId = $json['audit_trx_id'];

        $config['upload_path']          = './uploads/photo/';
        $config['allowed_types']        = 'gif|jpg|png|jpeg|jfif|heif';
        // $config['max_size']             = 5000;
        // $config['max_width']            = 1024;
        // $config['max_height']           = 768;

        $this->load->library('upload', $config);

        $config['file_name'] = time() . "_" . preg_replace('/[^A-Za-z0-9.]/', "", $_FILES['documentation']['name']);
        // echo  $_FILES['documentation']['name'];exit;
        $this->upload->initialize($config);
        if (!$this->upload->do_upload('documentation')) {
            $error = json_encode(array('error' => $this->upload->display_errors()));

            // $this->load->view('upload_form', $error);
            // echo "$row[filename]: $error\n";
        } else {
            $data = array('upload_data' => $this->upload->data());

            // $this->load->view('upload_success', $data);
            $update['actual']=$actual;
            $update['is_implemented']=$isImplemented;
            $update['documentation'] = "/$config[file_name]";
            $update['last_updated_at'] = $datetime->format('Y-m-d H:i:s');
            if($this->action_plan->update($id, $update)){
                $status = $this->action_plan->getFollowedUpStatus($auditTrxId);
                if($status['followed_up_at']!==null){
                    $this->audit_transaction->update($auditTrxId,['last_followed_up_at'=>$status['followed_up_at'],'completed_at'=>$status['followed_up_at']]);
                }
            }
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message' => 'Action plan updated successfully!',
                'audit_trx_id' => $auditTrxId,
            ]));
    }
}
