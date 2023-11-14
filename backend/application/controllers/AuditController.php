<?php
defined('BASEPATH') or exit('No direct script access allowed');
date_default_timezone_set("Asia/Jakarta");

use PhpOffice\PhpSpreadsheet\Helper\Sample;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;


// require_once __DIR__ . '/../Bootstrap.php';

class AuditController extends CI_Controller
{
    public $is_token_verify_hookable = TRUE;
    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('AuditTransaction', 'audit_transaction');
        $this->load->model('AuditAnswer', 'audit_answer');
        $this->load->model('Finding', 'finding');
        $this->load->model('User', 'user');
        $this->load->model('Question', 'question');
        $this->load->model('QuestionGroup', 'question_group');
        $this->load->model('Area', 'area');
        $this->load->model('MQAAItem', 'mqaa_item');
        $this->load->library('Custom', 'custom');
        $this->load->helper('cookie');
    }


    public function getDatabasesPaginated()
    {
        $filter = $this->input->get();
        $data = $this->audit_transaction->getDatabases2(
            $filter
        );

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    public function getRecordsPaginated()
    {
        $filter = $this->input->get();
        $data = $this->audit_transaction->getRecords2(
            $filter
            // [
            // 'id'=>$this->input->get('id'),
            // 'limit'=>$this->input->get('limit')?$this->input->get('limit'):10,
            // 'direction'=>$this->input->get('direction'),
            // 'page'=>$this->input->get('page')
            // ]
        );

        // var_dump($data);

        foreach ($data['current10'] as $i => $row) {
            $data['current10'][$i]['possible'] = $data['current10'][$i]['enable'] = $data['current10'][$i]['na'] = $data['current10'][$i]['so'] = null;
            if (isset($data['current10'][$i]['reviewed_at'])) {
                $data['current10'][$i]['possible'] = $this->audit_answer->countPosible($row['id']);
                $data['current10'][$i]['enable'] = $this->audit_answer->countEnable($row['id']);
                $data['current10'][$i]['na'] = $this->audit_answer->countNA($row['id']);
                $data['current10'][$i]['so'] = $this->audit_answer->countAll($row['id']);
            }

            // $row['answers']=$this->audit_answer->findByAuditTrxId($row['id']);
            // $row['findings']=$this->finding->findByAuditTrxId($row['id']);
            // array_push($data,$row);
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    public function getRecords()
    {
        $filter = $this->input->get();

        $trx = $this->audit_transaction->getRecords($filter);

        $data = [];
        if ($trx) {
            foreach ($trx as $row) {
                $row['possible'] = $row['enable'] = $row['na'] = null;
                if (isset($row['reviewed_at'])) {
                    $row['possible'] = $this->audit_answer->countPosible($row['id']);
                    $row['enable'] = $this->audit_answer->countEnable($row['id']);
                    $row['na'] = $this->audit_answer->countNA($row['id']);
                }

                // $row['answers']=$this->audit_answer->findByAuditTrxId($row['id']);
                // $row['findings']=$this->finding->findByAuditTrxId($row['id']);
                array_push($data, $row);
            }
        } else {
            $data = [];
        }

        // exit;
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    public function getFindings($auditTrxId)
    {
        $data = $this->audit_transaction->getRecord($auditTrxId);
        $answers = $this->audit_answer->getAnswers($auditTrxId, ['answer' => 'EN']);
        $questions = [];
        foreach ($answers as $row) {
            $question = $this->question->find($row['question_id']);
            $questionGroup = $this->question_group->find($question['question_group_id']);
            $question['question_group_order'] = $questionGroup['order'];
            $question['findings'] = $this->finding->getFindings(['f.audit_trx_id' => $auditTrxId, 'question_id' => $row['question_id']]);
            array_push($questions, $question);
        }
        $data['questions'] = $questions;

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    public function check()
    {
        $where = $this->input->post();

        if ($this->audit_transaction->checkIfExists($where)) {

            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'message' => 'Already audited.'
                ]));
        } else if($this->audit_transaction->checkIfNotCompletedYet($where)){
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode([
                    'message' => 'There is an incomplete audit'
                ]));
        }else {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'message' => 'Audit available.'
                ]));
        }
    }
    public function checkIsReviewed($id)
    {
        $bool = $this->audit_transaction->isReviewed($id);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'bool' => $bool
            ]));
       
    }
    public function checkIsFollowedUp($id)
    {
        $bool = $this->audit_transaction->isFollowedUp($id);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'bool' => $bool
            ]));
       
    }
    public function checkIsCompleted($id)
    {
        $bool = $this->audit_transaction->isCompleted($id);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'bool' => $bool
            ]));
       
    }
    public function store()
    {
        
        // $stream = $this->security->xss_clean( $this->input->raw_input_stream );
        // $json = json_decode($stream, true);

        $json = json_decode($this->input->post('json'), true);

        $mqaaItemId = $json['mqaa_item_id'];
        $areaId = $json['area_id'];
        $building = $json['building'];
        $auditorId = $json['auditor_id'];
        $month = $json['month'];
        $year = $json['year'];
        $week = $json['week'];
        $companyCode = $json['company_code'];
        $answers = $json['answers'];
        $findings = isset($json['findings']) ? $json['findings'] : [];

        $insertId = $this->audit_transaction->save([
            'mqaa_item_id' => $mqaaItemId,
            'area_id' => $areaId,
            'building' => $building,
            'auditor_id' => $auditorId,
            'period_month' => $month,
            'period_year' => $year,
            'week' => $week, 'company_code' => $companyCode
        ]);
        // echo 'masuk';exit;

        foreach ($answers as $row) {
            $this->audit_answer->save([
                'audit_trx_id' => $insertId,
                'question_id' => $row['question_id'],
                'answer' => $row['answer'], 'company_code' => $row['company_code']
            ]);
        };

        $config['upload_path']          = './uploads/photo/';
        $config['allowed_types']        = 'gif|jpg|png|jpeg|jfif|heif';
        // $config['max_size']             = 5000;
        // $config['max_width']            = 1024;
        // $config['max_height']           = 768;

        $this->load->library('upload', $config);

        foreach ($findings as $row) {
            $finding = [
                'audit_trx_id' => $insertId,
                'question_id' => $row['question_id'],
                'description' => $row['description'], 'company_code' => $row['company_code']
            ];


            if (isset($row['filename'])) {
                $config['file_name'] = time() . "_" . preg_replace('/[^A-Za-z0-9.]/', "", $_FILES[$row['filename']]['name']);
                $this->upload->initialize($config);
                if (!$this->upload->do_upload($row['filename'])) {
                    $error = json_encode(array('error' => $this->upload->display_errors()));

                    // $this->load->view('upload_form', $error);
                    // echo "$row[filename]: $error\n";
                } else {
                    $data = array('upload_data' => $this->upload->data());

                    // $this->load->view('upload_success', $data);
                    $finding['photo'] = "/$config[file_name]";
                }
            }
            // var_dump($finding);
            $this->finding->save($finding);
        };

        $connection = new AMQPStreamConnection(config_item('amqp_host'), 5672, 'guest', 'guest');
        $channel = $connection->channel();

        $channel->exchange_declare('retina.audit_auto_complete', 'x-delayed-message', true, false, new AMQPTable(['x-delayed-type' => 'direct']));

        $tomorrow = strtotime("tomorrow");
        $schedule = strtotime("+10 hours", $tomorrow);
        $schedule2 = strtotime("+6 hours", $tomorrow);//email notification
        // echo date('Y-m-d h:i:s',$enddate);exit;
        //test
        // $tomorrow = strtotime("now");
        // $schedule = strtotime("+120 seconds", $tomorrow);
        // $schedule2 = strtotime("+60 seconds", $tomorrow);

        $token =get_cookie('tokenId');
        $decoded = JWT::decode($token, new Key($this->config->item('jwt_key'), 'HS256'));
        $data = json_decode(json_encode($decoded), true);

        $date = new DateTime();
        $payload['id']=$data['id'];
        $payload['username']=$data['username'];
        $payload['name']=$data['name'];
        $payload['role_id']=$data['role_id'];
        $payload['role_name']=$data['role_name'];
        $payload['company_code']=$data['company_code'];
        $payload['iat']=$date->getTimestamp();
        $payload['exp']=$schedule+60*60*0.5;

        $token=JWT::encode($payload,$this->config->item('jwt_key'),'HS256');
        

        $diff = $schedule-strtotime("now");
        $diff2 = $schedule2-strtotime("now");//email notification

        $vssPIC = null;
        if($vssArea = $this->area->find($areaId)){
            $vssPIC = $vssArea['vss_id'];
        }

        $data = [
            'id'=>$insertId,
            'vss_pic'=> $vssPIC,
            'token'=>$token,
            // 'diff'=>$diff
        ];
        $json = json_encode($data);

        $msg = new AMQPMessage($json, [
            'application_headers' => new AMQPTable([
                'x-delay' => $diff*1000 //convert to miliseconds
            ])
        ]);
        $channel->basic_publish($msg, 'retina.audit_auto_complete');


        //email notification
        $trx=$this->audit_transaction->getRecord($insertId);
        $monthName = date("M", mktime(0, 0, 0, $trx['period_month'], 10));
        $message="$trx[auditor] has submitted <u>$trx[mqaa_item]</u> MQA audit on <u>$trx[area]</u> area period <u>$monthName $trx[period_year] week $trx[week]</u>. This audit needs to be reviewed by <b>$trx[pic]</b> no later than 10am today, otherwise the audit score will be deducted by 20 points.";
        $data = [
            'id'=>$insertId,
            'recipient'=>$trx['manager_email'],
            'recipient_name'=>$trx['manager_name'],
            'message'=>$message,
            'token'=>$token,
            // 'diff'=>$diff
        ];
        $json = json_encode($data);
        $msg = new AMQPMessage($json, [
            'application_headers' => new AMQPTable([
                'x-delay' => $diff2*1000 //convert to miliseconds
            ])
        ]);
        $channel->basic_publish($msg, 'retina.email_notification');

        // echo ' [x] Sent ', $data, "\n";

        $channel->close();
        $connection->close();

        // exit;
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message' => 'Audit recorded successfully!',
                'audit_trx_id' => $insertId
            ]));
    }

    public function update($id){
        
        $data = $this->input->post();

        if($this->audit_transaction->update($id,$data)){
        $data['id']=$id;
            
            $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'message'=>'audit updated successfully',
                'data'=>$data
            ]));
        }
    }

    public function getDatabases()
    {
        $data = $this->audit_transaction->getDatabases();

        foreach ($data as $i => $row) {
            $data[$i]['findings'] = $this->finding->getFindings(['f.audit_trx_id' => $row['trx_id']]);
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    public function exportDatabases()
    {
        $filter = $this->input->post();

        $helper = new Sample();
        if ($helper->isCli()) {
            $helper->log('This example should only be run from a Web Browser' . PHP_EOL);

            return;
        }
        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();

        // Set document properties
        // $spreadsheet->getProperties()->setCreator('Maarten Balliauw')
        //     ->setLastModifiedBy('Maarten Balliauw')
        //     ->setTitle('Office 2007 XLSX Test Document')
        //     ->setSubject('Office 2007 XLSX Test Document')
        //     ->setDescription('Test document for Office 2007 XLSX, generated using PHP classes.')
        //     ->setKeywords('office 2007 openxml php')
        //     ->setCategory('Test result file');

        // Add some data
        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A1', 'Year')
            ->setCellValue('B1', 'Date')
            ->setCellValue('C1', 'Month')
            ->setCellValue('D1', 'Week')
            ->setCellValue('E1', 'Item')
            ->setCellValue('F1', 'Area')
            ->setCellValue('G1', 'Gedung')
            ->setCellValue('H1', 'Nik')
            ->setCellValue('I1', 'Leader')
            ->setCellValue('J1', 'Auditor')
            ->setCellValue('K1', 'Possible')
            ->setCellValue('L1', 'Enable')
            ->setCellValue('M1', 'Score')
            ->setCellValue('N1', 'Final Score')
            ->setCellValue('O1', 'Code')
            ->setCellValue('P1', 'Finding');

        //fill color skublue
        $spreadsheet->getActiveSheet()->getStyle('A1:P1')
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('bbdefb');


        $data = $this->audit_transaction->getDatabases($filter);

        $i = 2;
        foreach ($data as $row) {
            $findings = $this->finding->getFindings(['f.audit_trx_id' => $row['trx_id']]);
            if (count($findings) > 0) {
                foreach ($findings as $j => $finding) {
                    $spreadsheet->getActiveSheet()
                        ->setCellValue('A' . $i, $row['year'])
                        ->setCellValue('B' . $i, $row['date'])
                        ->setCellValue('C' . $i, $row['month'])
                        ->setCellValue('D' . $i, $row['week'])
                        ->setCellValue('E' . $i, $row['item'])
                        ->setCellValue('F' . $i, $row['area'])
                        ->setCellValue('G' . $i, $row['building'])
                        ->setCellValue('H' . $i, $row['nik'])
                        ->setCellValue('I' . $i, $row['leader'])
                        ->setCellValue('J' . $i, $row['name']);
                    if ($j == 0) {
                        $spreadsheet->getActiveSheet()
                            ->setCellValue('K' . $i, $row['possible'])
                            ->setCellValue('L' . $i, $row['enable'])
                            ->setCellValue('M' . $i, $row['score'])
                            ->setCellValue('N' . $i, $row['final_score']);
                    }

                    $spreadsheet->getActiveSheet()
                        ->setCellValue('O' . $i, $finding['g_order'] . "." . $finding['q_order'])
                        ->setCellValue('P' . $i, $finding['description']);
                    $i++;
                }
            } else {
                $spreadsheet->getActiveSheet()
                    ->setCellValue('A' . $i, $row['year'])
                    ->setCellValue('B' . $i, $row['date'])
                    ->setCellValue('C' . $i, $row['month'])
                    ->setCellValue('D' . $i, $row['week'])
                    ->setCellValue('E' . $i, $row['item'])
                    ->setCellValue('F' . $i, $row['area'])
                    ->setCellValue('G' . $i, $row['building'])
                    ->setCellValue('H' . $i, $row['nik'])
                    ->setCellValue('I' . $i, $row['leader'])
                    ->setCellValue('J' . $i, $row['name'])
                    ->setCellValue('K' . $i, $row['possible'])
                    ->setCellValue('L' . $i, $row['enable'])
                    ->setCellValue('M' . $i, $row['score'])
                    ->setCellValue('N' . $i, $row['final_score'])
                    ->setCellValue('O' . $i, '-')
                    ->setCellValue('P' . $i, 'NO FINDING');
                $i++;
            }
        }

        $spreadsheet->getActiveSheet()->getStyle('A1:P1')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $spreadsheet->getActiveSheet()->getStyle('A1:P1')->getFont()->setBold(true);

        // Miscellaneous glyphs, UTF-8
        // $spreadsheet->setActiveSheetIndex(0)
        //     ->setCellValue('A4', 'Miscellaneous glyphs')
        //     ->setCellValue('A5', 'éàèùâêîôûëïüÿäöüç');

        // Rename worksheet
        // $spreadsheet->getActiveSheet()->setTitle('Simple');

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        // Redirect output to a client’s web browser (Xlsx)
        // header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // header('Content-Disposition: attachment;filename="01simple.xlsx"');
        // header('Cache-Control: max-age=0');
        // // If you're serving to IE 9, then the following may be needed
        // header('Cache-Control: max-age=1');

        // // If you're serving to IE over SSL, then the following may be needed
        // header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        // header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        // header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        // header('Pragma: public'); // HTTP/1.0

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        ob_start();
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        $response =  array(
            'op' => 'ok',
            'file' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData),
            // 'image' => FCPATH . 'public/uploads/images/' . $newName, 'contoh' => __DIR__ . '/resources/logo_ubuntu_transparent.png', '__DIR__' => __DIR__
        );


        die(json_encode($response));
    }


    public function getItemScores()
    {
        $year = $this->input->get('year') ? $this->input->get('year') : date('Y');
        $month = $this->input->get('month') ? $this->input->get('month') : date('m');
        $week = $this->input->get('week') ? $this->input->get('week') : $this->custom->weekOfMonth(date('Y-m-d'));
        $companyCode = $this->input->get('company_code');

        $total = $this->audit_transaction->getScoreTot($year, $month, $week, $companyCode);

        $res['avg_months_tot'] = $total['avg_months_tot'];
        $res['avg_weeks_tot'] = $total['avg_weeks_tot'];
        $res['prev_years_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'y';
        }, ARRAY_FILTER_USE_KEY);
        $res['prev_months_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'm';
        }, ARRAY_FILTER_USE_KEY);
        $res['weeks_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'w';
        }, ARRAY_FILTER_USE_KEY);

        $data = $this->audit_transaction->getItemScore($year, $month, $week, $companyCode);
        // var_dump($data);

        $summary = [];
        foreach ($data as $row) {
            $item['mqaa_item_id'] = $row['mqaa_item_id'];
            $item['item'] = $row['item'];
            $item['avg_months'] = $row['avg_months'];
            $item['avg_weeks'] = $row['avg_weeks'];
            // var_dump($row);exit;
            $item['prev_years'] = array_filter($row, function ($k) {
                return $k[0] == 'y';
            }, ARRAY_FILTER_USE_KEY);
            $item['prev_months'] = array_filter($row, function ($k) {
                return $k[0] == 'm' && substr($k, 0, 4) != 'mqaa';
            }, ARRAY_FILTER_USE_KEY);
            $item['weeks'] = array_filter($row, function ($k) {
                return $k[0] == 'w';
            }, ARRAY_FILTER_USE_KEY);
            array_push($summary, $item);
        }

        $res['data'] = $summary;
        $res['year'] = $year;
        $res['month'] = $month;
        $res['week'] = $week;

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($res));
    }

    public function exportItemScores()
    {
        $pyear = $this->input->post('year') ? $this->input->post('year') : date('Y');
        $pmonth = $this->input->post('month') ? $this->input->post('month') : date('m');
        $pweek = $this->input->post('week') ? $this->input->post('week') : $this->custom->weekOfMonth(date('Y-m-d'));
        $companyCode = $this->input->post('company_code');
        // echo $pyear.'-'.$pmonth.'-'.$pweek;

        $helper = new Sample();
        if ($helper->isCli()) {
            $helper->log('This example should only be run from a Web Browser' . PHP_EOL);

            return;
        }
        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();

        // Set document properties
        // $spreadsheet->getProperties()->setCreator('Maarten Balliauw')
        //     ->setLastModifiedBy('Maarten Balliauw')
        //     ->setTitle('Office 2007 XLSX Test Document')
        //     ->setSubject('Office 2007 XLSX Test Document')
        //     ->setDescription('Test document for Office 2007 XLSX, generated using PHP classes.')
        //     ->setKeywords('office 2007 openxml php')
        //     ->setCategory('Test result file');

        // Add some data
        $spreadsheet->getActiveSheet()
            ->mergeCells("A1:A3")
            ->mergeCells("B1:B3")
            ->mergeCells("C1:C3")
            ->mergeCells("D1:D3")
            ->mergeCells("E1:E3")
            ->mergeCells("F1:F3");

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A1', 'Rank')
            ->setCellValue('B1', 'Item');

        //fill color orange
        $spreadsheet->getActiveSheet()->getStyle('A1:B1')
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFAB40');




        // $summary = $this->audit_transaction->getItemScoreSummary($pyear,$pmonth,$pweek);

        // foreach($summary as $i=>$row){
        //     $summary[$i]['prev_years'] = $this->audit_transaction->getItemScoreYearly($row['mqaa_item_id'],$pyear);
        //     $summary[$i]['prev_months'] = $this->audit_transaction->getItemScoreMonthly($row['mqaa_item_id'],$pyear,$pmonth,$pweek);
        //     $summary[$i]['weeks'] = $this->audit_transaction->getItemScoreWeekly($row['mqaa_item_id'],$pyear,$pmonth,$pweek);
        // }
        $rows = $this->audit_transaction->getItemScore($pyear, $pmonth, $pweek, $companyCode);
        // var_dump($data);

        $summary = [];
        foreach ($rows as $row) {
            $item['mqaa_item_id'] = $row['mqaa_item_id'];
            $item['item'] = $row['item'];
            $item['avg_months'] = $row['avg_months'];
            $item['avg_weeks'] = $row['avg_weeks'];
            // var_dump($row);exit;
            $item['prev_years'] = array_filter($row, function ($k) {
                return $k[0] == 'y';
            }, ARRAY_FILTER_USE_KEY);
            $item['prev_months'] = array_filter($row, function ($k) {
                return $k[0] == 'm' && substr($k, 0, 4) != 'mqaa';
            }, ARRAY_FILTER_USE_KEY);
            $item['weeks'] = array_filter($row, function ($k) {
                return $k[0] == 'w';
            }, ARRAY_FILTER_USE_KEY);
            array_push($summary, $item);
        }

        $lastYearIdx = 0;
        foreach (array_keys($summary[0]['prev_years']) as $i => $year) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow(3 + $i, 1, 'Avg ' . str_replace('y', '', $year));

            //fill color orange
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(3 + $i, 1)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFAB40');

            $lastYearIdx = 3 + $i;
        }

        $weekSpan = count($summary[0]['weeks']);
        $monthSpan = count($summary[0]['prev_months']) + $weekSpan;


        $startMonthIdx = $lastYearIdx + 1;
        $startMonth = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx, 1)->getCoordinate();
        $endMonth = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $monthSpan, 1)->getCoordinate();


        $spreadsheet->getActiveSheet()
            ->mergeCells("$startMonth:$endMonth");
        $spreadsheet->getActiveSheet()
            ->setCellValue($startMonth, $pyear);


        //fill color orange
        $spreadsheet->getActiveSheet()->getStyle($startMonth)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFAB40');

        // $lastMonthIdx=$startMonthIdx;
        $lastMonthIdx = $startMonthIdx;
        foreach (array_keys($summary[0]['prev_months']) as $i => $month) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startMonthIdx + $i, 2, date("M", mktime(0, 0, 0, str_replace('m', '', $month), 10)));

            //fill color yellow
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $i, 2)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('ffff00');


            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $i, 2)->getCoordinate();
            $cordinateSpan = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $i, 3)->getCoordinate();
            $spreadsheet->getActiveSheet()
                ->mergeCells("$cordinate:$cordinateSpan");
            $lastMonthIdx += 1;
        }

        // $startWeekIdx=$lastMonthIdx+1;
        $startWeekIdx = $lastMonthIdx;
        $startWeek = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx, 2)->getCoordinate();
        $endWeek = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx + $weekSpan, 2)->getCoordinate();

        $spreadsheet->getActiveSheet()
            ->mergeCells("$startWeek:$endWeek");
        $spreadsheet->getActiveSheet()
            ->setCellValue($startWeek, date("M", mktime(0, 0, 0, $pmonth, 10)));

        //fill color yellow
        $spreadsheet->getActiveSheet()->getStyle($startWeek)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('ffff00');

        $spreadsheet->getActiveSheet()
            ->setCellValueByColumnAndRow($startWeekIdx, 3, 'Avg');


        //fill color yellow
        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx, 3)->getCoordinate();
        $spreadsheet->getActiveSheet()->getStyle($cordinate)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('ffff00');

        $startWeekIdx++;
        $lastWeekIdx = 0;
        foreach (array_keys($summary[0]['weeks']) as $i => $week) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startWeekIdx + $i, 3, $week);

            //fill color yellow
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx + $i, 3)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('ffff00');

            $lastWeekIdx = $startWeekIdx + $i;
        }

        $startAvgYearIdx = $lastWeekIdx + 1;

        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startAvgYearIdx, 1)->getCoordinate();
        $cordinateSpan = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startAvgYearIdx, 3)->getCoordinate();
        $spreadsheet->getActiveSheet()
            ->mergeCells("$cordinate:$cordinateSpan");

        $spreadsheet->getActiveSheet()
            ->setCellValueByColumnAndRow($startAvgYearIdx, 1, 'Avg ' . $pyear);

        //fill color orange
        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startAvgYearIdx, 1)->getCoordinate();
        $spreadsheet->getActiveSheet()->getStyle($cordinate)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFAB40');

        $spreadsheet->getActiveSheet()->getStyle("A1:$cordinateSpan")
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle("A1:$cordinateSpan")
            ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);


        // $data = $this->audit_transaction->getScoreSummaryTotal($pyear,$pmonth);
        // $data['prev_years_tot'] = $this->audit_transaction->getScoreYearlyTotal($pyear);
        // $data['prev_months_tot'] = $this->audit_transaction->getScoreMonthlyTotal($pyear,$pmonth);
        // $data['weeks_tot'] = $this->audit_transaction->getScoreWeeklyTotal($pyear,$pmonth,$pweek);
        // $data['data']=$summary;
        $total = $this->audit_transaction->getScoreTot($pyear, $pmonth, $pweek, $companyCode);

        $data['avg_months_tot'] = $total['avg_months_tot'];
        $data['avg_weeks_tot'] = $total['avg_weeks_tot'];
        $data['prev_years_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'y';
        }, ARRAY_FILTER_USE_KEY);
        $data['prev_months_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'm';
        }, ARRAY_FILTER_USE_KEY);
        $data['weeks_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'w';
        }, ARRAY_FILTER_USE_KEY);

        $data['data'] = $summary;

        $lastRowIdx = 0;
        foreach ($data['data'] as $i => $row) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow(1, $i + 4, $i + 1)
                ->setCellValueByColumnAndRow(2, $i + 4, $row['item']);

            //fill color blue sky
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(2, $i + 4)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('80d8ff');

            $lastYearIdx = 0;
            foreach (array_values($row['prev_years']) as $j => $year) {
                $spreadsheet->getActiveSheet()
                    ->setCellValueByColumnAndRow(3 + $j, $i + 4, !is_null($year) ? round($year, 0) : $year);

                $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(3 + $j, $i + 4)->getCoordinate();
                if (isset($year)) {
                    if ($year >= 97) {
                        //fill color green
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('64dd17');
                    } elseif ($year <= 90) {
                        //fill color red
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('ff0000');
                    }
                } else {
                    //fill color black
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('424242');
                }

                $lastYearIdx = 3 + $j;
            }

            $startMonthIdx = $lastYearIdx + 1;

            $lastMonthIdx = $startMonthIdx;
            foreach (array_values($row['prev_months']) as $j => $month) {
                $spreadsheet->getActiveSheet()
                    ->setCellValueByColumnAndRow($startMonthIdx + $j, $i + 4, !is_null($month) ? round($month, 0) : $month);

                $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $j, $i + 4)->getCoordinate();
                if (isset($month)) {
                    if ($month >= 97) {
                        //fill color green
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('64dd17');
                    } elseif ($month <= 90) {
                        //fill color red
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('ff0000');
                    }
                } else {
                    //fill color black
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('424242');
                }

                $lastMonthIdx += 1;
            }

            $startWeekIdx = $lastMonthIdx;
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startWeekIdx, $i + 4, !is_null($row['avg_weeks']) ? round($row['avg_weeks'], 0) : $row['avg_weeks']);

            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx, $i + 4)->getCoordinate();
            if (isset($row['avg_weeks'])) {
                if ($row['avg_weeks'] >= 97) {
                    //fill color green
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('64dd17');
                } elseif ($row['avg_weeks'] <= 90) {
                    //fill color red
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('ff0000');
                }
            } else {
                //fill color black
                $spreadsheet->getActiveSheet()->getStyle($cordinate)
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('424242');
            }

            $startWeekIdx++;

            $lastWeekIdx = 0;
            foreach (array_values($row['weeks']) as $j => $week) {
                $spreadsheet->getActiveSheet()
                    ->setCellValueByColumnAndRow($startWeekIdx + $j, $i + 4, !is_null($week) ? round($week, 0) : $week);

                $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx + $j, $i + 4)->getCoordinate();
                if (isset($week)) {
                    if ($week >= 97) {
                        //fill color green
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('64dd17');
                    } elseif ($week <= 90) {
                        //fill color red
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('ff0000');
                    }
                } else {
                    //fill color black
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('424242');
                }

                $lastWeekIdx = $startWeekIdx + $j;
            }

            $startAvgYearIdx = $lastWeekIdx + 1;
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startAvgYearIdx, $i + 4, !is_null($row['avg_months']) ? round($row['avg_months'], 0) : $row['avg_months']);

            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startAvgYearIdx, $i + 4)->getCoordinate();
            if (isset($row['avg_months'])) {
                if ($row['avg_months'] >= 97) {
                    //fill color green
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('64dd17');
                } elseif ($row['avg_months'] <= 90) {
                    //fill color red
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('ff0000');
                }
            } else {
                //fill color black
                $spreadsheet->getActiveSheet()->getStyle($cordinate)
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('424242');
            }

            $lastRowIdx = 4 + $i;
        }

        $rowTotalIdx = $lastRowIdx + 1;


        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(1, $rowTotalIdx)->getCoordinate();
        $cordinateSpan = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(2, $rowTotalIdx)->getCoordinate();


        $spreadsheet->getActiveSheet()
            ->mergeCells("$cordinate:$cordinateSpan");

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue($cordinate, 'Grand Total');
        //fill color brown
        $spreadsheet->getActiveSheet()->getStyle($cordinate)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('a30000');
        $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));


        $lastYearIdx = 0;
        foreach (array_values($data['prev_years_tot']) as $j => $year) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow(3 + $j, $rowTotalIdx, !is_null($year) ? round($year, 0) : $year);

            //fill color blue
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(3 + $j, $rowTotalIdx)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0026ca');
            $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));


            $lastYearIdx = 3 + $j;
        }

        $startMonthIdx = $lastYearIdx + 1;

        $lastMonthIdx = $startMonthIdx;
        foreach (array_values($data['prev_months_tot']) as $j => $month) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startMonthIdx + $j, $rowTotalIdx, !is_null($month) ? round($month, 0) : $month);


            //fill color blue
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $j, $rowTotalIdx)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0026ca');
            $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));


            $lastMonthIdx += 1;
        }

        $startWeekIdx = $lastMonthIdx;

        $spreadsheet->getActiveSheet()
            ->setCellValueByColumnAndRow($startWeekIdx, $rowTotalIdx, !is_null($data['avg_weeks_tot']) ? round($data['avg_weeks_tot'], 0) : $data['avg_weeks_tot']);

        //fill color blue
        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx, $rowTotalIdx)->getCoordinate();
        $spreadsheet->getActiveSheet()->getStyle($cordinate)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('0026ca');
        $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));



        $startWeekIdx++;

        $lastWeekIdx = 0;
        foreach (array_values($data['weeks_tot']) as $j => $week) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startWeekIdx + $j, $rowTotalIdx, !is_null($week) ? round($week, 0) : $week);

            //fill color blue
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx + $j, $rowTotalIdx)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0026ca');
            $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));


            $lastWeekIdx = $startWeekIdx + $j;
        }

        $startAvgYearIdx = $lastWeekIdx + 1;

        $spreadsheet->getActiveSheet()
            ->setCellValueByColumnAndRow($startAvgYearIdx, $rowTotalIdx, !is_null($data['avg_months_tot']) ? round($data['avg_months_tot'], 0) : $data['avg_months_tot']);

        //fill color blue
        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startAvgYearIdx, $rowTotalIdx)->getCoordinate();
        $spreadsheet->getActiveSheet()->getStyle($cordinate)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('0026ca');
        $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));

        $spreadsheet->getActiveSheet()->getStyle("A1:$cordinate")->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(22);

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        ob_start();
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        $response =  array(
            'op' => 'ok',
            'file' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData),
            // 'image' => FCPATH . 'public/uploads/images/' . $newName, 'contoh' => __DIR__ . '/resources/logo_ubuntu_transparent.png', '__DIR__' => __DIR__
        );


        die(json_encode($response));
    }

    public function getAreaScores()
    {
        $year = $this->input->get('year') ? $this->input->get('year') : date('Y');
        $month = $this->input->get('month') ? $this->input->get('month') : date('m');
        $week = $this->input->get('week') ? $this->input->get('week') : $this->custom->weekOfMonth(date('Y-m-d'));
        $companyCode = $this->input->get('company_code');

        $total = $this->audit_transaction->getScoreTot($year, $month, $week, $companyCode);

        $res['avg_months_tot'] = $total['avg_months_tot'];
        $res['avg_weeks_tot'] = $total['avg_weeks_tot'];
        $res['prev_years_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'y';
        }, ARRAY_FILTER_USE_KEY);
        $res['prev_months_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'm';
        }, ARRAY_FILTER_USE_KEY);
        $res['weeks_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'w';
        }, ARRAY_FILTER_USE_KEY);

        $data = $this->audit_transaction->getAreaScore($year, $month, $week, $companyCode);
        // var_dump($data);

        $summary = [];
        foreach ($data as $row) {
            $item['area_id'] = $row['area_id'];
            $item['area'] = $row['area'];
            $item['avg_months'] = $row['avg_months'];
            $item['avg_weeks'] = $row['avg_weeks'];
            // var_dump($row);exit;
            $item['prev_years'] = array_filter($row, function ($k) {
                return $k[0] == 'y';
            }, ARRAY_FILTER_USE_KEY);
            $item['prev_months'] = array_filter($row, function ($k) {
                return $k[0] == 'm';
            }, ARRAY_FILTER_USE_KEY);
            $item['weeks'] = array_filter($row, function ($k) {
                return $k[0] == 'w';
            }, ARRAY_FILTER_USE_KEY);
            array_push($summary, $item);
        }

        $res['data'] = $summary;
        $res['year'] = $year;
        $res['month'] = $month;
        $res['week'] = $week;

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($res));
    }

    public function exportAreaScores()
    {
        $pyear = $this->input->post('year') ? $this->input->post('year') : date('Y');
        $pmonth = $this->input->post('month') ? $this->input->post('month') : date('m');
        $pweek = $this->input->post('week') ? $this->input->post('week') : $this->custom->weekOfMonth(date('Y-m-d'));
        $companyCode = $this->input->post('company_code');

        $helper = new Sample();
        if ($helper->isCli()) {
            $helper->log('This example should only be run from a Web Browser' . PHP_EOL);

            return;
        }
        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();

        // Set document properties
        // $spreadsheet->getProperties()->setCreator('Maarten Balliauw')
        //     ->setLastModifiedBy('Maarten Balliauw')
        //     ->setTitle('Office 2007 XLSX Test Document')
        //     ->setSubject('Office 2007 XLSX Test Document')
        //     ->setDescription('Test document for Office 2007 XLSX, generated using PHP classes.')
        //     ->setKeywords('office 2007 openxml php')
        //     ->setCategory('Test result file');

        // Add some data
        $spreadsheet->getActiveSheet()
            ->mergeCells("A1:A3")
            ->mergeCells("B1:B3")
            ->mergeCells("C1:C3")
            ->mergeCells("D1:D3")
            ->mergeCells("E1:E3")
            ->mergeCells("F1:F3");

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A1', 'Rank')
            ->setCellValue('B1', 'Area');

        //fill color orange
        $spreadsheet->getActiveSheet()->getStyle('A1:B1')
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFAB40');


        // $summary = $this->audit_transaction->getAreaScoreSummary($pyear,$pmonth,$pweek);

        // foreach($summary as $i=>$row){
        //     $summary[$i]['prev_years'] = $this->audit_transaction->getAreaScoreYearly($row['area_id'],$pyear);
        //     $summary[$i]['prev_months'] = $this->audit_transaction->getAreaScoreMonthly($row['area_id'],$pyear,$pmonth,$pweek);
        //     $summary[$i]['weeks'] = $this->audit_transaction->getAreaScoreWeekly($row['area_id'],$pyear,$pmonth,$pweek);
        // }
        $rows = $this->audit_transaction->getAreaScore($pyear, $pmonth, $pweek, $companyCode);

        $summary = [];
        foreach ($rows as $row) {
            $item['area_id'] = $row['area_id'];
            $item['area'] = $row['area'];
            $item['avg_months'] = $row['avg_months'];
            $item['avg_weeks'] = $row['avg_weeks'];
            // var_dump($row);exit;
            $item['prev_years'] = array_filter($row, function ($k) {
                return $k[0] == 'y';
            }, ARRAY_FILTER_USE_KEY);
            $item['prev_months'] = array_filter($row, function ($k) {
                return $k[0] == 'm' && substr($k, 0, 4) != 'mqaa';
            }, ARRAY_FILTER_USE_KEY);
            $item['weeks'] = array_filter($row, function ($k) {
                return $k[0] == 'w';
            }, ARRAY_FILTER_USE_KEY);
            array_push($summary, $item);
        }

        $lastYearIdx = 0;
        foreach (array_keys($summary[0]['prev_years']) as $i => $year) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow(3 + $i, 1, 'Avg ' . str_replace('y', '', $year));

            //fill color orange
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(3 + $i, 1)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFAB40');


            $lastYearIdx = 3 + $i;
        }

        $weekSpan = count($summary[0]['weeks']);
        $monthSpan = count($summary[0]['prev_months']) + $weekSpan;


        $startMonthIdx = $lastYearIdx + 1;
        $startMonth = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx, 1)->getCoordinate();
        $endMonth = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $monthSpan, 1)->getCoordinate();

        $spreadsheet->getActiveSheet()
            ->mergeCells("$startMonth:$endMonth");
        $spreadsheet->getActiveSheet()
            ->setCellValue($startMonth, $pyear);

        //fill color orange
        $spreadsheet->getActiveSheet()->getStyle($startMonth)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFAB40');

        $lastMonthIdx = $startMonthIdx;
        foreach (array_keys($summary[0]['prev_months']) as $i => $month) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startMonthIdx + $i, 2, date("M", mktime(0, 0, 0, str_replace('m', '', $month), 10)));

            //fill color yellow
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $i, 2)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('ffff00');

            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $i, 2)->getCoordinate();
            $cordinateSpan = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $i, 3)->getCoordinate();
            $spreadsheet->getActiveSheet()
                ->mergeCells("$cordinate:$cordinateSpan");
            $lastMonthIdx += 1;
        }

        $startWeekIdx = $lastMonthIdx;
        $startWeek = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx, 2)->getCoordinate();
        $endWeek = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx + $weekSpan, 2)->getCoordinate();

        $spreadsheet->getActiveSheet()
            ->mergeCells("$startWeek:$endWeek");
        $spreadsheet->getActiveSheet()
            ->setCellValue($startWeek, date("M", mktime(0, 0, 0, $pmonth, 10)));

        //fill color yellow
        $spreadsheet->getActiveSheet()->getStyle($startWeek)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('ffff00');

        $spreadsheet->getActiveSheet()
            ->setCellValueByColumnAndRow($startWeekIdx, 3, 'Avg');

        //fill color yellow
        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx, 3)->getCoordinate();
        $spreadsheet->getActiveSheet()->getStyle($cordinate)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('ffff00');

        $startWeekIdx++;
        $lastWeekIdx = 0;
        foreach (array_keys($summary[0]['weeks']) as $i => $week) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startWeekIdx + $i, 3, $week);

            //fill color yellow
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx + $i, 3)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('ffff00');

            $lastWeekIdx = $startWeekIdx + $i;
        }

        $startAvgYearIdx = $lastWeekIdx + 1;

        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startAvgYearIdx, 1)->getCoordinate();
        $cordinateSpan = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startAvgYearIdx, 3)->getCoordinate();
        $spreadsheet->getActiveSheet()
            ->mergeCells("$cordinate:$cordinateSpan");

        $spreadsheet->getActiveSheet()
            ->setCellValueByColumnAndRow($startAvgYearIdx, 1, 'Avg ' . $pyear);

        //fill color orange
        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startAvgYearIdx, 1)->getCoordinate();
        $spreadsheet->getActiveSheet()->getStyle($cordinate)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFAB40');

        $spreadsheet->getActiveSheet()->getStyle("A1:$cordinateSpan")
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle("A1:$cordinateSpan")
            ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);


        // $data = $this->audit_transaction->getScoreSummaryTotal($pyear,$pmonth);
        // $data['prev_years_tot'] = $this->audit_transaction->getScoreYearlyTotal($pyear);
        // $data['prev_months_tot'] = $this->audit_transaction->getScoreMonthlyTotal($pyear,$pmonth);
        // $data['weeks_tot'] = $this->audit_transaction->getScoreWeeklyTotal($pyear,$pmonth,$pweek);
        // $data['data']=$summary;
        $total = $this->audit_transaction->getScoreTot($pyear, $pmonth, $pweek, $companyCode);

        $data['avg_months_tot'] = $total['avg_months_tot'];
        $data['avg_weeks_tot'] = $total['avg_weeks_tot'];
        $data['prev_years_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'y';
        }, ARRAY_FILTER_USE_KEY);
        $data['prev_months_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'm';
        }, ARRAY_FILTER_USE_KEY);
        $data['weeks_tot'] = array_filter($total, function ($k) {
            return $k[0] == 'w';
        }, ARRAY_FILTER_USE_KEY);

        $data['data'] = $summary;

        $lastRowIdx = 0;
        foreach ($data['data'] as $i => $row) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow(1, $i + 4, $i + 1)
                ->setCellValueByColumnAndRow(2, $i + 4, $row['area']);

            //fill color blue sky
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(2, $i + 4)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('80d8ff');

            $lastYearIdx = 0;
            foreach (array_values($row['prev_years']) as $j => $year) {
                $spreadsheet->getActiveSheet()
                    ->setCellValueByColumnAndRow(3 + $j, $i + 4, !is_null($year) ? round($year, 0) : $year);

                $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(3 + $j, $i + 4)->getCoordinate();
                if (isset($year)) {
                    if ($year >= 97) {
                        //fill color green
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('64dd17');
                    } elseif ($year <= 90) {
                        //fill color red
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('ff0000');
                    }
                } else {
                    //fill color black
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('424242');
                }
                $lastYearIdx = 3 + $j;
            }

            $startMonthIdx = $lastYearIdx + 1;

            $lastMonthIdx = $startMonthIdx;
            foreach (array_values($row['prev_months']) as $j => $month) {
                $spreadsheet->getActiveSheet()
                    ->setCellValueByColumnAndRow($startMonthIdx + $j, $i + 4, !is_null($month) ? round($month, 0) : $month);

                $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $j, $i + 4)->getCoordinate();
                if (isset($month)) {
                    if ($month >= 97) {
                        //fill color green
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('64dd17');
                    } elseif ($month <= 90) {
                        //fill color red
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('ff0000');
                    }
                } else {
                    //fill color black
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('424242');
                }
                $lastMonthIdx += 1;
            }

            $startWeekIdx = $lastMonthIdx;
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startWeekIdx, $i + 4, !is_null($row['avg_weeks']) ? round($row['avg_weeks'], 0) : $row['avg_weeks']);


            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx, $i + 4)->getCoordinate();
            if (isset($row['avg_weeks'])) {
                if ($row['avg_weeks'] >= 97) {
                    //fill color green
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('64dd17');
                } elseif ($row['avg_weeks'] <= 90) {
                    //fill color red
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('ff0000');
                }
            } else {
                //fill color black
                $spreadsheet->getActiveSheet()->getStyle($cordinate)
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('424242');
            }
            $startWeekIdx++;

            $lastWeekIdx = 0;
            foreach (array_values($row['weeks']) as $j => $week) {
                $spreadsheet->getActiveSheet()
                    ->setCellValueByColumnAndRow($startWeekIdx + $j, $i + 4, !is_null($week) ? round($week, 0) : $week);

                $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx + $j, $i + 4)->getCoordinate();
                if (isset($week)) {
                    if ($week >= 97) {
                        //fill color green
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('64dd17');
                    } elseif ($week <= 90) {
                        //fill color red
                        $spreadsheet->getActiveSheet()->getStyle($cordinate)
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('ff0000');
                    }
                } else {
                    //fill color black
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('424242');
                }
                $lastWeekIdx = $startWeekIdx + $j;
            }

            $startAvgYearIdx = $lastWeekIdx + 1;
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startAvgYearIdx, $i + 4, !is_null($row['avg_months']) ? round($row['avg_months'], 0) : $row['avg_months']);
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startAvgYearIdx, $i + 4)->getCoordinate();
            if (isset($row['avg_months'])) {
                if ($row['avg_months'] >= 97) {
                    //fill color green
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('64dd17');
                } elseif ($row['avg_months'] <= 90) {
                    //fill color red
                    $spreadsheet->getActiveSheet()->getStyle($cordinate)
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('ff0000');
                }
            } else {
                //fill color black
                $spreadsheet->getActiveSheet()->getStyle($cordinate)
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('424242');
            }

            $lastRowIdx = 4 + $i;
        }

        $rowTotalIdx = $lastRowIdx + 1;

        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(1, $rowTotalIdx)->getCoordinate();
        $cordinateSpan = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(2, $rowTotalIdx)->getCoordinate();


        $spreadsheet->getActiveSheet()
            ->mergeCells("$cordinate:$cordinateSpan");

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue($cordinate, 'Grand Total');
        //fill color brown
        $spreadsheet->getActiveSheet()->getStyle($cordinate)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('a30000');
        $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));

        $lastYearIdx = 0;
        foreach (array_values($data['prev_years_tot']) as $j => $year) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow(3 + $j, $rowTotalIdx, !is_null($year) ? round($year, 0) : $year);

            //fill color blue
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(3 + $j, $rowTotalIdx)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0026ca');
            $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));


            $lastYearIdx = 3 + $j;
        }

        $startMonthIdx = $lastYearIdx + 1;

        $lastMonthIdx = $startMonthIdx;
        foreach (array_values($data['prev_months_tot']) as $j => $month) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startMonthIdx + $j, $rowTotalIdx, !is_null($month) ? round($month, 0) : $month);

            //fill color blue
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startMonthIdx + $j, $rowTotalIdx)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0026ca');
            $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));


            $lastMonthIdx += 1;
        }

        $startWeekIdx = $lastMonthIdx;

        $spreadsheet->getActiveSheet()
            ->setCellValueByColumnAndRow($startWeekIdx, $rowTotalIdx, !is_null($data['avg_weeks_tot']) ? round($data['avg_weeks_tot'], 0) : $data['avg_weeks_tot']);

        //fill color blue
        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx, $rowTotalIdx)->getCoordinate();
        $spreadsheet->getActiveSheet()->getStyle($cordinate)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('0026ca');
        $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));


        $startWeekIdx++;

        $lastWeekIdx = 0;
        foreach (array_values($data['weeks_tot']) as $j => $week) {
            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($startWeekIdx + $j, $rowTotalIdx, !is_null($week) ? round($week, 0) : $week);

            //fill color blue
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startWeekIdx + $j, $rowTotalIdx)->getCoordinate();
            $spreadsheet->getActiveSheet()->getStyle($cordinate)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0026ca');
            $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));


            $lastWeekIdx = $startWeekIdx + $j;
        }

        $startAvgYearIdx = $lastWeekIdx + 1;

        $spreadsheet->getActiveSheet()
            ->setCellValueByColumnAndRow($startAvgYearIdx, $rowTotalIdx, !is_null($data['avg_months_tot']) ? round($data['avg_months_tot'], 0) : $data['avg_months_tot']);

        //fill color blue
        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($startAvgYearIdx, $rowTotalIdx)->getCoordinate();
        $spreadsheet->getActiveSheet()->getStyle($cordinate)
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('0026ca');
        $spreadsheet->getActiveSheet()->getStyle($cordinate)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));


        $spreadsheet->getActiveSheet()->getStyle("A1:$cordinate")->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(42);

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        ob_start();
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        $response =  array(
            'op' => 'ok',
            'file' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData),
            // 'image' => FCPATH . 'public/uploads/images/' . $newName, 'contoh' => __DIR__ . '/resources/logo_ubuntu_transparent.png', '__DIR__' => __DIR__
        );


        die(json_encode($response));
    }

    public function getFindingScores()
    {
        $year = $this->input->get('year') ? $this->input->get('year') : date('Y');
        $month = $this->input->get('month') ? $this->input->get('month') : date('m');
        $week = $this->input->get('week') ? $this->input->get('week') : $this->custom->weekOfMonth(date('Y-m-d'));
        $companyCode = $this->input->get('company_code');

        $data = $this->audit_transaction->getFindingScore($year, $month, $week, $companyCode);

        $areas = array_map(function ($item) {
            return [
                'area_id' => $item['area_id'],
                'name' => $item['name']
            ];
        }, $data);

        // var_dump(array_unique($area,SORT_REGULAR));exit;\
        $areas = array_values(array_unique($areas, SORT_REGULAR));

        // $area = $this->area->findAll();

        foreach ($areas as $i => $row) {
            $items = array_filter($data, function ($var) use ($row) {
                return $var['area_id'] == $row['area_id'];
            });

            // var_dump($items);echo"\n\n";

            $items = array_map(function ($item) {
                return [
                    'mqaa_item_id' => $item['mqaa_item_id'],
                    'item_name' => $item['item'],
                    'trx_id' => $item['trx_id']
                ];
            }, $items);

            // var_dump($items);echo"\n\n";

            $items = array_values(array_unique($items, SORT_REGULAR));

            // $area[$i]['mqaa_items']=$this->mqaa_item->getItemsByArea($row['id']);
            $areaId = $row['area_id'];
            foreach ($items as $j => $row) {
                // echo $areaId." ".$row['id'];
                $findings = array_filter($data, function ($var) use ($areaId, $row) {
                    return $var['area_id'] == $areaId &&  $var['mqaa_item_id'] == $row['mqaa_item_id'];
                });
                // var_dump($findings);echo"\n\n";

                $findings = array_map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'g_order' => $item['g_order'],
                        'q_order' => $item['q_order'],
                        'description' => $item['description'],
                        // 'audit_trx_id'=>$item['audit_trx_id']
                    ];
                }, $findings);

                // var_dump(array_values($findings));echo"\n\n\n";



                $items[$j]['findings'] = array_values($findings);
            }
            $areas[$i]['mqaa_items'] = $items;
        }

        $res['year'] = $year;
        $res['month'] = $month;
        $res['week'] = $week;
        $res['data'] = $areas;


        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($res));
    }

    public function exportFindingScores()
    {
        $year = $this->input->post('year') ? $this->input->post('year') : date('Y');
        $month = $this->input->post('month') ? $this->input->post('month') : date('m');
        $week = $this->input->post('week') ? $this->input->post('week') : $this->custom->weekOfMonth(date('Y-m-d'));
        $companyCode = $this->input->post('company_code');


        $helper = new Sample();
        if ($helper->isCli()) {
            $helper->log('This example should only be run from a Web Browser' . PHP_EOL);

            return;
        }
        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A1', 'Area')
            ->setCellValue('B1', 'Item')
            ->setCellValue('C1', 'Code')
            ->setCellValue('D1', 'Finding')
            ->setCellValue('E1', 'Score');

        //fill color skublue
        $spreadsheet->getActiveSheet()->getStyle('A1:E1')
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('ffff00');
        $spreadsheet->getActiveSheet()->getStyle('A1:E1')->getFont()->setBold(true);


        $data = $this->audit_transaction->getFindingScore($year, $month, $week, $companyCode);

        $areas = array_map(function ($item) {
            return [
                'area_id' => $item['area_id'],
                'name' => $item['name']
            ];
        }, $data);

        // var_dump(array_unique($area,SORT_REGULAR));exit;\
        $areas = array_values(array_unique($areas, SORT_REGULAR));


        $startRowIdx = 2;
        foreach ($areas as $i => $row) {
            $items = array_filter($data, function ($var) use ($row) {
                return $var['area_id'] == $row['area_id'];
            });

            // var_dump($items);echo"\n\n";

            $items = array_map(function ($item) {
                return [
                    'mqaa_item_id' => $item['mqaa_item_id'],
                    'item_name' => $item['item'],
                    'trx_id' => $item['trx_id']
                ];
            }, $items);

            // var_dump($items);echo"\n\n";

            $items = array_values(array_unique($items, SORT_REGULAR));

            $areaId = $row['area_id'];
            $areaName = $row['name'];

            $firstItem = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(1, $startRowIdx)->getCoordinate();
            foreach ($items as $j => $row) {
                $findings = array_filter($data, function ($var) use ($areaId, $row) {
                    return $var['area_id'] == $areaId &&  $var['mqaa_item_id'] == $row['mqaa_item_id'];
                });
                // var_dump($findings);echo"\n\n";

                $findings = array_map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'g_order' => $item['g_order'],
                        'q_order' => $item['q_order'],
                        'description' => $item['description'],
                        // 'audit_trx_id'=>$item['audit_trx_id']
                    ];
                }, $findings);

                $itemName = $row['item_name'];
                $trxId = $row['trx_id'];
                $firstFinding = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(2, $startRowIdx)->getCoordinate();

                foreach ($findings as $k => $row) {

                    $spreadsheet->getActiveSheet()
                        ->setCellValueByColumnAndRow(1, $startRowIdx, $areaName)
                        ->setCellValueByColumnAndRow(2, $startRowIdx, $itemName);
                    if (!isset($trxId)) {
                        $spreadsheet->getActiveSheet()
                            ->setCellValueByColumnAndRow(3, $startRowIdx, '-')
                            ->setCellValueByColumnAndRow(4, $startRowIdx, 'NA')
                            ->setCellValueByColumnAndRow(5, $startRowIdx, 'NA');

                        //font green
                        $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(3, $startRowIdx)->getCoordinate();
                        $cordinateEnd = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(5, $startRowIdx)->getCoordinate();
                        $spreadsheet->getActiveSheet()->getStyle("$cordinate:$cordinateEnd")->getFont()
                            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKGREEN));
                    } else {
                        if (isset($row['description'])) {
                            $spreadsheet->getActiveSheet()
                                ->setCellValueByColumnAndRow(3, $startRowIdx, $row['g_order'] . "." . $row['q_order'])
                                ->setCellValueByColumnAndRow(4, $startRowIdx, $row['description'])
                                ->setCellValueByColumnAndRow(5, $startRowIdx, '1');
                        } else {
                            $spreadsheet->getActiveSheet()
                                ->setCellValueByColumnAndRow(3, $startRowIdx, '-')
                                ->setCellValueByColumnAndRow(4, $startRowIdx, 'NO FINDING')
                                ->setCellValueByColumnAndRow(5, $startRowIdx, '0');

                            //font green
                            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(3, $startRowIdx)->getCoordinate();
                            $cordinateEnd = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(5, $startRowIdx)->getCoordinate();
                            $spreadsheet->getActiveSheet()->getStyle("$cordinate:$cordinateEnd")->getFont()
                                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKGREEN));
                        }
                    }

                    $startRowIdx++;
                }


                $lastFinding = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(2, $startRowIdx - 1)->getCoordinate();

                // echo "$first:$last";

                $spreadsheet->getActiveSheet()
                    ->mergeCells("$firstFinding:$lastFinding");
            }
            $lastItem = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(1, $startRowIdx - 1)->getCoordinate();

            if ((int)filter_var($firstItem, FILTER_SANITIZE_NUMBER_INT) <= (int)filter_var($lastItem, FILTER_SANITIZE_NUMBER_INT)) {
                $spreadsheet->getActiveSheet()
                    ->mergeCells("$firstItem:$lastItem");
            }
        }
        // exit;
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(42);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(22);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(97);
        $spreadsheet->getActiveSheet()->getStyle("A1:E" . ($startRowIdx - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $spreadsheet->getActiveSheet()->getStyle("A1:A" . ($startRowIdx - 1))
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle("B1:B" . ($startRowIdx - 1))
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle("C1:C" . ($startRowIdx - 1))
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle("D1:D" . ($startRowIdx - 1))
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $spreadsheet->getActiveSheet()->getStyle("E1:E" . ($startRowIdx - 1))
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);



        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        ob_start();
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        $response =  array(
            'op' => 'ok',
            'file' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData),
            // 'image' => FCPATH . 'public/uploads/images/' . $newName, 'contoh' => __DIR__ . '/resources/logo_ubuntu_transparent.png', '__DIR__' => __DIR__
        );


        die(json_encode($response));
    }

    public function getBSCScores()
    {
        $year = $this->input->get('year') ? $this->input->get('year') : date('Y');
        $companyCode = $this->input->get('company_code');

        $buildings = $this->audit_transaction->getBuildingScore($year, $companyCode);

        // var_dump($buildings);exit;

        foreach ($buildings as $i => $row) {
            $buildings[$i]['lines'] = $this->audit_transaction->getLineScore($year, $row['building'], $companyCode);
            // var_dump($buildings[$i]['lines']);exit;

            foreach ($buildings[$i]['lines'] as $j => $row) {
                $buildings[$i]['lines'][$j]['teams'] = $this->audit_transaction->getTeamScore($year, $row['area_id'], $companyCode);
                // var_dump($buildings[$i]['lines'][$j]['teams']);exit;
            }
        }

        $data['year'] = $year;
        $data['data'] = $buildings;
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    public function exportBSCScores()
    {
        $year = $this->input->post('year') ? $this->input->post('year') : date('Y');
        $companyCode = $this->input->post('company_code');


        $helper = new Sample();
        if ($helper->isCli()) {
            $helper->log('This example should only be run from a Web Browser' . PHP_EOL);

            return;
        }
        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();

        $months = range(1, 12);

        foreach ($months as $i => $month) {
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($month + $i, 1)->getCoordinate();
            $cordinateSpan = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($month + $i + 1, 1)->getCoordinate();

            $spreadsheet->setActiveSheetIndex(0)
                ->mergeCells("$cordinate:$cordinateSpan");


            $spreadsheet->getActiveSheet()
                ->setCellValue($cordinate, date("F", mktime(0, 0, 0, $month, 10)));

            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($month + $i, 2, "Line\Item")
                ->setCellValueByColumnAndRow($month + $i + 1, 2, 'MQAA');
            $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('M')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('O')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('Q')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('S')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('U')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('W')->setWidth(22);
        }

        $spreadsheet->getActiveSheet()->getStyle('A1:X2')->getFont()->setBold(true);


        $buildings = $this->audit_transaction->getBuildingScore($year, $companyCode);

        $startBuildingRowIdx = 3;
        foreach ($buildings as $row) {
            foreach ($months as $j => $month) {
                $spreadsheet->getActiveSheet()
                    ->setCellValueByColumnAndRow($month + $j, $startBuildingRowIdx, $row['building'])
                    ->setCellValueByColumnAndRow($month + $j + 1, $startBuildingRowIdx, isset($row[strtolower(date("M", mktime(0, 0, 0, $month, 10)))]) ? round($row[strtolower(date("M", mktime(0, 0, 0, $month, 10)))], 0) : null);
            }

            //fill color orange
            $spreadsheet->getActiveSheet()->getStyle("A$startBuildingRowIdx:X$startBuildingRowIdx")
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFAB40');
            $spreadsheet->getActiveSheet()->getStyle("A$startBuildingRowIdx:X$startBuildingRowIdx")->getFont()->setBold(true);

            $lines = $this->audit_transaction->getLineScore($year, $row['building'], $companyCode);

            $startLineRowIdx = $startBuildingRowIdx + 1;
            foreach ($lines as $row) {
                foreach ($months as $k => $month) {
                    $spreadsheet->getActiveSheet()
                        ->setCellValueByColumnAndRow($month + $k, $startLineRowIdx, 'Assembly ' . $row['name'])
                        ->setCellValueByColumnAndRow($month + $k + 1, $startLineRowIdx, isset($row[strtolower(date("M", mktime(0, 0, 0, $month, 10)))]) ? round($row[strtolower(date("M", mktime(0, 0, 0, $month, 10)))], 0) : null);
                }

                //fill color skyblue
                $spreadsheet->getActiveSheet()->getStyle("A$startLineRowIdx:X$startLineRowIdx")
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('bbdefb');
                $spreadsheet->getActiveSheet()->getStyle("A$startLineRowIdx:X$startLineRowIdx")->getFont()->setBold(true);


                $teams = $this->audit_transaction->getTeamScore($year, $row['area_id'], $companyCode);

                $startTeamRowIdx = $startLineRowIdx + 1;
                $endTeamRowIdx = 0;
                foreach ($teams as $k => $row) {
                    foreach ($months as $l => $month) {
                        $spreadsheet->getActiveSheet()
                            ->setCellValueByColumnAndRow($month + $l, $startTeamRowIdx + $k, 'Assembly Line ' . $row['team'])
                            ->setCellValueByColumnAndRow($month + $l + 1, $startTeamRowIdx + $k, isset($row[strtolower(date("M", mktime(0, 0, 0, $month, 10)))]) ? round($row[strtolower(date("M", mktime(0, 0, 0, $month, 10)))], 0) : null);
                    }
                    $endTeamRowIdx = $startTeamRowIdx + $k;
                }
                $startLineRowIdx = $endTeamRowIdx + 1;
            }
            $startBuildingRowIdx = $startLineRowIdx;
        }

        $spreadsheet->getActiveSheet()->getStyle("A1:X" . ($startBuildingRowIdx - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);


        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        ob_start();
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        $response =  array(
            'op' => 'ok',
            'file' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData),
            // 'image' => FCPATH . 'public/uploads/images/' . $newName, 'contoh' => __DIR__ . '/resources/logo_ubuntu_transparent.png', '__DIR__' => __DIR__
        );


        die(json_encode($response));
    }

    public function getBSCItemScores()
    {
        $year = $this->input->get('year') ? $this->input->get('year') : date('Y');
        $companyCode = $this->input->get('company_code');

        $areas = $this->audit_transaction->getBSCAreaScore($year, $companyCode);
        // var_dump($areas);exit;
        foreach ($areas as $i => $row) {
            $areas[$i]['items'] = $this->audit_transaction->getBSCItemScore($year, $row['area_id'], $companyCode);
            // var_dump($areas[$i]['items']);exit;

        }

        $data['year'] = $year;
        $data['data'] = $areas;

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    public function exportBSCItemScores()
    {
        $year = $this->input->post('year') ? $this->input->post('year') : date('Y');
        $companyCode = $this->input->post('company_code');


        $helper = new Sample();
        if ($helper->isCli()) {
            $helper->log('This example should only be run from a Web Browser' . PHP_EOL);

            return;
        }
        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();

        $months = range(1, 12);

        foreach ($months as $i => $month) {
            $cordinate = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($month + $i, 1)->getCoordinate();
            $cordinateSpan = $spreadsheet->getActiveSheet()->getCellByColumnAndRow($month + $i + 1, 1)->getCoordinate();

            $spreadsheet->setActiveSheetIndex(0)
                ->mergeCells("$cordinate:$cordinateSpan");


            $spreadsheet->getActiveSheet()
                ->setCellValue($cordinate, date("F", mktime(0, 0, 0, $month, 10)));

            $spreadsheet->getActiveSheet()
                ->setCellValueByColumnAndRow($month + $i, 2, "Line\Item")
                ->setCellValueByColumnAndRow($month + $i + 1, 2, 'MQAA');
            $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('M')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('O')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('Q')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('S')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('U')->setWidth(22);
            $spreadsheet->getActiveSheet()->getColumnDimension('W')->setWidth(22);
        }

        $spreadsheet->getActiveSheet()->getStyle('A1:X2')->getFont()->setBold(true);


        $areas = $this->audit_transaction->getBSCAreaScore($year, $companyCode);

        $startBuildingRowIdx = 3;
        foreach ($areas as $row) {
            foreach ($months as $j => $month) {
                $spreadsheet->getActiveSheet()
                    ->setCellValueByColumnAndRow($month + $j, $startBuildingRowIdx, $row['area'])
                    ->setCellValueByColumnAndRow($month + $j + 1, $startBuildingRowIdx, isset($row[strtolower(date("M", mktime(0, 0, 0, $month, 10)))]) ? round($row[strtolower(date("M", mktime(0, 0, 0, $month, 10)))], 0) : null);
            }

            //fill color skyblue
            $spreadsheet->getActiveSheet()->getStyle("A$startBuildingRowIdx:X$startBuildingRowIdx")
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('bbdefb');
            $spreadsheet->getActiveSheet()->getStyle("A$startBuildingRowIdx:X$startBuildingRowIdx")->getFont()->setBold(true);


            $items = $this->audit_transaction->getBSCItemScore($year, $row['area_id'], $companyCode);

            $startLineRowIdx = $startBuildingRowIdx + 1;
            foreach ($items as $row) {
                foreach ($months as $k => $month) {
                    $spreadsheet->getActiveSheet()
                        ->setCellValueByColumnAndRow($month + $k, $startLineRowIdx, $row['item_name'])
                        ->setCellValueByColumnAndRow($month + $k + 1, $startLineRowIdx, isset($row[strtolower(date("M", mktime(0, 0, 0, $month, 10)))]) ? round($row[strtolower(date("M", mktime(0, 0, 0, $month, 10)))], 0) : null);
                }


                $startLineRowIdx++;
            }
            $startBuildingRowIdx = $startLineRowIdx;
        }

        $spreadsheet->getActiveSheet()->getStyle("A1:X" . ($startBuildingRowIdx - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);


        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        ob_start();
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        $response =  array(
            'op' => 'ok',
            'file' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData),
            // 'image' => FCPATH . 'public/uploads/images/' . $newName, 'contoh' => __DIR__ . '/resources/logo_ubuntu_transparent.png', '__DIR__' => __DIR__
        );


        die(json_encode($response));
    }

    public function getDashboardData()
    {
        $year = $this->input->get('year') ? $this->input->get('year') : date('Y');
        $month = $this->input->get('month') ? $this->input->get('month') : date('m');
        $week = $this->input->get('week') ? $this->input->get('week') : $this->custom->weekOfMonth(date('Y-m-d'));
        $companyCode = $this->input->get('company_code');

        $data['year'] = $year;
        $data['month'] = $month;
        $data['week'] = $week;
        $data['score_tot'] = $this->audit_transaction->getScoreSummaryTotal($year, $month, $companyCode);
        $data['score_tot']['prev_years_tot'] = $this->audit_transaction->getScoreYearlyTotal($year, $companyCode);
        $data['score_tot']['prev_months_tot'] = $this->audit_transaction->getScoreMonthlyTotal($year, $month, $companyCode);
        $data['score_tot']['weeks_tot'] = $this->audit_transaction->getScoreWeeklyTotal($year, $month, $week, $companyCode);

        $summary = $this->audit_transaction->getItemScoreSummary($year, $month, $week, ['notnull' => true, 'company_code' => $companyCode]);

        if ($summary) {
            $data['top_finding'] = $summary[count($summary) - 1];
            $data['top_finding']['findings'] = $this->finding->getFindings([
                't.mqaa_item_id' => $data['top_finding']['mqaa_item_id'],
                'period_year' => $year,
                'period_month' => $month,
                'week' => $week, 'f.company_code' => $companyCode
            ]);
        }


        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
