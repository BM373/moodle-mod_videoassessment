<?php
/**
 * Video assessment
 *
 * @package videoassessment
 * @author  VERSION2 Inc.
 * @version $Id: async.php 823 2012-09-27 05:28:21Z yama $
 */

require_once __DIR__.'/../../../config.php';

require_once __DIR__.'/lib.php';

try {
	$cmid = required_param('cmid', PARAM_INT);
	$file = required_param('file', PARAM_FILE);

	$bulkupload = new videoassessment_bulkupload($cmid);
	//$bulkupload->require_capability();
	$bulkupload->convert($file);

} catch (Exception $ex) {
	header('HTTP/1.1 403 Forbidden');
	error_log($ex->__toString());
}
