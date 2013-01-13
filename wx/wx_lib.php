<?php
/**
* 微信公众平台
*/
class wechatCallbackapiTest
{
    public $token;
    public $limit;
    /*
    * @ $token
    * @ $limit 显示条数
    */
    function __construct($token,$limit=5)
    {
        $this->token = $token; 
        $this->limit = $limit;
        
        $host       = 'host';
        $user       = 'user';
        $password   = 'password';
        $dbname     = 'dbname';
        $con = mysql_connect($host,$user ,$password);
        if (!$con)
        {
            die('Could not connect: ' . mysql_error());
        }         
        mysql_select_db($dbname, $con);//选择数据库
        mysql_query('set names utf8');       
    }

	public function valid()
    {
        if($this->checkSignature()){
            return $this->responseMsg();
        }
    }
    //签名规则算法    
    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce     = $_GET["nonce"];   
                
        $token  = $this->token;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
    //响应请求
    public function responseMsg()
    {
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

		if (!empty($postStr))
        {                
          	$postObj        = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $fromUsername   = $postObj->FromUserName;
            $toUsername     = $postObj->ToUserName;
            $keyword        = strtolower(trim($postObj->Content));
            $time           = time();
            $msgType        = "text";

            $textTpl        = "<xml>
        						<ToUserName><![CDATA[%s]]></ToUserName>
        						<FromUserName><![CDATA[%s]]></FromUserName>
        						<CreateTime>%s</CreateTime>
        						<MsgType><![CDATA[%s]]></MsgType>
        						<Content><![CDATA[%s]]></Content>
        						<FuncFlag>0</FuncFlag>
        						</xml>";

			if(!empty( $keyword ))
            {
            	$contentStr = $this->contentStr($fromUsername,$keyword);
            	$resultStr  = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
            	echo $resultStr;
            }else{
                $contentStr = $this->contentStr($fromUsername,'0');
            	$resultStr  = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                echo $resultStr;
            }

        }else {
        	echo "Input something";
        	exit;
        }
    }

    //通过输入一个唯一值来定位某一个表中的唯一条记录的某一个值
    private function get_a_value($table, $key, $condition)
    {
        $result = NULL;
        $query  = $this->get_a_record($table, array($key), $condition);
        if ($query) $result = $query[$key];
        return $result;
    }

    //获取一张表的一条记录    @$field array
    private function get_a_record($table, $field, $condition,$forcount = FALSE)
    {
        if(is_array($field))
        {
            $field = implode($field, ',');              
        }
        else
        {
            $field = $field;
        }
        
        $query = $this->select_rows($table, $field, $condition,$forcount);
        return (mysql_num_rows($query)) ? mysql_fetch_array($query) : NULL;
    }
        
    //从数据库中读取一条或多条数据的标准方法
    private function select_rows($table, $field, $condition, $forcount = FALSE)
    {   
        if (is_array($condition))
        {   
            $str = '';
            foreach ($condition as $t => $values)
            {                    
                    $str .= "$t ='$values' AND ";                   
            } 
            $condition = substr($str,0,-4);             
        }
        else
        {
            $condition = $condition;
        }

        $sql = "SELECT $field FROM $table WHERE $condition "; 

        if ($forcount == TRUE)
        {
            $sql = "SELECT COUNT(*) FROM $table WHERE $condition";
        }       
        $result = mysql_query($sql);
            
        return $result;
    }

    //向数据库里插入一条记录的方法，返回被插入数据的ID
    private function create_a_record($table, $data)
    {   
        $key    = array();
        $value  = array();

        foreach ($data as $t => $values)
        {                    
            $key[]      = $t; 
            $value[]    = "'$values'";         
        }

        $key    = implode(',', $key);
        $value  = implode(',', $value);
        $sql    = "INSERT INTO $table ($key) VALUES ($value)";
        $result = mysql_query($sql);
        $id     = mysql_insert_id();
        return $id ;
    }

    //指令分析 private
    public  function contentStr($fromUsername,$keyword)
    {   
        //指令规则: '+数字'：进入下一级 '-':进入上一级 0：清空返回根目录 
        if (preg_match("/^#(\d+)$/is",$keyword,$id)) 
        {            
            $id = (int)$id[1];
            //查找最后一次输入的指令
            $condition  = "name='{$fromUsername}' order by id desc limit 1";
            $last_id    = $this->get_a_value('username', 'cat_id', $condition);

            if (! $last_id) {
                //查询顶层数据
                $condition  = "parent_id=0 LIMIT $this->limit";
                $result     = $this->select_rows('product_cat', 'id,cat_name,parent_id', $condition);

                while($row  = mysql_fetch_array($result))
                {
                    $id_array[] = $row['id'];                
                } 

                $cat_id = $id_array[$id];

                //返回顶层
                $condition  = "parent_id={$cat_id} LIMIT $this->limit";
                $result     = $this->select_rows('product_cat', 'id,cat_name,parent_id', $condition); 

                //创建指令
                $this->create_a_record('username', array('name'=>$fromUsername,'cat_id'=>$cat_id));                         
            }
            else
            {
                $id_array   = array();
                //查询上一次发出的指令数据
                $result     = $this->select_rows('product_cat', 'id,cat_name,parent_id', array('parent_id'=>$last_id));
                while($row= mysql_fetch_array($result))
                {
                    $id_array[] = $row['id'];                
                } 

                $id = $id_array[$id];

                //发送指令数据
                $condition  = "parent_id={$id} LIMIT $this->limit";
                $result  = $this->select_rows('product_cat', 'id,cat_name,parent_id', $condition);
                //创建指令记录   
                $last_id = $this->create_a_record('username', array('name'=>$fromUsername,'cat_id'=>$id)); 
            }
        }
        elseif ($keyword === '*')
        {
            //返回上一级指令： 删除数据库最后一条数据之后 获取最后一次操作id 作为查询条件
            $condition  = "name='{$fromUsername}' ORDER BY id desc limit 1";
            $last_id    = $this->get_a_value('username', 'id', $condition);
 
            if (! $last_id) {
                //返回顶层
                $condition  = "parent_id=0 LIMIT $this->limit";
                $result     = $this->select_rows('product_cat', 'id,cat_name,parent_id', $condition);
            } else {
                $sql        = "DELETE FROM username where name='{$fromUsername}' AND id={$last_id}";
                mysql_query($sql);
  
                $condition  = "name='{$fromUsername}' ORDER BY id desc limit 1";
                $last_catid = $this->get_a_value('username', 'cat_id', $condition);
              
                if (! $last_catid) {
                    //返回顶层
                    $condition  = "parent_id=0 LIMIT $this->limit";
                    $result     = $this->select_rows('product_cat', 'id,cat_name,parent_id', $condition);
                }else{
                    $condition  = "parent_id={$last_catid} LIMIT $this->limit";
                    $result     = $this->select_rows('product_cat', 'id,cat_name,parent_id', $condition);
                }

            }
        } elseif ($keyword == '0') {
            //初始化指令
            $sql    = "DELETE FROM username where name='{$fromUsername}'";
            mysql_query($sql);

            //返回顶层
            $condition  = "parent_id=0 LIMIT $this->limit";
            $result     = $this->select_rows('product_cat', 'id,cat_name,parent_id', $condition);
        } else {
            return '非法指令';
        }

        $contentStr = '';
        $i          = 0;
        while ($row= mysql_fetch_array($result))
        {
            $id_array[] = $row['id'];
            $contentStr .= $i++.':'.$row['cat_name']."\r\n";
        }

        if (! empty($contentStr)) {
            return $contentStr;
        }else{
            return '下面没有数据了';
        } 
    }
    
}

?>