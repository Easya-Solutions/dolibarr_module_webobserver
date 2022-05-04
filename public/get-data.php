<?php

// To escape other module output
if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
	ob_start(null, 0, PHP_OUTPUT_HANDLER_STDFLAGS ^ PHP_OUTPUT_HANDLER_REMOVABLE);
} else {
	ob_start(null, 0, false);
}

if(is_file('../main.inc.php'))$dir = '../';
else  if(is_file('../../../main.inc.php'))$dir = '../../../';
else  if(is_file('../../../../main.inc.php'))$dir = '../../../../';
else  if(is_file('../../../../../main.inc.php'))$dir = '../../../../../';
else $dir = '../../';

require $dir.'master.inc.php';

// clean other modules print and failure
ob_clean();


require_once __DIR__ . '/../class/webobserver.class.php';

$webObserver = new WebObserver();

WebObserver::securityCheck(GETPOST('api_key'));

print $webObserver::getInstanceJson();


ob_flush();
