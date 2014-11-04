<?php

// hashes the string in sha1 using \n as newlines
function sha1Newline($str) {
    return sha1(str_replace(array("\r", "\r\n"), "\n", $str));
}

// return file with 1 second timeout or return false
function readExternalFile($url) {
    $handle = curl_init();
    $headers = array(
        'User-Agent' => 'GMOT-Whatpulse-Parser/1.0 just for logs (requested by ' . $_SERVER['REMOTE_ADDR'] . ' at ' . time() . ')',
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
    curl_close($handle);
    
    if (DEVMODE || isset($_GET['devmode'])) {
        if ($response === false) {
            echo '   ... failed (timeout of 1 second?)' . ENDL . ENDL;
        } else {
            echo '   ... success (' . strlen($response) . ' characters, ' . round((microtime(true) - $start) * 1000, 2) . 'ms)' . ENDL . ENDL;
        }
    }
    
    return $response;
}
