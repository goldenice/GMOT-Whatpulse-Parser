<?php

// hashes the string in sha1 using \n as newlines
function sha1Newline($str) {
    return sha1(str_replace(array("\r", "\r\n"), "\n", $str));
}

// return file with 1 second timeout or return false
function readExternalFile($url) {
    $handle = curl_init();
    
    $addr = '';
    if (isset($_SERVER['REMOTE_ADDR'])) {
        $addr = $_SERVER['REMOTE_ADDR'];
    } else {
        $addr = gethostname() . ' (' . gethostbyname(gethostname()) . ')';
    }
    
    $headers = array(
        'User-Agent' => 'GMOT-Whatpulse-Parser/1.0 just for logs (requested by ' . $addr . ' at ' . time() . ')',
    );
    
    if (DEVMODE || isset($_GET['devmode'])) {
        echo 'Requesting external file (' . $url . ') ...' . ENDL;
        $start = microtime(true);
    }
    
    curl_setopt($handle, CURLOPT_URL, $url);
    curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($handle, CURLOPT_TIMEOUT, 1);
    $response = curl_exec($handle);
	$responsecode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
	if($responsecode != 200) {
        echo '   ... failed (fetch returned error ' . $responsecode . ')' . ENDL . ENDL;
		return false;
		curl_close($handle);
	}
    if (DEVMODE || isset($_GET['devmode'])) {
		echo '   ... success (' . strlen($response) . ' characters, ' . round((microtime(true) - $start) * 1000, 2) . 'ms) ' . ENDL . ENDL;
    }
    curl_close($handle);
    
    return $response;
}
function bg_process($fn, $arr) {
	if (DEVMODE || isset($_GET['devmode'])) {
		call_user_func ($fn, $arr);
	}
	else
	{
		$call = function($fn, $arr){
			header('Connection: close');
			header('Content-length: '.ob_get_length());
			ob_flush();
			flush();
			call_user_func_array($fn, $arr);
			};
		register_shutdown_function($call, $fn, $arr);
	}
}