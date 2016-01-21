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
	
	public function req_url($url,$params=array()) {
		
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		if (count($params)>0) {
			curl_setopt($ch, CURLOPT_POST, true); // post
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params)); // post数据 可为数组、连接字串
		}
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
			
		$rsp = curl_exec ( $ch );
		curl_close ( $ch );


		$rsp = json_decode ( $rsp, TRUE );
		return $rsp;
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
		$rsp=$this->req_url("https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token,array("body"=>$param["body"]));
		if(isset($rsp["errcode"])&&$rsp["errcode"]==40001){
			$access_token=$this->req_access_token();
			$rsp=$this->req_url("https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token,array("body"=>$param["body"]));
		}
		return $rsp;
	}
	//获取微信API的access_token值：选填AppID,AppSecret
	protected function get_access_token($param)
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
			$rsp=$this->req_access_token();
		}
		$this->_instance->cache->redis->save("access_token_of_".$this->AppID, $rsp["access_token"], 60*60*2-300);
		return $rsp["access_token"];
	}
}

?>