<?php
date_default_timezone_set("Asia/Jakarta");
class AuditTransaction extends CI_Model {

    private $_table ='audit_transaction';

    public function __construct()
        {
                // Memanggil konstruktor CI_Model
                parent::__construct();

                $this->load->library('Custom','custom');
        }

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
        //     try {
                $this->db->insert($this->_table, $data);
        
                // documentation at
                // https://www.codeigniter.com/userguide3/database/queries.html#handling-errors
                // says; "the error() method will return an array containing its code and message"
                $db_error = $this->db->error();
                if (!empty($db_error) && $db_error['code']!=0) {
                        $message='Database error!';
                        if($db_error['code']==1062){
                                $message='Already audited.';
                        }
                        header($_SERVER["SERVER_PROTOCOL"] . ' 500 Internal Server Error', true, 500);
                        echo json_encode([
                                'message'=>$message
                            ]);exit;
                        
                //     throw new Exception('Database error! Error Code [' . $db_error['code'] . '] Error: ' . $db_error['message']);
                    return false; // unreachable retrun statement !!!
                }
        //     } catch (Exception $e) {
        //         // this will not catch DB related errors. But it will include them, because this is more general. 
        //         // log_message('error: ',$e->getMessage());
        // //         $this->output
        // //     ->set_content_type('application/json')
        // //     ->set_status_header(401)
        // //     ->set_output(json_encode([
        // //         'message'=>'Invalid username or password.'
        // //     ]));
        // //     return;
        //         echo json_encode([
        //                 'message'=>'Invalid username or password.'
        //             ]);exit;
        //         return;
        //     }
            
            return $this->db->insert_id();
    }

    public function update($id,$data)
    {
            return $this->db->update($this->_table, $data, array('id' =>$id));
    }

    public function destroy($id)
    {
            $this->db->delete($this->_table, array('id' => $id));
    }

    public function checkIfExists($where){
        $query = $this->db->get_where($this->_table, $where);

        if ($query->num_rows() > 0) {
                return true;
        }
        return false;
    }

    public function checkIfNotCompletedYet($where){
        $query = $this->db
        ->where("exists(select * from audit_transaction where mqaa_item_id = $where[mqaa_item_id] and area_id = $where[area_id] and created_at < now() and completed_at is null)")
        ->get($this->_table);

        if ($query->num_rows() > 0) {
                return true;
        }
        return false;
    }

    public function isReviewed($id){
        $query = $this->db->where('id',$id)
        ->where('reviewed_at is not null')->get($this->_table);

        if ($query->num_rows() > 0) {
                return true;
        }
        return false;
    }
    public function isFollowedUp($id){
        $query = $this->db->where('id',$id)
        ->where('last_followed_up_at is not null')->get($this->_table);

        if ($query->num_rows() > 0) {
                return true;
        }
        return false;
    }
    public function isCompleted($id){
        $query = $this->db->where('id',$id)
        ->where('completed_at is not null')->get($this->_table);

        if ($query->num_rows() > 0) {
                return true;
        }
        return false;
    }

    
    //
    public function getRecords($filter=[]){
        $query = $this->db->select('trx.*, i.item_name mqaa_item, a.name area, auditor.name auditor, vss.name vss, case when reviewed_at is null then reviewed_at else 0 end `order`',false)
        ->from("$this->_table trx")
        ->join('mqaa_item i',"i.id = trx.mqaa_item_id",'left')
        ->join('area a',"a.id = trx.area_id",'left')
        ->join('user auditor',"auditor.id = trx.auditor_id",'left')
        ->join('user vss',"vss.id = trx.vss_id",'left');

        // if(isset($filter['status'])){
        //         if($filter['status']=="R"){
        //                 $query->where('reviewed_at is not null');
        //         }else if($filter['status']=="U"){
        //                 $query->where('reviewed_at is null');
        //         }
        // }
        if(isset($filter['status'])){
                if($filter['status']=="U"){
                        $query->where("reviewed_at is null ")
                        ->where("last_followed_up_at is null ")
                        ->where("completed_at is null ");
                }else if($filter['status']=="R"){
                        $query->where("reviewed_at is not null ")
                        ->where("last_followed_up_at is null ")
                        ->where("completed_at is null ");
                }else if($filter['status']=="C"){
                        $query->where("completed_at is not null ");
                }
                
        }
        if(isset($filter['mqaa_item_id'])){
                $query->where('mqaa_item_id',$filter['mqaa_item_id']);
        }
        if(isset($filter['area_id'])){
                $query->where('area_id',$filter['area_id']);
        }
        if(isset($filter['period'])){
                $year = DateTime::createFromFormat('Y-m', $filter['period'])->format('Y');
                $month = DateTime::createFromFormat('Y-m', $filter['period'])->format('m');

                $query->where('period_year',$year)
                ->where('lpad(period_month,2,0)',$month);
        }
        if(isset($filter['vss_id'])){
                $query->where('a.vss_id',$filter['vss_id']);
        }
        return $query->order_by('`order`, trx.created_at desc')->get()->result_array();
    }  

    public function getRecord($id){
        $query = $this->db->select('trx.*, i.item_name mqaa_item, a.name area, auditor.name auditor, vss.name vss, a.manager_email, a.manager_name, pic.name pic')
        ->from("$this->_table trx")
        ->join('mqaa_item i',"i.id = trx.mqaa_item_id",'left')
        ->join('area a',"a.id = trx.area_id",'left')
        ->join('user auditor',"auditor.id = trx.auditor_id",'left')
        ->join('user vss',"vss.id = trx.vss_id",'left')
        ->join('user pic',"pic.id = a.vss_id",'left')
        ->where('trx.id',$id)->get();

        return $query->row_array();
    }  

    public function getDatabases($filter = []){
        $query=$this->db->select('*')
        ->from('audit_database_v db')
        ->where('company_code',$filter['company_code']);

        if(isset($filter['mqaa_item_id'])){
                $query->where('mqaa_item_id',$filter['mqaa_item_id']);
        }
        if(isset($filter['area_id'])){
                $query->where('area_id',$filter['area_id']);
        }
        if(isset($filter['period'])){
                $year = DateTime::createFromFormat('Y-m', $filter['period'])->format('Y');
                $month = DateTime::createFromFormat('Y-m', $filter['period'])->format('m');

                $query->where('year', $year)
                ->where('month', $month);
        }
        if(isset($filter['week'])){
                $query->where('week',$filter['week']);
        }
        
        $query->order_by('created_at','desc');
        return $query->get()->result_array();
    }

    public function getDatabases2($filter = []){
        $limit=isset($filter['limit'])?$filter['limit']:10;
        
        $where="and company_code = '$filter[company_code]'";
        array_walk($filter, function ($v, $k)use(&$where) {
                if(in_array($k,['item','area','building','nik','leader','name'])){
                        // $where[$k]=$v;
                        $V=strtoupper($v);
                        $where.="and upper($k) like '%$V%' ";
                }
        });
        array_walk($filter, function ($v, $k)use(&$where) {
                if(in_array($k,['year','date','month','week'])){
                        $where.="and $k = $v ";
                }
        });

        // $like = [];
        // if(isset($filter['code'])){
        // $like['code']=$filter['code'];
        // }
        // if(isset($filter['finding'])){
        // $like['finding']=$filter['finding'];
        // }

        //     var_dump($where);exit;


        $PAGE_SHOW=5;

        $currPage=null;
        $current10=null;
        $next5Ids=[];
        $nextId=null;
        $prev5Ids=[];
        $prevId=null;
        if(isset($filter['id'])&&isset($filter['direction'])&&isset($filter['page'])){
            if($filter['direction']==1){
                $currPage=$filter['page'];
                
                $query = $this->db->query("select * from audit_database_v where trx_id <= $filter[id] $where order by trx_id desc limit $limit");
                $current10 = $query->result_array();

                foreach($current10 as $i=>$row){
                    $current10[$i]['findings']=$this->finding->getFindings(['f.audit_trx_id'=>$row['trx_id']]);
                }

                $nextOffset=$limit;
                $nextLimit=$limit*($PAGE_SHOW-1)+1;
                $query = $this->db->query("select trx_id id from audit_database_v where trx_id <= $filter[id] $where order by trx_id desc limit $nextOffset,$nextLimit");
                $next41 = $query->result_array();

                $nextPage=$currPage+1;
                array_walk($next41, function ($v, $k) use (&$next5Ids,$limit,&$nextPage) {
                    if($k%$limit==0){
                        $v['direction'] = 1;
                        $v['page'] = $nextPage;
                        $v['limit'] = $limit;
                        array_push($next5Ids,$v);

                        $nextPage++;
                    }
                });
                $nextId = count($next5Ids)>0?$next5Ids[0]:null;


                $prevOffset = $limit;
                $prevLimit = $limit*$PAGE_SHOW+1;
                $query = $this->db->query("select trx_id id from audit_database_v where trx_id > $filter[id] $where order by trx_id asc limit $prevOffset,$prevLimit");
                $prev51 = $query->result_array();

                $prevPage=$currPage-1;
                array_walk($prev51, function ($v, $k) use (&$prev5Ids,$limit,&$prevPage) {
                    if($k%$limit==0){
                        $v['direction'] = -1;
                        $v['page'] = $prevPage;
                        $v['limit'] = $limit;
                        array_unshift($prev5Ids,$v);

                        $prevPage--;
                    }
                });
                $prevId = count($prev5Ids)>0?$prev5Ids[count($prev5Ids)-1]:null;
            }elseif($filter['direction']==-1){
                $currPage=$filter['page'];

                $query = $this->db->query("select * from audit_database_v where trx_id < $filter[id] $where order by trx_id desc limit $limit");
                $current10 = $query->result_array();

                foreach($current10 as $i=>$row){
                    $current10[$i]['findings']=$this->finding->getFindings(['f.audit_trx_id'=>$row['trx_id']]);
                }

                $nextOffset=$limit;
                $nextLimit=$limit*($PAGE_SHOW-1)+1;
                $query = $this->db->query("select trx_id id from audit_database_v where trx_id < $filter[id] $where order by trx_id desc limit $nextOffset,$nextLimit");
                $next41 = $query->result_array();

                $nextPage=$currPage+1;
                array_walk($next41, function ($v, $k) use (&$next5Ids,$limit,&$nextPage) {
                    if($k%$limit==0){
                        $v['direction'] = 1;
                        $v['page'] = $nextPage;
                        $v['limit'] = $limit;
                        array_push($next5Ids,$v);

                        $nextPage++;
                    }
                });
                $nextId = count($next5Ids)>0?$next5Ids[0]:null;


                $prevOffset = $limit;
                $prevLimit = $limit*$PAGE_SHOW+1;
                $query = $this->db->query("select trx_id id from audit_database_v where trx_id >= $filter[id] $where order by trx_id asc limit $prevOffset,$prevLimit");
                $prev51 = $query->result_array();

                $prevPage=$currPage-1;
                array_walk($prev51, function ($v, $k) use (&$prev5Ids,$limit,&$prevPage) {
                    if($k%$limit==0){
                        $v['direction'] = -1;
                        $v['page'] = $prevPage;
                        $v['limit'] = $limit;
                        array_unshift($prev5Ids,$v);

                        $prevPage--;
                    }
                });
                $prevId = count($prev5Ids)>0?$prev5Ids[count($prev5Ids)-1]:null; 
            }
        }else if(isset($filter['id'])&&isset($filter['direction'])){
            if($filter['direction']==1){
                $query = $this->db->query("select * from audit_database_v where trx_id <= $filter[id] $where order by trx_id desc limit $limit");
                $current10 = $query->result_array();

                foreach($current10 as $i=>$row){
                    $current10[$i]['findings']=$this->finding->getFindings(['f.audit_trx_id'=>$row['trx_id']]);
                }

                $nextOffset=$limit;
                $nextLimit=$limit+1;
                $query = $this->db->query("select trx_id id from audit_database_v where trx_id <= $filter[id] $where order by trx_id desc limit $nextOffset,$nextLimit");
                $next41 = $query->result_array();

                $next1Ids=[];
                array_walk($next41, function ($v, $k) use (&$next1Ids,$limit) {
                    if($k%$limit==0){
                        $v['direction'] = 1;
                        $v['limit'] = $limit;
                        array_push($next1Ids,$v);
                    }
                });
                $nextId = count($next1Ids)>0?$next1Ids[0]:null;

                $prevOffset = $limit;
                $prevLimit = $limit+1;
                $query = $this->db->query("select trx_id id from audit_database_v where trx_id > $filter[id] $where order by trx_id asc limit $prevOffset,$prevLimit");
                $prev51 = $query->result_array();

                $prev1Ids=[];
                array_walk($prev51, function ($v, $k) use (&$prev1Ids,$limit) {
                    if($k%$limit==0){
                        $v['direction'] = -1;
                        $v['limit'] = $limit;
                        array_unshift($prev1Ids,$v);
                    }
                });
                $prevId = count($prev1Ids)>0?$prev1Ids[count($prev1Ids)-1]:null;
            }elseif($filter['direction']==-1){
                $query = $this->db->query("select * from audit_database_v where trx_id < $filter[id] $where order by trx_id desc limit $limit");
                $current10 = $query->result_array();

                foreach($current10 as $i=>$row){
                    $current10[$i]['findings']=$this->finding->getFindings(['f.audit_trx_id'=>$row['trx_id']]);
                }

                $nextOffset=$limit;
                $nextLimit=$limit+1;
                $query = $this->db->query("select trx_id id from audit_database_v where trx_id < $filter[id] $where order by trx_id desc limit $nextOffset,$nextLimit");
                $next41 = $query->result_array();

                $next1Ids=[];
                array_walk($next41, function ($v, $k) use (&$next1Ids,$limit) {
                    if($k%$limit==0){
                        $v['direction'] = 1;
                        $v['limit'] = $limit;
                        array_push($next1Ids,$v);
                    }
                });
                $nextId = count($next1Ids)>0?$next1Ids[0]:null;


                $prevOffset = $limit;
                $prevLimit = $limit+1;
                $query = $this->db->query("select trx_id id from audit_database_v where trx_id >= $filter[id] $where order by trx_id asc limit $prevOffset,$prevLimit");
                $prev51 = $query->result_array();

                $prev1Ids=[];
                array_walk($prev51, function ($v, $k) use (&$prev1Ids,$limit) {
                    if($k%$limit==0){
                        $v['direction'] = -1;
                        $v['limit'] = $limit;
                        array_unshift($prev1Ids,$v);
                    }
                });
                $prevId = count($prev1Ids)>0?$prev1Ids[count($prev1Ids)-1]:null; 
            }
        }else if(isset($filter['id']) && $filter['id']=='LAST'){
            $currPage='LAST';
            $query = $this->db->query("select * from(select * from audit_database_v order by trx_id ASC limit $limit)s order by trx_id desc");
            $current10 = $query->result_array();

            foreach($current10 as $i=>$row){
                $current10[$i]['findings']=$this->finding->getFindings(['f.audit_trx_id'=>$row['trx_id']]);
            }

            $prevOffset = $limit*2;
            $prevLimit = $limit+1;
            $query = $this->db->query("select trx_id id from audit_database_v order by trx_id asc limit $prevOffset,$prevLimit");
            $prev11 = $query->result_array();

            $prev1Ids=[];
            array_walk($prev11, function ($v, $k) use (&$prev1Ids,$limit) {
                if($k%$limit==0){
                    $v['direction'] = -1;
                    $v['limit'] = $limit;
                    array_unshift($prev1Ids,$v);
                }
            });
            $prevId = count($prev1Ids)>0?$prev1Ids[count($prev1Ids)-1]:null;
        }else{
            $currPage='FIRST';
        //     echo "select * from audit_database_v where 1=1 $where order by trx_id desc limit $limit";exit;
            $query = $this->db->query("select * from audit_database_v where 1=1 $where order by trx_id desc limit $limit");
            $current10 = $query->result_array();

        //     var_dump($like);exit;
            foreach($current10 as $i=>$row){
            $current10[$i]['findings']=$this->finding->getFindings(['f.audit_trx_id'=>$row['trx_id']]);
            }

            $offset=$limit;
            $nextLimit=$limit*($PAGE_SHOW-1)+1;
            $query = $this->db->query("select trx_id id from audit_database_v where 1=1 $where order by trx_id desc limit $offset,$nextLimit");
            $next41 = $query->result_array();

            $nextPage=2;
            array_walk($next41, function ($v, $k) use (&$next5Ids,$limit,&$nextPage) {
                if($k%$limit==0){
                    $v['direction'] = 1;
                    $v['page'] = $nextPage;
                    $v['limit'] = $limit;
                    array_push($next5Ids,$v);

                    $nextPage++;
                }
            });
            $nextId = count($next5Ids)>0?$next5Ids[0]:null;
        }

        return [
                'currPage'=>$currPage,
                'current10'=>$current10,
                // 'next41'=>$next41,
                'next5Ids'=>$next5Ids,
                'prev5Ids'=>$prev5Ids,
                'nextId'=>$nextId,
                'prevId'=>$prevId,
                'where'=>$where
        ];
    }

    public function getRecords2($filter = []){
        $limit=isset($filter['limit'])?$filter['limit']:10;
        $direction=isset($filter['direction'])?$filter['direction']:1;

        $currPage=null;
        $current10=null;
        $next5Ids=[];
        $nextId=null;
        $prev5Ids=[];
        $prevId=null;

        if(isset($filter['id'])&&isset($direction)){
            if($direction==1){
                $sqlText = "select trx.*, i.item_name mqaa_item, a.name area, auditor.name auditor, vss.name vss
                from audit_transaction trx
                left join mqaa_item i on i.id = trx.mqaa_item_id 
                left join area a on a.id = trx.area_id
                left join user auditor on auditor.id = trx.auditor_id
                left join user vss on vss.id = trx.vss_id
                /*left join audit_completion_status_v c on c.audit_trx_id = trx.id*/
                where trx.id <= $filter[id] 
                and trx.company_code = '$filter[company_code]' ";
                if(isset($filter['status'])){
                        if($filter['status']=="U"){
                                $sqlText .="and trx.reviewed_at is null ";
                                $sqlText .="and trx.last_followed_up_at is null ";
                                $sqlText .="and trx.completed_at is null ";
                        }else if($filter['status']=="R"){
                                $sqlText .="and trx.reviewed_at is not null ";
                                $sqlText .="and trx.last_followed_up_at is null ";
                                $sqlText .="and trx.completed_at is null ";
                        }else if($filter['status']=="C"){
                                $sqlText .="and trx.completed_at is not null ";
                        }
                        
                }
                if(isset($filter['mqaa_item_id'])){
                        $sqlText .="and mqaa_item_id = $filter[mqaa_item_id] ";
                }
                if(isset($filter['area_id'])){
                        $sqlText .="and area_id = $filter[area_id] ";
                }
                if(isset($filter['period'])){
                        $year = DateTime::createFromFormat('Y-m', $filter['period'])->format('Y');
                        $month = DateTime::createFromFormat('Y-m', $filter['period'])->format('m');
        
                        $sqlText .="and period_year = '$year'
                        and lpad(period_month,2,0) = '$month' ";
                }
                if(isset($filter['vss_id'])){
                        $sqlText .="and a.vss_id = $filter[vss_id] ";
                }
                if(isset($filter['auditor_id'])){
                        $sqlText .="and trx.auditor_id = $filter[auditor_id] ";
                }
                $limit11=$limit+1;
                $sqlText.="order by trx.id desc limit $limit11";
                // echo $sqlText;
                $query = $this->db->query($sqlText);
                $current11 = $query->result_array();
                $current10 = array_slice($current11,0,$limit);
                if(count($current11)==$limit11){
                        $nextId = $current11[count($current11)-1];  
                }else{
                        $nextId=null;
                }
            }
        }else{
                $sqlText = "select trx.*, i.item_name mqaa_item, a.name area, auditor.name auditor, vss.name vss
                from audit_transaction trx
                left join mqaa_item i on i.id = trx.mqaa_item_id 
                left join area a on a.id = trx.area_id
                left join user auditor on auditor.id = trx.auditor_id
                left join user vss on vss.id = trx.vss_id
                /*left join audit_completion_status_v c on c.audit_trx_id = trx.id*/
                where 1=1 
                and trx.company_code = '$filter[company_code]' ";
                if(isset($filter['status'])){
                        if($filter['status']=="U"){
                                $sqlText .="and trx.reviewed_at is null ";
                                $sqlText .="and trx.last_followed_up_at is null ";
                                $sqlText .="and trx.completed_at is null ";
                        }else if($filter['status']=="R"){
                                $sqlText .="and trx.reviewed_at is not null ";
                                $sqlText .="and trx.last_followed_up_at is null ";
                                $sqlText .="and trx.completed_at is null ";
                        }else if($filter['status']=="C"){
                                $sqlText .="and trx.completed_at is not null ";
                        }
                }
                if(isset($filter['mqaa_item_id'])){
                        $sqlText .="and mqaa_item_id = $filter[mqaa_item_id] ";
                }
                if(isset($filter['area_id'])){
                        $sqlText .="and area_id = $filter[area_id] ";
                }
                if(isset($filter['period'])){
                        $year = DateTime::createFromFormat('Y-m', $filter['period'])->format('Y');
                        $month = DateTime::createFromFormat('Y-m', $filter['period'])->format('m');
        
                        $sqlText .="and period_year = '$year'
                        and lpad(period_month,2,0) = '$month' ";
                }
                if(isset($filter['vss_id'])){
                        $sqlText .="and a.vss_id = $filter[vss_id] ";
                }
                if(isset($filter['auditor_id'])){
                        $sqlText .="and trx.auditor_id = $filter[auditor_id] ";
                }
                $limit11=$limit+1;
                $sqlText.="order by trx.id desc limit $limit11";
                // echo $sqlText;
                $query = $this->db->query($sqlText);
                $current11 = $query->result_array();
                $current10 = array_slice($current11,0,$limit);
                if(count($current11)==$limit11){
                        $nextId = $current11[count($current11)-1];  
                }else{
                        $nextId=null;
                }
        }

        return [
                'current10'=>$current10,
                'nextId'=>$nextId
        ];
    }

    public function getItemScoreWeekly($id,$year,$month,$week){
        $weeks=range(1,$week);
        $weeks=array_map(function($item){return 'w'.$item;},$weeks);
        $strWeeks=implode(',',$weeks);

        $query = $this->db->select($strWeeks)
        ->from("(select @p_year:=$year year,@p_month:=$month month) params, (select @p_week:=$week week)params2, item_score_weekly_v")
        ->where('mqaa_item_id',$id)
        ->get();
        return $query !== false?$query->row_array():[];
    }
    public function getItemScoreMonthly($id,$year,$month,$week){
        $months=range(1,$month-1);
        $months=array_map(function($item){return 'm'.$item;},$months);
        $strMonths=implode(',',$months);

        $query = $this->db->select($strMonths)
        ->from("(select @p_year:=$year year,@p_month:=$month month) params,(select @p_week:=$week week) params2, item_score_monthly_v")
        ->where('mqaa_item_id',$id)
        ->get();
        
        return $query !== false?$query->row_array():[];
}
public function getItemScoreYearly($id,$year){
        $years=range($year-4,$year-1);

        $query = $this->db->select("
        , SUM(case when YEAR=$years[0] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[0] then possible ELSE 0 END)y$years[0]
        , SUM(case when year=$years[1] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[1] then possible ELSE 0 END)y$years[1]
        , SUM(case when year=$years[2] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[2] then possible ELSE 0 END)y$years[2]
        , SUM(case when year=$years[3] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[3] then possible ELSE 0 END)y$years[3]
        ")
        ->from('audit_database_v')
        ->where('mqaa_item_id',$id)
        ->group_by(array("mqaa_item_id", "item"))
        ->get();
        
        return $query->row_array();
}
public function getItemScoreSummary($year,$month,$pweek,$filter=[]){
        $week = 'w'.$pweek;
        $query = $this->db
        ->from("(select @p_year:=$year `year`,@p_month:=$month `month`) params, (select @p_week:=$pweek week)params2, item_score_summary_v v")
        ->select('v.*')
        ->where('company_code',$filter['company_code']);
        

        if(isset($filter['notnull'])){
                $query->where("$week is not null");
        }

        $query->order_by("$week desc, avg_weeks desc, avg_months desc, mqaa_item_id");

        // var_dump($query);exit;

        return $query->get()->result_array();
}


//
public function getItemScore($year,$month,$week,$companyCode,$filter=[]){
        $years=range($year-4,$year-1);
        $years=array_map(function($item)use($year){

                return 'ymin'.($year-$item).' y'.$item;
        },$years);
        $strYears=implode(',',$years);

        // echo $strYears;

        $strMonths="";
        if($month>1){
                $months=range(1,$month-1);
                $months=array_map(function($item){return 'm'.$item;},$months);
                $strMonths=implode(',',$months);
        }

        // echo $strMonths;

        $weeks=range(1,$week);
        $weeks=array_map(function($item){return 'w'.$item;},$weeks);
        $strWeeks=implode(',',$weeks);

        // echo $strWeeks;exit;

        // echo "$strYears,$strMonths,$strWeeks";
        $query = $this->db->select("mqaa_item_id, item, avg_months, avg_weeks, $strYears,$strMonths,$strWeeks")
        ->from("(select @p_year:=$year year,@p_month:=$month month) params, (select @p_week:=$week week)params2, item_score_v")
        ->where('company_code',$companyCode);

        if(isset($filter['notnull'])){
                $query->where("$week is not null");
        }
        
        $query->order_by("w$week desc, avg_weeks desc, avg_months desc, mqaa_item_id");
        return $query->get()->result_array();
}
public function getAreaScore($year,$month,$week,$companyCode,$filter=[]){
        $years=range($year-4,$year-1);
        $years=array_map(function($item)use($year){

                return 'ymin'.($year-$item).' y'.$item;
        },$years);
        $strYears=implode(',',$years);

        // echo $strYears;

        $strMonths="";
        if($month>1){
                $months=range(1,$month-1);
                $months=array_map(function($item){return 'm'.$item;},$months);
                $strMonths=implode(',',$months);
        }

        // echo $strMonths;

        $weeks=range(1,$week);
        $weeks=array_map(function($item){return 'w'.$item;},$weeks);
        $strWeeks=implode(',',$weeks);

        // echo $strWeeks;exit;

        // echo "$strYears,$strMonths,$strWeeks";
        $query = $this->db->select("area_id, area, avg_months, avg_weeks, $strYears,$strMonths,$strWeeks")
        ->from("(select @p_year:=$year year,@p_month:=$month month) params, (select @p_week:=$week week)params2, area_score_v")
        ->where('company_code',$companyCode);

        if(isset($filter['notnull'])){
                $query->where("$week is not null");
        }
        
        $query->order_by("w$week desc, avg_weeks desc, avg_months desc, area_id");
        return $query->get()->result_array();
}
public function getScoreTot($year,$month,$week,$companyCode){
        $years=range($year-4,$year-1);
        $years=array_map(function($item)use($year){

                return 'ymin'.($year-$item).' y'.$item;
        },$years);
        $strYears=implode(',',$years);

        // echo $strYears;
        $strMonths="";
        if($month>1){
                $months=range(1,$month-1);
                $months=array_map(function($item){return 'm'.$item;},$months);
                $strMonths=implode(',',$months);
        }
        

        // echo $strMonths;

        $weeks=range(1,$week);
        $weeks=array_map(function($item){return 'w'.$item;},$weeks);
        $strWeeks=implode(',',$weeks);

        // echo "$strYears,$strMonths,$strWeeks";
        $query = $this->db->select("avg_months_tot, avg_weeks_tot, $strYears,$strMonths,$strWeeks")
        ->from("(select @p_year:=$year year,@p_month:=$month month) params, (select @p_week:=$week week)params2, score_total_v")
        ->where('company_code',$companyCode)
        ->get();
        return $query->row_array();
}
public function getFindingScore($year,$month,$week,$companyCode){
        $query = $this->db->select("v.*")
        ->from("(select @p_year:=$year year,@p_month:=$month month) params, (select @p_week:=$week week)params2, finding_score_v v")
        ->where('company_code',$companyCode)
        ->get();

        return $query->result_array();
}
public function getAreaScoreWeekly($id,$year,$month,$week){
        $weeks=range(1,$week);
        $weeks=array_map(function($item){return 'w'.$item;},$weeks);
        $strWeeks=implode(',',$weeks);

        $query = $this->db->select($strWeeks)
        ->from("(select @p_year:=$year year,@p_month:=$month month) params, (select @p_week:=$week week)params2, area_score_weekly_v")
        ->where('area_id',$id)
        ->get();
        return $query->row_array();
    }
    public function getAreaScoreMonthly($id,$year,$month,$week){
       $months=range(1,$month-1);
        $months=array_map(function($item){return 'm'.$item;},$months);
        $strMonths=implode(',',$months);

        $query = $this->db->select($strMonths)
        ->from("(select @p_year:=$year year,@p_month:=$month month) params,(select @p_week:=$week week) params2, area_score_monthly_v")
        ->where('area_id',$id)
        ->get();

       return $query !== false?$query->row_array():[];
}
public function getAreaScoreYearly($id,$year){
        $years=range($year-4,$year-1);

        $query = $this->db->select("
        , SUM(case when YEAR=$years[0] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[0] then possible ELSE 0 END)y$years[0]
        , SUM(case when year=$years[1] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[1] then possible ELSE 0 END)y$years[1]
        , SUM(case when year=$years[2] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[2] then possible ELSE 0 END)y$years[2]
        , SUM(case when year=$years[3] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[3] then possible ELSE 0 END)y$years[3]
        ")
        ->from('audit_database_v')
        ->where('area_id',$id)
        ->group_by(array("area_id", "area"))
        ->get();
        
        return $query->row_array();
}
public function getAreaScoreSummary($year,$month,$pweek,$filter=[]){
        $week = 'w'.$pweek;
        $query = $this->db
        ->from("(select @p_year:=$year `year`,@p_month:=$month `month`) params, (select @p_week:=$pweek week)params2, area_score_summary_v v")
        ->select('v.*');
        
        if(isset($filter['notnull'])){
                $query->where("$week is not null");
        }
        
        $query->order_by("$week desc, avg_weeks desc, avg_months desc, area_id");
        
        
        return $query->get()->result_array();
}


public function getScoreWeeklyTotal($year,$month,$week,$companyCode){
        $weeks=range(1,$week);
        $weeks=array_map(function($item){return 'w'.$item;},$weeks);
        $strWeeks=implode(',',$weeks);


        $query = $this->db->select($strWeeks)
        ->from("(select @p_year:=$year year,@p_month:=$month month) params,(select @p_week:=$year week) params2, score_weekly_total_v")
        ->where('company_code',$companyCode)
        ->get();
        return $query->row_array();
    }
    public function getScoreMonthlyTotal($year,$month,$companyCode){
        $months=range(1,$month-1);
        $months=array_map(function($item){return 'm'.$item;},$months);
        $strMonths=implode(',',$months);

        $query = $this->db->select($strMonths)
        ->from("(select @p_year:=$year year) params, score_monthly_total_v")
        ->where('company_code',$companyCode)
        ->get();

        return $query !== false?$query->row_array():[];
}
public function getScoreYearlyTotal($year,$companyCode){
        $years=range($year-4,$year-1);

        $query = $this->db->select("
        , SUM(case when YEAR=$years[0] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[0] then possible ELSE 0 END)y$years[0]
        , SUM(case when year=$years[1] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[1] then possible ELSE 0 END)y$years[1]
        , SUM(case when year=$years[2] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[2] then possible ELSE 0 END)y$years[2]
        , SUM(case when year=$years[3] then `enable` ELSE 0 END)*100/SUM(case when YEAR=$years[3] then possible ELSE 0 END)y$years[3]
        ")
        ->from('audit_database_v')
        ->where('company_code',$companyCode)
        ->get();
        
        return $query->row_array();
}
public function getScoreSummaryTotal($year,$month,$companyCode){
        $query = $this->db
        ->from("(select @p_year:=$year year,@p_month:=$month month) params, score_summary_total_v v")
        ->select('v.*')
        ->where('v.company_code',$companyCode);
        return $query->get()->row_array();
}




public function getAreaItemTrxMapping($areaId, $mqaaItemId, $year, $month, $week){
        $query = $this->db->from("(select @p_year:=$year year,@p_month:=$month month) params, (select @p_week:=$week week) params2, area_item_trx_mapping_v v")
        ->where(['area_id'=>$areaId,'mqaa_item_id'=>$mqaaItemId]);
        return $query->get()->row_array();
}

public function getBuildingScore($year,$companyCode){
        // echo $year;exit;
        $query = $this->db->from("(select @p_year:=$year year) params, bsc_building_score_v v")
        ->where('v.company_code',$companyCode);

        return $query->get()->result_array();
}

public function getLineScore($year,$building,$companyCode){
        $query = $this->db->from("(select @p_year:=$year year, @p_building:='$building' building) params, bsc_line_score_v v")
        ->where('v.company_code',$companyCode);
        return $query->get()->result_array();
}

public function getTeamScore($year,$areaId,$companyCode){
        $query = $this->db->from("(select @p_year:=$year year, @p_area_id:=$areaId area_id) params, bsc_team_score_v v")
        ->where('v.company_code',$companyCode);
        return $query->get()->result_array();
}

public function getBSCAreaScore($year,$companyCode){
        $query = $this->db->from("(select @p_year:=$year year) params, bsc_area_score_v v")
        ->where('v.company_code',$companyCode);
        return $query->get()->result_array();
}

public function getBSCItemScore($year,$areaId,$companyCode){
        $query = $this->db->from("(select @p_year:=$year year, @p_area_id:=$areaId area_id) params, bsc_item_score_v v")
        ->where('v.company_code',$companyCode);
        return $query->get()->result_array();
}

}