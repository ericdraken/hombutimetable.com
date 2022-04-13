<?php

class cURL {
	
	var $headers;
	var $user_agent;
	var $compression;
	var $cookie_file;
	var $proxy;
	var $referer;
	var $responseCode;
	var $timeout;
	
	public function getResponseCode() {
		return $this->responseCode;
	}

	function cURL($cookies=TRUE,$cookie=null,$compression='gzip',$proxy='') {
	
		if($cookie == null){
			$cookie = realpath(__DIR__ . "/../../tmp") . "/cookies.txt";
		}
		
		$this->headers[] = 'Accept: image/gif, image/x-bitmap, image/jpeg, image/pjpeg';
		$this->headers[] = 'Connection: Keep-Alive';
		$this->headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
		$this->user_agent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)';
		$this->compression=$compression;
		$this->proxy=$proxy;
		$this->cookies=$cookies;
		$this->referer=FALSE;
		$this->responseCode = 0;
		$this->timeout = 30;
		
		if ($this->cookies == TRUE) $this->cookie($cookie);
	}
	
	function setUA($str)
	{
		// This is a good one! 'Googlebot/2.1 (http://www.googlebot.com/bot.html)';
		$this->user_agent = $str;
	}
	
	function setReferer($str)
	{
		$this->referer=$str;
	}

	// 2012.07.02
	function setTimeout($timeout)
	{
		$this->timeout=$timeout;
	}
	
	function cookie($cookie_file) {
		if (file_exists($cookie_file)) {
			$this->cookie_file=$cookie_file;
		} else {
			fopen($cookie_file,'w') or $this->error('The cookie file could not be opened. Make sure this directory has the correct permissions');
			$this->cookie_file=$cookie_file;
			fclose($this->cookie_file);
		}
	}
	
	function get($url, $filename = null, $progress = null) {
		
		// Direct to disk?
		$o = null;
        if($filename != null) {
			$o = @fopen ($filename, "w");
			if(!$o){
				print "curl: Error: Failed to open target-file\n";
			}
		}		
		
		$process = curl_init($url);
		
		// 2012.06.03 - Process a callback function
		if($progress) {
			curl_setopt($process, CURLOPT_PROGRESSFUNCTION, $progress);
			curl_setopt($process, CURLOPT_NOPROGRESS, false);
		}
		
		curl_setopt($process, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($process, CURLOPT_HEADER, 0);
		curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);
		if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
		if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
		curl_setopt($process,CURLOPT_ENCODING , $this->compression);
		curl_setopt($process, CURLOPT_TIMEOUT, $this->timeout);
		if (strstr($this->referer,"://") > 0) curl_setopt($process, CURLOPT_REFERER, $this->referer);
		if ($this->proxy) curl_setopt($cUrl, CURLOPT_PROXY, 'proxy_ip:proxy_port');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
		
		// Direct to disk?
		$return = "";
		if($o) {
			curl_setopt($process, CURLOPT_FILE, $o);
			curl_exec($process);
		} else {
			$return = curl_exec($process);
		}
		
		$this->responseCode = curl_getinfo($process, CURLINFO_HTTP_CODE);
		curl_close($process);
		
		// Direct to disk?
		if($o) {
			fclose($o);
		}

		return $return;
	}
	
	function post($url,$data) {
		$process = curl_init($url);
		curl_setopt($process, CURLOPT_HTTPHEADER, $this->headers);
		
		// Do NOT print the return headers
		curl_setopt($process, CURLOPT_HEADER, 0);
		
		curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);
		if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
		if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
		curl_setopt($process, CURLOPT_ENCODING , $this->compression);
		curl_setopt($process, CURLOPT_TIMEOUT, $this->timeout);
		if (strstr($this->referer,"://") > 0) curl_setopt($process, CURLOPT_REFERER, $this->referer);
		if ($this->proxy) curl_setopt($process, CURLOPT_PROXY, $this->proxy);
		curl_setopt($process, CURLOPT_POSTFIELDS, $data);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($process, CURLOPT_POST, 1);
		$return = curl_exec($process);
		$this->responseCode = curl_getinfo($process, CURLINFO_HTTP_CODE);
		curl_close($process);
		return $return;
	}
	
	function error($error) {
		echo "<center><div style='width:500px;border: 3px solid #FFEEFF; padding: 3px; background-color: #FFDDFF;font-family: verdana; font-size: 10px'><b>cURL Error</b><br>$error</div></center>";
		die;
	}
}

//$cc = new cURL();
//$cc->get('http://www.example.com');
//$cc->post('http://www.example.com','foo=bar');
?>
