<?php
/**
 * Currently using a service account for paperlessofficepro.com
 * uses the Google Apps Script API to push and pull from stand alone and container bound scripts
 * * each local folder must contain a manifest.json file that contains a 'scriptId' key
*/

// retrieve arguments from cli and put in $_GET, otherwise $_GET populated by web form
if (isset($argc) && $argc > 1) parse_str(implode('&',array_slice($argv, 1)), $_GET);

if (isset($_GET['type']) && $_GET['type']) $type = $_GET['type'];
else $type = '';

if (isset($_GET['filePrefix']) && $_GET['filePrefix']) $filePrefix = $_GET['filePrefix'];
else $filePrefix = '';

if (isset($_GET['folderName']) && $_GET['folderName']) $localFolderPath = '../' . $_GET['folderName'];
else $localFolderPath = '';

// for use when an alternate scriptId is specified, e.g. upload Magic Viewer files to the Magic HOA script
if (isset($_GET['scriptId']) && $_GET['scriptId']) $scriptId = $_GET['scriptId'];
else {
	// get scriptId
	$manifestPath = $localFolderPath . "/manifest.json";
	if (!file_exists($manifestPath)) {
		$data['error'] = 'Could not find "manifest.json" in the "'.  $_GET['folderName']  . '" folder.' ;
		echo json_encode($data);
		exit();
	}
	$manifestJson = json_decode(file_get_contents($manifestPath),true);
	if (!array_key_exists('scriptId',$manifestJson)) {
		$data['error'] = 'Could not find "scriptId" in the "manifest.json" file';
		echo json_encode($data);
		exit();
	} else {
		$scriptId = $manifestJson['scriptId'];
	}
}

// check for valid folder path
if (!is_dir($localFolderPath)) {
	$data['error'] = 'Could not find a folder named "' . $_GET['folderName'] . '"';
	echo json_encode($data);
	exit();
}


require_once ('vendor/autoload.php');
putenv('GOOGLE_APPLICATION_CREDENTIALS=paperless-office-service-acct.json');
define('SCOPES', implode(' ', array(
  "https://www.googleapis.com/auth/script.projects")
));

$client = new Google_Client();
$client->setScopes(SCOPES); // accepts an array of scopes
$client->useApplicationDefaultCredentials();
$client->setSubject('lpadan@paperlessofficepro.com');
$service = new Google_Service_Script($client);

switch ($type) {

	case 'push':
		push($scriptId, $service, $localFolderPath,$filePrefix);
		break;

	case 'pull':
		pull($scriptId, $service, $localFolderPath);
		break;

	default:
		$result['success'] = false;
		$result['error'] = "Invalid Type or Type not specified\n";
		echo json_encode($result);
		exit;
}

function getServerFiles($scriptId,$service) {
	try {
		$response = $service->projects->getContent($scriptId);
	} catch (Exception $e) {
		$temp = json_decode($e->getMessage(),true);
		$message = $temp['error']['message'];
		$pos = strpos($message,'File not found:');
		if ($pos === false) {
			$result['error'] = $message;
		}
		else {
			$result['error'] = 'Script file not found. Check if file exists, or if you have edit rights to the file.';
		}
		$result['success'] = false;
		echo json_encode($result);
		exit;
	}
	$serverFiles = $response->files;
	return $serverFiles;
}

function pull($scriptId,$service,$localFolderPath) {

	$serverFiles = getServerFiles($scriptId,$service);
	// delete files from the $localFolderPath
	$files = glob($localFolderPath . '/*'); // ignore hidden files, includes folders
	foreach($files as $file){
	    if(is_file($file)){
	        $fileName = pathinfo($file,PATHINFO_BASENAME);
	        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
	        if ($ext === 'gs' || $ext === 'html' || $fileName === 'appsscript') unlink($file); //delete file
	    }
	}
	// create new files with names and content from $serverFiles array
	for ($i = 0; $i < sizeof($serverFiles); $i++) {
		if ($serverFiles[$i]['type'] === 'SERVER_JS') {
			$newFilePath = $localFolderPath . "/" . $serverFiles[$i]['name'] . ".gs";
		} elseif ($serverFiles[$i]['type'] === 'HTML') {
			$newFilePath = $localFolderPath . "/" . $serverFiles[$i]['name'] . ".html";
		} elseif ($serverFiles[$i]['name'] === 'appsscript') {
			$newFilePath = $localFolderPath . "/" . $serverFiles[$i]['name'] . ".json";
		}
		$newFile = fopen($newFilePath,"w");
		$content = $serverFiles[$i]['source'];
		fwrite($newFile,$content);
	}
	$result['success'] = true;
	echo json_encode($result);
}

function push($scriptId,$service,$localFolderPath,$filePrefix) {

	// NOTE:  files on the server that do not have the same named file locally will be deleted
	// unless you specify an "Excluded File Prefix", in which case files with the prefix will not be overwritten
	// this funcitonality is to allow Magic HOA to be updated with the Magic Viewer files, without overwriting the HOA files
	// HOA files are prefixed with 'hoa'


	$serverFiles = getServerFiles($scriptId,$service);
	for ($i = 0; $i < sizeof($serverFiles); $i++) {
		if ($serverFiles[$i]['name'] === 'appsscript') {
			$manifestContent = $serverFiles[$i]['source'];
			break;
		}
	}


	$localFileNames = glob($localFolderPath . '/*'); // ignores hidden files, does return folders
	$localFiles = [];
	foreach ($localFileNames as $fileName) {
		if (!is_file($fileName)) continue; // ignore folders
		$fileName = pathinfo($fileName,PATHINFO_BASENAME);
		$ext = pathinfo($fileName, PATHINFO_EXTENSION);
		if ($ext === 'gs') $type = 'server_js';
		else if ($ext === 'html') $type = 'html';
		else continue;
		$pos = strripos($fileName,'.');
		$trimName = substr($fileName,0,$pos); // remove file extension
		$content = file_get_contents($localFolderPath . "/" . $fileName);
		$localFile = new Google_Service_Script_ScriptFile();
		$localFile -> setName($trimName);
		$localFile -> setType($type);
		$localFile -> setSource($content);
 		$localFiles[] = $localFile;
	}


	// add files from server to local files array if an excluded filePrefix is specified
	// all files on the server are deleted, but the preFixed files are first downloaded and saved to $localFiles[]
	if ($filePrefix) {
		for ($i = 0; $i < sizeof($serverFiles); $i++) {
			if (strpos($serverFiles[$i]['name'],$filePrefix) === 0) {
				if ($serverFiles[$i]['type'] === 'SERVER_JS') {
					$type = 'SERVER_JS';
				} elseif ($serverFiles[$i]['type'] === 'HTML') {
					$type = 'HTML';
				}
				$content = $serverFiles[$i]['source'];
				$serverFile = new Google_Service_Script_ScriptFile();
				$serverFile -> setName($serverFiles[$i]['name']);
				$serverFile -> setType($type);
				$serverFile -> setSource($content);
		 		$localFiles[] = $serverFile;
			}
		}
	}


	$manifestFile = new Google_Service_Script_ScriptFile();
	$manifestFile->setName('appsscript');
	$manifestFile->setType('JSON');
	$manifestFile->setSource($manifestContent);
	$localFiles[] = $manifestFile;

	$request = new Google_Service_Script_Content($scriptId);
  	$request->setFiles($localFiles);

	try {
		$response = $service->projects->updateContent($scriptId, $request);
		$result['success'] = true;
		echo json_encode($result);
	} catch (Exception $e) {
		$temp = json_decode($e->getMessage(),true);
		$message = $temp['error']['message'];
		$code = $temp['error']['code'];
		if ($code == 400 && $message === 'Bad Request') {
			$result['error'] = $code . ' - ' . 'Bad Request.<br>Likely invalid javascript in uploaded files.';
		}
		else {
			$result['error'] = $code . ' - ' . $message;
		}
		$result['success'] = false;
		echo json_encode($result);
		exit;
  	}
}
