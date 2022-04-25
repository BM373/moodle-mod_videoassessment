<?php
/**
 * Video assessment
 *
 * @package videoassessment
 * @author  VERSION2 Inc.
 * @version $Id: ajax.php 823 2012-09-27 05:28:21Z yama $
 */

require_once __DIR__.'/../../../config.php';

require_once __DIR__.'/lib.php';

try {
	$cmid = required_param('cmid', PARAM_INT);
	
	$bulkupload = new videoassessment_bulkupload($cmid);
	$bulkupload->require_capability();
	
	if (isset($_FILES['file'])) {
		$code = $bulkupload->start_async($_FILES['file']);
		send_headers('text/plain', false);
		echo $code;
		exit;
	}
	
	if ($code = optional_param('code', '', PARAM_TEXT)) {
		$progress = $bulkupload->get_progress($code);
		send_headers('text/plain', false);
		echo $progress;
		exit;
	}
	
} catch (Exception $ex) {
	header('HTTP/1.1 403 Forbidden');
	error_log($ex->__toString());
}
