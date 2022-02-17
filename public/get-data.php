<?php

// To escape other module output
if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
	ob_start(null, 0, PHP_OUTPUT_HANDLER_STDFLAGS ^
		PHP_OUTPUT_HANDLER_REMOVABLE);
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


$result = new stdClass;

$result->apiversion = '1.0';

$result->dolibarr = new stdClass;
$result->dolibarr->version = DOL_VERSION;
$result->dolibarr->version1 = $conf->global->MAIN_VERSION_LAST_INSTALL;
$result->dolibarr->theme = $conf->theme;

$result->dolibarr->path=new stdClass;
$result->dolibarr->path->http = dol_buildpath('/',2);
$result->dolibarr->path->relative = dol_buildpath('/var/www/client/',1);
$result->dolibarr->path->absolute = dol_buildpath('/var/www/client/',0);

$result->dolibarr->data = new stdClass;
$result->dolibarr->data->path = DOL_DATA_ROOT;
$result->dolibarr->data->size = _dir_size(DOL_DATA_ROOT);

$result->dolibarr->htdocs=new stdClass;
$result->dolibarr->htdocs->path = DOL_DOCUMENT_ROOT;
$result->dolibarr->htdocs->size = _dir_size(DOL_DOCUMENT_ROOT);

$result->dolibarr->repertoire_client=new stdClass;
$result->dolibarr->repertoire_client->path = dirname(dirname(DOL_DOCUMENT_ROOT));
$result->dolibarr->repertoire_client->size = _dir_size($result->dolibarr->repertoire_client->path);

$result->db=new stdClass;
$result->db->host = $dolibarr_main_db_host;
$result->db->name = $dolibarr_main_db_name;
$result->db->user = $dolibarr_main_db_user;
$result->db->type = $dolibarr_main_db_type;

$result->user=new stdClass;
$result->user->all = _nb_user();
$result->user->active = _nb_user(true);
$result->user->date_last_login = _last_login() ;

$result->module = new stdClass;

$result->module = _module_active();

$result->server_info = _getServerInfo();
//var_dump($result);

echo json_encode($result);

// Ouput data
ob_flush();

function _module_active() {
	include_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

	global $db, $conf;
	$modNameLoaded = array();
	$modulesdir = dolGetModulesDirs();

	foreach ($modulesdir as $dir)
	{
		$handle = @opendir($dir);
		if (is_resource($handle))
		{
			while (($file = readdir($handle)) !== false)
			{
				if (is_readable($dir.$file) && substr($file, 0, 3) == 'mod' && substr($file, dol_strlen($file) - 10) == '.class.php')
				{
					$modName = substr($file, 0, dol_strlen($file) - 10);

					if ($modName)
					{
						/*if (!empty($modNameLoaded[$modName]))   // In cache of already loaded modules ?
						{
							$mesg = "Error: Module ".$modName." was found twice: Into ".$modNameLoaded[$modName]." and ".$dir.". You probably have an old file on your disk.<br>";
							setEventMessages($mesg, null, 'warnings');
							dol_syslog($mesg, LOG_ERR);
							continue;
						}*/

						try
						{
							$res = include_once $dir.$file; // A class already exists in a different file will send a non catchable fatal error.
							if (class_exists($modName))
							{
								try {
									$objMod = new $modName($db);
									$modNameLoaded[$modName] = new stdClass();
									$modNameLoaded[$modName]->dir = $dir;
									$modNameLoaded[$modName]->numero = $objMod->numero;
									$modNameLoaded[$modName]->version = $objMod->version;
									$modNameLoaded[$modName]->source = $objMod->isCoreOrExternalModule();
									$modNameLoaded[$modName]->gitinfos = _getModuleGitInfos($dir);
									$modNameLoaded[$modName]->editor_name = dol_escape_htmltag($objMod->getPublisher());
									$modNameLoaded[$modName]->editor_url = dol_escape_htmltag($objMod->getPublisherUrl());
									$modNameLoaded[$modName]->active = !empty($conf->global->{$objMod->const_name});
								}
								catch (Exception $e)
								{
									dol_syslog("Failed to load ".$dir.$file." ".$e->getMessage(), LOG_ERR);
								}
							}
							else
							{
								print "Warning bad descriptor file : ".$dir.$file." (Class ".$modName." not found into file)<br>";
							}
						}
						catch (Exception $e)
						{
							dol_syslog("Failed to load ".$dir.$file." ".$e->getMessage(), LOG_ERR);
						}
					}
				}
			}
			closedir($handle);
		}
		else
		{
			dol_syslog("htdocs/admin/modules.php: Failed to open directory ".$dir.". See permission and open_basedir option.", LOG_WARNING);
		}
	}

	return $modNameLoaded;
}

function _getModuleGitInfos($dir) {
	global $donedir;
	if(isset($donedir[$dir])) return $donedir[$dir];

	$cmd = 'cd ' . $dir . ' && git status';
	$res = shell_exec($cmd);

	$branch = substr($res, strpos($res, 'On branch ')+10, strpos($res, "\n" )-10);
	$donedir[$dir] = new stdClass();
	$donedir[$dir]->branch = $branch;
	$donedir[$dir]->status = $res;

	return $donedir[$dir];
}

function _dir_size($dir) {

	// taile en Mo

	$io = popen ( 'du -sm ' . $dir, 'r' );
	$size = fgets ( $io, 4096);
	$size = substr ( $size, 0, strpos ( $size, "\t" ) );
	pclose ( $io );

	return (int)$size;
}

function _last_login() {
	global $db;

	$sql = "SELECT MAX(datelastlogin) as datelastlogin FROM ".MAIN_DB_PREFIX."user WHERE 1 ";
	$sql.=" AND statut=1 AND rowid>1"; // pas l'admin

	$res = $db->query($sql);

	$obj = $db->fetch_object($res);

	return $obj->datelastlogin;

}

function _nb_user($just_actif = false) {
	global $db;

	$sql = "SELECT count(*) as nb FROM ".MAIN_DB_PREFIX."user WHERE 1 ";

	if($just_actif) {
		$sql.=" AND statut=1 ";
	}

	$res = $db->query($sql);

	$obj = $db->fetch_object($res);

	return (int)$obj->nb;


}


function _getServerInfo()
{
	$si_prefix = array( 'B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB' );
	$base = 1024;

	$root = DOL_DOCUMENT_ROOT.'/../../../';

	$bytes_total = disk_total_space($root);
	$bytes_left = disk_free_space($root);
	$bytes_used = $bytes_total - $bytes_left;

	$class = min((int)log($bytes_total , $base) , count($si_prefix) - 1);
	$espace_total = sprintf('%1.2f' , $bytes_total / pow($base,$class)) . ' ' . $si_prefix[$class];

	$class = min((int)log($bytes_left , $base) , count($si_prefix) - 1);
	$espace_left = sprintf('%1.2f' , $bytes_left / pow($base,$class)) . ' ' . $si_prefix[$class];

	$class = min((int)log($bytes_used , $base) , count($si_prefix) - 1);
	$espace_used = sprintf('%1.2f' , $bytes_used / pow($base,$class)) . ' ' . $si_prefix[$class];

	$percent_used = sprintf('%1.2f %%' , $bytes_used * 100 / $bytes_total, true);

	return array(
		'espace_total' => $espace_total
	,'espace_restant' => $espace_left
	,'espace_used' => $espace_used
	,'percent_used' => $percent_used
	);

}
