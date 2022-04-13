<?php
/**
 * Custom exception class
 */

 require_once(__DIR__ . '/HombuLogger.php');
 
 class HombuException extends Exception {
	
	private $logger = null;
	
	public function __construct($message, HombuLogger $logger = null) {
		$this->logger = $logger;
		parent::__construct($message, 0);
	}   
	
	public function __toString()
	{
		if(isset($this->logger)) {
			$this->logger->error($this->message . implode("<br />&nbsp;&nbsp;#", explode("#", $this->getTraceAsString())) );
		}
		
		return get_class($this) . ": {$this->message}";
	}
 } 

?>