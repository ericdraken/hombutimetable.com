<?php
/**
 * Track messages internally
 * REF: http://dollyaswin.net/en/blog/log_your_php_application_with_zend_log_part_3
 */

 require_once('Zend/Log.php');
 require_once('Zend/Log/Writer/Stream.php');
 
 class HombuLogger extends Zend_Log {
 	
	protected $logger, $writer;
	
	function __construct() {
		
		// set formatter, add %class% to save class name
		$format = '%message%' . "<br />" . PHP_EOL;
		$this->_formatter = new Zend_Log_Formatter_Simple($format);

		$error_format = "(%priorityName%) %priority%: <font color=\"red\">%message%</font><br />" . PHP_EOL;
		$this->_error_formatter = new Zend_Log_Formatter_Simple($error_format);

		parent::addWriter($this->_allWriter());
		parent::addWriter($this->_errorWriter());
		parent::__construct();	
	}
	
	protected function _allWriter()
	{
		$writer = new Zend_Log_Writer_Stream('php://output');
		$writer->addFilter(new Zend_Log_Filter_Priority(Zend_Log::ERR, "!="));
		$writer->setFormatter($this->_formatter);
		return $writer;
	}	
	
	protected function _errorWriter()
	{
		$writer = new Zend_Log_Writer_Stream('php://output');
		$writer->addFilter(new Zend_Log_Filter_Priority(Zend_Log::ERR));
		$writer->setFormatter($this->_error_formatter);
		return $writer;
	}	
	
	function info($str) {
		parent::log($str, Zend_Log::INFO);
	}

	function error($str) {
		parent::log($str, Zend_Log::ERR);
	}

	function debug($str) {
		parent::log($str, Zend_Log::DEBUG);
	}
	
	function debugArray($str) {
		parent::log("<pre>".print_r($str, true)."</pre>", Zend_Log::DEBUG);
	}
 }
?>