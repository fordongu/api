<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.com/license/gpl GPL License
 */
 
 
namespace common\obj;

define('HTTP_TIME_OUT',-3);
class Curl extends \yii\base\Object{

    var $timeout = 5;
    var $defaultChunk = 4096;
    var $http_ver = '1.1';
    var $hostaddr = null;
    var $default_headers = array(
        'Pragma'=>"no-cache",
        'Cache-Control'=>"no-cache",
        'Connection'=>"close"
        );

    
    function set_timeout($timeout){
        $this->timeout = $timeout;
        return $this;
    }
    

	//认证方式
	function gen_sign($params)
	{
		return strtoupper(md5(strtoupper(md5($this->pz_assemble($params)))));
	}

	function pz_assemble($params)
	{
		if (!is_array($params)) {
			return NULL;
		}
		ksort($params, SORT_STRING);
		$sign = '';

		foreach ($params as $key => $val ) {
			if (is_null($val) || empty($val) || !isset($val) || !$val || $val=='Array') {
				continue;
			}
			if (is_bool($val)) {
				$val = ($val ? 1 : 0);
			}
			$sign .= $key . (is_array($val) ? $this->pz_assemble($val) : $val);
		}
		return $sign;
	}



    function action($action,$url,$headers=null,$callback=null,$data=null,$ping_only=false){
    	
    	$data['sign'] = $this->gen_sign($data);
    	
    	
		$action = $action=='post'?true:false;
        $headers = array_merge($this->default_headers,(array)$headers);
		$set_headers = array();
        foreach((array)$headers as $k=>$v){
            $set_headers[] .= $k.': '.$v;
        }

		$this->responseBody = '';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this,'callback_header'));
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this,'callback_body'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		if($set_headers)
			curl_setopt($ch, CURLOPT_HTTPHEADER, $set_headers);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, $this->http_ver);
		curl_setopt($ch, CURLOPT_POST, $action=='post'?true:false);

		curl_exec($ch);

		curl_close($ch);

        $this->callback = $callback;
		preg_match('/\d{3}/',$this->responseHeader,$match);
		$this->responseCode = $match[0];
		switch($this->responseCode){
			case 301:
			case 302:
			//logger::info(" Redirect \n\t--> ".$responseHeader['location']);
			return false;

			case 200:
		//	logger::info(' OK');
			if($this->callback){
				if(!call_user_func_array($this->callback,array($this,$this->responseBody))){
					break;
				}
			}
			return $this->responseBody;

			case 404:
		//	logger::info(' file not found');
			return false;

			default:
			return false;
		}

    }

	function callback_header($curl,$header){
		$this->responseHeader .= $header;
		return strlen($header);
	}
	function callback_body($curl,$content){
		$this->responseBody .= $content;
		return strlen($content);
	}
	function is_addr($ip){
		return preg_match('/^[0-9]{1-3}\.[0-9]{1-3}\.[0-9]{1-3}\.[0-9]{1-3}$/',$ip);
	}

	private function microtime(){
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

}
