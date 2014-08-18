<?php
/*
 *	Various functions for the whatpulse bbcode-parser
 *	
 *	Written by Rick Lubbers, and stole some functions from Lucb1e (http://lucb1e.com/)
*/


// Check if someone requested the source (even this piece of code is stolen!)
if (isset($_GET["source"])) {
    highlight_file(__FILE__);
    exit;
}

// Function for correct formatting of numbers
function formatNumber($number, $decimals = 0) { 
	if (defined('BASE') and BASE != 0) {
		return str_replace("9.999.999", "[abbr=Sjongejonge, er kan tegenwoordig geen key meer vanaf hè... De jeugd van tegenwoordig...]9.999.999[/abbr]",  
                    preg_replace("/^1\\.337$/", "[abbr=1337][b]leet[/b][/abbr]", preg_replace("/^1\\,337$/", "[abbr=1337][b]leet[/b][/abbr]", 
                        str_replace("1.787.569", "[abbr=1.787.569 ofwel 1337²][b]leet^2!!![/b][/abbr]", 
                            number_format(round(($number/BASE), 0), $decimals, ",", "."))))).' × '.BASE; 
	}
	else {
        return str_replace("9.999.999", "[abbr=Sjongejonge, er kan tegenwoordig geen key meer vanaf hè... De jeugd van tegenwoordig...]9.999.999[/abbr]",  
                    preg_replace("/^1\\.337$/", "[abbr=1337][b]leet[/b][/abbr]", preg_replace("/^1\\,337$/", "[abbr=1337][b]leet[/b][/abbr]", 
                        str_replace("1.787.569", "[abbr=1.787.569 ofwel 1337²][b]leet^2!!![/b][/abbr]", 
                            number_format($number, $decimals, ",", "."))))); 
	}
}

// Function to generate a proper date from a timestamp (with dutch month- and daynames)
function generateDatetime($timestamp) { 
    $dagen[1] = "maandag"; 
    $dagen[2] = "dinsdag"; 
    $dagen[3] = "woensdag"; 
    $dagen[4] = "donderdag"; 
    $dagen[5] = "vrijdag"; 
    $dagen[6] = "zaterdag"; 
    $dagen[7] = "zondag"; 

    $maanden[1] = "januari"; 
    $maanden[2] = "februari"; 
    $maanden[3] = "maart"; 
    $maanden[4] = "april"; 
    $maanden[5] = "mei"; 
    $maanden[6] = "juni"; 
    $maanden[7] = "juli"; 
    $maanden[8] = "augustus"; 
    $maanden[9] = "september"; 
    $maanden[10] = "oktober"; 
    $maanden[11] = "november"; 
    $maanden[12] = "december"; 
      
    return $dagen[date("N", $timestamp)] . date(" j ", $timestamp) . $maanden[date("n", $timestamp)] . date(" Y", $timestamp); 
}

// Function to execute a MySQL query
function q($query) {
	global $db;
	return mysqli_query($db, $query);
}

// Function to get an associative array from MySQL result
function fetchAssoc($input) {
	global $db;
	return mysqli_fetch_assoc($input);
}

// Helps sorting the user array, based on keys 
function arraySortCmp($a, $b) {
	return $b["keys"] - $a["keys"];
}

// Get a human-readable uptime format
function generateUptime($input) {
	$yearsec 	= 60*60*24*365;
	$monthsec 	= 60*60*24*31;
	$weeksec 	= 60*60*24*7;
	$daysec		= 60*60*24;
	$hoursec	= 60*60;
	$minsec		= 60;
	$output 	= '';
	$years 	= 0;
	$months = 0;
	$weeks 	= 0;
	$days 	= 0;
	$hours 	= 0;
	$mins 	= 0;
	$types  = 0;
	while ($input >= $yearsec) {
		$years++;
		$input -= $yearsec;
	}
	if ($years > 0) {
		$output .= $years.'y';
		$types++;
	}
	while ($input >= $monthsec) {
		$months++;
		$input -= $monthsec;
	}
	if ($months > 0 or strlen($output) > 0) {
		$output .= $months.'m';
		$types++;
	}
	while ($input >= $weeksec) {
		$weeks++;
		$input -= $weeksec;
	}
	if ($weeks > 0 or strlen($output) > 0) {
		if ($types >= 2) {
			if ($input > ($weeksec/2)) {
				$weeks++;
			}
		}
		$output .= $weeks.'w';
		$types++;
		if ($types > 2) {
			return $output;
		}
	}
	while ($input >= $daysec) {
		$days++;
		$input -= $daysec;
	}
	if ($days > 0 or strlen($output) > 0) {
		if ($types >= 2) {
			if ($input > ($daysec/2)) {
				$days++;
			}
		}
		$output .= $days.'d';
		$types++;
		if ($types > 2) {
			return $output;
		}
	}
	while ($input >= $hoursec) {
		$hours++;
		$input -= $hoursec;
	}
	if ($hours > 0 or strlen($output) > 0) {
		if ($types >= 2) {
			if ($input > ($hoursec/2)) {
				$hours++;
			}
		}
		$output .= $hours.'h';
		$types++;
		if ($types > 2) {
			return $output;
		}
	}
	while ($input >= $minsec) {
		$mins++;
		$input -= $minsec;
	}
	if ($mins > 0 or strlen($output) > 0) {
		if ($types >= 2) {
			if ($input > ($minsec/2)) {
				$mins++;
			}
		}
		$output .= $mins.'m';
		$types++;
		if ($types > 2) {
			return $output;
		}
	}
	if ($types == 0) {
		echo '-';
	}
	return $output;
}

// Function to get a human-readable bandwidth
function generateBandwidth($input) {
	$terabyte	= 1024*1024;
	$gigabyte	= 1024;
	$output 	= '';
	if ($input >= ($terabyte/10)) {
		return sprintf("%.2f", round($input/$terabyte, 2)).' TB';
	}
	elseif ($input >= ($gigabyte/10)) {
		return sprintf("%.2f", round($input/$gigabyte, 2)).' GB';
	}
	else {
		return sprintf("%.2f", round($input, 2)).' MB';
	}
}

// Randomize assoc array
function shuffle_assoc(&$array) {
    $keys = array_keys($array);
    shuffle($keys);
    foreach($keys as $key) {
        $new[$key] = $array[$key];
    }
    return $new;
}


