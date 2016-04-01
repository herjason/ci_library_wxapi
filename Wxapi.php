<?php
/**
  * wechat php api
  */

//define your token
//define("TOKEN", "wddgegj53hsgs");

class wxapi
{
	//超级对象
	private $_instance;
	private $AppID = "";//定义公众号AppID
	private $AppSecret = "";//定义公众号AppSecret
	
	public function __construct()
	{
		//获得超级对象
		$this->_instance = & get_instance();
	
		//载入Redis驱动
		$this->_instance->load->driver('cache', array('adapter' => 'redis'));
	}
	
	public function site_url($uri = '', $protocol = NULL)
	{
		return $this->_instance->config->site_url($uri, $protocol);
	}
	
	public function current_url()
	{
		return $this->_instance->config->site_url($this->_instance->uri->uri_string());
	}
	
	public function redirect($uri = '', $method = 'location', $http_response_code = 302)
	{
		if ( ! preg_match('#^https?://#i', $uri))
		{
			$uri = $this->site_url($uri);
		}
	
		switch($method)
		{
			case 'refresh'	: header("Refresh:0;url=".$uri);
			break;
			default			: header("Location: ".$uri, TRUE, $http_response_code);
			break;
		}
		exit;
	}
	
	public function is_wx_browser()
	{
		$user_agent = '';
		if(isset($_SERVER['HTTP_USER_AGENT']))
			$user_agent=$_SERVER['HTTP_USER_AGENT'];
		if (!empty($user_agent)&&strpos($user_agent, 'MicroMessenger') ==false)
		{
			return false;
		}
		return true;
	}
	
	public function req_url($url,$params=array()) {
		
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		if (!empty($params)) {
			curl_setopt($ch, CURLOPT_POST, true); // post
			if(is_array($params)){
				$params=json_encode($params);
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params); // post数据 可为数组、连接字串
		}
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
			
		$rsp = curl_exec ( $ch );
		curl_close ( $ch );


		$rsp = json_decode ( $rsp, TRUE );
		return $rsp;
	}
	//获取微信openid：选填AppID,AppSecret
	public function get_open_id($param)
	{
		//判断是否微信浏览器
		$is_wx_browser = $this->is_wx_browser();
		if(!$is_wx_browser){
			return "";
		}
		if(isset($param["AppID"])&&isset($param["AppSecret"])){
			$this->AppID = $param["AppID"];
			$this->AppSecret = $param["AppSecret"];
		}
		$code=$_GET["code"];
		if(empty($code))//step1
		{
			$current_url=$this->current_url();
			$url= $current_url.'/?'.$_SERVER['QUERY_STRING'];
			$url=urlencode($url);
			$url="https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$this->AppID."&redirect_uri=".$url."&response_type=code&scope=snsapi_base&state=123#wechat_redirect";
			$this->redirect($url);
	
		}else { //step2
	
			$url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->AppID."&secret=".$this->AppSecret."&code=".$code."&grant_type=authorization_code";
			$rsp=$this->req_url($url);
			if(!isset($rsp['openid'])||empty($rsp['openid']))
				return "";
			return $rsp['openid'];
		}// end step2
	}
	//推送微信模板消息：必填template__param；选填AppID,AppSecret
	public function post_wx_template($param)
	{
		$access_token = $this->get_access_token($param);
		$rsp=$this->req_url("https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token,$param["template_param"]);
		if(isset($rsp["errcode"])&&$rsp["errcode"]==40001){
			$access_token = $this->req_access_token();
			$rsp=$this->req_url("https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token,$param["template_param"]);
		}
		return $rsp;
	}
	//获取微信用户信息：必填openid;选填AppID,AppSecret
	public function get_wx_user_info($param)
	{
		$access_token=$this->get_access_token($param);
		$rsp=$this->req_url("https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$param["openid"]."&lang=zh_CN");
		if(isset($rsp["errcode"])&&$rsp["errcode"]==40001){
			$access_token=$this->req_access_token();
			$rsp=$this->req_url("https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$param["openid"]."&lang=zh_CN");
		}
		return $rsp;
	}
	//自定义菜单查询：选填AppID,AppSecret
	public function get_menu($param)
	{
		$access_token=$this->get_access_token($param);
		$rsp=$this->req_url("https://api.weixin.qq.com/cgi-bin/menu/get?access_token=".$access_token);
		if(isset($rsp["errcode"])&&$rsp["errcode"]==40001){
			$access_token=$this->req_access_token();
			$rsp=$this->req_url("https://api.weixin.qq.com/cgi-bin/menu/get?access_token=".$access_token);
		}
		return $rsp;
	}
	//自定义菜单创建：必填body;选填AppID,AppSecret
	public function create_menu($param)
	{
		$access_token=$this->get_access_token($param);
		if(is_array($param["body"])){
			$param["body"] = $this->json_encode($param["body"]);
		}
		$rsp=$this->req_url("https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token,$param["body"]);
		if(isset($rsp["errcode"])&&$rsp["errcode"]==40001){
			$access_token=$this->req_access_token();
			$rsp=$this->req_url("https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token,$param["body"]);
		}
		return $rsp;
	}
	//获取JS-SDK使用权限签名：必填AppID,AppSecret
	public function get_js_sdk_signature($param)
	{
		if(isset($param["AppID"])&&isset($param["AppSecret"])){
			$this->AppID = $param["AppID"];
			$this->AppSecret = $param["AppSecret"];
		}
		$jsapi_ticket=$this->get_jsapi_ticket();
		$timestr = $_SERVER['REQUEST_TIME'];
		$noncestr = md5($timestr);
		$string1 = "jsapi_ticket=".$jsapi_ticket."&noncestr=".$noncestr."&timestamp=".$timestr."&url=".$this->current_url();;
		$signature=sha1($string1);
		
		$rsp=array();
		$rsp["timestr"] = $timestr;
		$rsp["noncestr"] = $noncestr;
		$rsp["signature"] = $signature;
		
		return $rsp;
	}
	//获取微信API的access_token值：选填AppID,AppSecret
	protected function get_access_token($param=array())
	{
		if(isset($param["AppID"])&&isset($param["AppSecret"])){
			$this->AppID = $param["AppID"];
			$this->AppSecret = $param["AppSecret"];
		}
		//获取缓存的access_token
		$access_token = $this->_instance->cache->redis->get("access_token_of_".$this->AppID);
		if(empty($access_token)){
			$access_token = $this->req_access_token();
		}
		return $access_token;
	}
	
	protected function req_access_token()
	{
		$url="https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->AppID."&secret=".$this->AppSecret;
		$rsp=$this->req_url($url);
		if(!isset($rsp["access_token"])){
			$rsp=$this->req_url($url);
		}
		$this->_instance->cache->redis->save("access_token_of_".$this->AppID, $rsp["access_token"], 60*60*2-300);
		return $rsp["access_token"];
	}
	
	//获取jsapi：选填AppID,AppSecret
	public function get_jsapi_ticket($param=array())
	{
		if(isset($param["AppID"])&&isset($param["AppSecret"])){
			$this->AppID = $param["AppID"];
			$this->AppSecret = $param["AppSecret"];
		}
		//获取缓存的jsapi_ticket
		$jsapi_ticket = $this->_instance->cache->redis->get("jsapi_ticket_of_".$this->AppID);
		if(empty($jsapi_ticket)){
			$jsapi_ticket = $this->req_jsapi_ticket();
		}
		return $jsapi_ticket;
	}
	protected function req_jsapi_ticket()
	{
		$access_token=$this->get_access_token();
		$url="https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=".$access_token."&type=jsapi";
		$rsp=$this->req_url($url);
		if($rsp["errcode"]!=0){
			$access_token=$this->req_access_token();
			$url="https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=".$access_token."&type=jsapi";
			$rsp=$this->req_url($url);
		}
		$this->_instance->cache->redis->save("jsapi_ticket_of_".$this->AppID, $rsp["ticket"], 60*60*2-300);
		return $rsp["ticket"];
	}
	//对象或对象数组转换为json：微信api不支持中文转义的json结构
	public function json_encode($arr)
	{
		if(is_array($arr)||is_object($arr)){
			//判断参数是否为纯数组，即以索引值为下标
			$is_array = false;
			if(is_array($arr)){
				$keys = array_keys($arr);
				$max_length = count($arr) - 1;
				if (($keys [0] === 0) && ($keys [$max_length] === $max_length )) { //See if the first key is 0 and last key is length - 1
					$is_array = true;
				}
			}
			if ($is_array){
				$arr_json='[';
				for($i=0;$i<count($arr);$i++){
					$arr_json=$arr_json.($i>0?',':'').$this->json_encode($arr[$i]);
				}
				$arr_json=$arr_json.']';
				return $arr_json;
			}else{
				$arr_json='{';
				$i=0;
				foreach ($arr as $key=>$val){
					$arr_json=$arr_json.($i>0?',':'').'"'.$key.'":'.$this->json_encode($val);
					$i=$i+1;
				}
				$arr_json=$arr_json.'}';
				return $arr_json;
			}
		}elseif (is_string($arr)){
			return '"'.$arr.'"';
		}elseif (is_numeric($arr)){
			return $arr;
		}else {
			return '""';
		}
	}
}

?>