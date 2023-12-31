<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class UserController extends CI_Controller {
    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('User','user');
        $this->load->model('Role','role');
        $this->load->model('Area','area');

        $this->load->helper('cookie');
    }

    public function index()
    {
        $filter = $this->input->get();

        $data=$this->user->findAll($filter);
        if(!$data){
            $data=[];
        }
        
        $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode($data));
    }

    public function show($id)
    {
        $data=$this->user->find($id);
        if(!$data){
            $data=(object)[];
        }
        
        $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode($data));
    }

    public function store(){
        $data = $this->input->post();

        if($this->user->save($data)){
            $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message'=>'User stored successfully'
            ]));
        }
    }

    public function update($id){
        $data = $this->input->post();

        if($this->user->update($id,$data)){
            $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message'=>'User updated successfully'
            ]));
        }
    }

    public function delete($id){
        if($this->user->destroy($id)){
            $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message'=>'User deleted successfully'
            ]));
        }
    }

    public function login(){
        $data = $this->input->post();
        // var_dump($data);
        if(!$this->user->isValid($data)){
            delete_cookie('tokenId');

            $this->output
            ->set_content_type('application/json')
            ->set_status_header(401)
            ->set_output(json_encode([
                'message'=>'Invalid username or password.'
            ]));
            return;
        }

    $user = $this->user->getUser(['username'=>$data['username']/*,'company_code'=>$data['company_code']*/]);

        $date = new DateTime();
        $payload['id']=$user['id'];
        $payload['username']=$user['username'];
        $payload['name']=$user['name'];
        $payload['role_id']=$user['role_id'];
        $payload['role_name']=$user['role_name'];
        $payload['company_code']=$user['company_code'];
        $payload['iat']=$date->getTimestamp();
        $payload['exp']=$date->getTimestamp()+60*60*2;

        

        $token=JWT::encode($payload,$this->config->item('jwt_key'),'HS256');
        $cookie= array(
            'name'   => 'tokenId',
            'value'  => $token,
            'expire'=>60*60*2,
            'httponly' => true,
        );
        $this->input->set_cookie($cookie);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message'=>'Login successfully',
                'role'=>$this->role->find($user['role_id'])
            ]));
    }

    public function logout(){
        delete_cookie('tokenId');

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message'=>'User logout successfully'
            ]));
    }


    public function vssVerify(){
        $username = $this->input->post('username');
        $password = $this->input->post('password');
        $company = $this->input->post('company_code');
        $areaId = $this->input->post('area_id');
        // var_dump($data);
        if(!$this->user->isValid(['username'=>$username,'password'=>$password,'company_code'=>$company])){
            $this->output
            ->set_content_type('application/json')
            ->set_status_header(401)
            ->set_output(json_encode([
                'message'=>'Invalid username or password.'
            ]));
            return;
        }

        $user = $this->user->getUser(['username'=>$username,'company_code'=>$company]);
        $area = $this->area->find($areaId);

        if($area['vss_id']!=$user['id']){
            $this->output
            ->set_content_type('application/json')
            ->set_status_header(401)
            ->set_output(json_encode([
                'message'=>"Unauthorized Access!"
            ]));
            return;
        }

        $date = new DateTime();
        $payload['id']=$user['id'];
        $payload['username']=$user['username'];
        $payload['name']=$user['name'];
        $payload['role_id']=$user['role_id'];
        $payload['role_name']=$user['role_name'];
        $payload['company_code']=$user['company_code'];
        $payload['iat']=$date->getTimestamp();
        $payload['exp']=$date->getTimestamp()+60*60*2;

        $token=JWT::encode($payload,$this->config->item('vss_jwt_key'),'HS256');
        $cookie= array(
            'name'   => 'VSSTokenId',
            'value'  => $token,
            'expire'=>60,//seconds
            'httponly' => true,
        );
        $this->input->set_cookie($cookie);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message'=>'Verification success',
                'vss_id'=> $payload['id']
            ]));
    }
}