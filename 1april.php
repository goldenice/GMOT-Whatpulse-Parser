<?php
/*	GMOT BB-code Whatpulse stats parser
 *	Parses stats from the database into BBCode
 *			Version: 1.0	
 *
 *	Written by Rick Lubbers
 *	Special thanks to Lucb1e (I stole some code from the old parser)
 *
 *	------------
 *	Known bugs
 *	- When a player leaves, everyone below them goes a rank up
 *	- When 2 people have the highest amount of a stat, only one gets the blue color (a part of the script has to be rewritten to fix this one)
 *	- The 'spaarders' are not being count correctly
 *
 *	------------
 *	Changelog
 *	2013-03-07: Added the 'milestone up'-icon
 *	2013-03-07: Probably fixed the just-left-rank-up bug. Testing tomorrow.
 *
 *
 *
 */


// Charset headers xD
header('Content-Type: text/html; charset=UTF-8');

// Script settings
$dbhost 		= 'lucb1e.com';
$dbport 		= 28;
require_once('sql.php');
$dbname 		= 'whatpulsestats';
$teamtag 		= '[GMOT]';
$scripturl 		= 'https://rpi.ricklubbers.nl/sandbox/gmotwpstats/1april.php';
$basedir 		= 'https://rpi.ricklubbers.nl/sandbox/gmotwpstats/';

// If it is not the first of april
if (date("d-m") != '01-04' and date("d-m") != '02-04') {
	die('Nice try...');
}

// Check if someone requested the source
if (isset($_GET["source"])) {
	highlight_file(__FILE__);
	exit;
}

$starttime = microtime(true);
set_time_limit(10);

// Setup database connections
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname, $dbport);

// Get some functions loaded
require_once('functions.php');

// Get base for the numbers; Luc's postcount
$gmotraw = file_get_contents('http://www.gmot.nl/index.php?action=profile;u=852');
$base = explode(' ', explode('<td><b>Berichten: </b></td><td>', str_replace(array("\t", "\n", "\r"), '', $gmotraw))[1])[0];
define('BASE', str_replace('.', '', $base));

// Init user array
$user = array();

// Init highest pulse stuff and total stats
$highestpulse['keys']['count']			= 0;
$highestpulse['keys']['user']			= '';
$highestpulse['clicks']['count']		= 0;
$highestpulse['clicks']['user']			= '';
$highestpulse['uptime']['count']		= 0;
$highestpulse['uptime']['user']			= '';
$highestpulse['upload']['count']		= 0;
$highestpulse['upload']['user']			= '';
$highestpulse['download']['count']		= 0;
$highestpulse['download']['user']		= '';
$teamstats['keys'] 			= 0;
$teamstats['clicks']	 	= 0;
$teamstats['uptime'] 		= 0;
$teamstats['download'] 		= 0;
$teamstats['upload'] 		= 0;
$teamstats['keysdiff'] 		= 0;
$teamstats['clicksdiff'] 	= 0;
$teamstats['uptimediff'] 	= 0;
$teamstats['downloaddiff'] 	= 0;
$teamstats['uploaddiff'] 	= 0;
$teamstats['memberspulsed']	= 0;
$teamstats['spaarders'] 	= 0;

// Load all the users from database with all possible info
$userquery = q("SELECT * FROM `3_updates` WHERE seqnum = (SELECT MAX(seqnum) FROM `3_updates`)");
while ($thisuser = fetchAssoc($userquery)) {
	global $user;
	$user[$thisuser['rank']] = $thisuser;
	$userdatabasedata = fetchAssoc(q("SELECT * FROM `3_users` WHERE `id`='".$thisuser['userid']."'"));
	$user[$thisuser['rank']]['username'] = trim(str_ireplace($teamtag, '', $userdatabasedata['username']));
	$user[$thisuser['rank']]['status'] = $userdatabasedata['status'];
	if ($userdatabasedata['status'] != 'just-joined') {		// If user was in the team yesterday
		$thisuseryesterdayq = q("SELECT * FROM 3_updates WHERE userid='".$thisuser['userid']."' ORDER BY seqnum DESC");
		$thisuseryesterday = fetchAssoc($thisuseryesterdayq);
		$thisuseryesterday = fetchAssoc($thisuseryesterdayq);
		$user[$thisuser['rank']]['keysdiff'] 		= $thisuser['keys']-$thisuseryesterday['keys'];
		$user[$thisuser['rank']]['clicksdiff'] 		= $thisuser['clicks']-$thisuseryesterday['clicks'];
		$user[$thisuser['rank']]['uploaddiff'] 		= $thisuser['upload']-$thisuseryesterday['upload'];
		$user[$thisuser['rank']]['downloaddiff'] 	= $thisuser['download']-$thisuseryesterday['download'];
		$user[$thisuser['rank']]['uptimediff'] 		= $thisuser['uptime']-$thisuseryesterday['uptime'];
		$user[$thisuser['rank']]['oldrank'] 		= $thisuseryesterday['rank'];
		$user[$thisuser['rank']]['oldlastpulse'] 	= $thisuseryesterday['lastpulse'];
	}
	else {		// If user is new today
		$user[$thisuser['rank']]['keysdiff'] = $thisuser['keys'];
		$user[$thisuser['rank']]['clicksdiff'] = $thisuser['clicks'];
		$user[$thisuser['rank']]['uploaddiff'] = $thisuser['upload'];
		$user[$thisuser['rank']]['downloaddiff'] = $thisuser['download'];
		$user[$thisuser['rank']]['uptimediff'] = $thisuser['uptime'];
	}
	
	// Check highest pulse stuff
	if ($highestpulse['keys']['count'] < $user[$thisuser['rank']]['keysdiff']) {
		$highestpulse['keys']['count'] 		= $user[$thisuser['rank']]['keysdiff'];
		$highestpulse['keys']['user'] 		= $user[$thisuser['rank']]['username'];
	}
	if ($highestpulse['clicks']['count'] < $user[$thisuser['rank']]['clicksdiff']) {
		$highestpulse['clicks']['count'] 		= $user[$thisuser['rank']]['clicksdiff'];
		//$highestpulse['clicks']['user'] 		= $user[$thisuser['rank']]['username'];
	}
	if ($highestpulse['uptime']['count'] < $user[$thisuser['rank']]['uptimediff']) {
		$highestpulse['uptime']['count'] 		= $user[$thisuser['rank']]['uptimediff'];
		//$highestpulse['uptime']['user'] 		= $user[$thisuser['rank']]['username'];
	}
	if ($highestpulse['upload']['count'] < $user[$thisuser['rank']]['uploaddiff']) {
		$highestpulse['upload']['count'] 		= $user[$thisuser['rank']]['uploaddiff'];
		//$highestpulse['upload']['user'] 		= $user[$thisuser['rank']]['username'];
	}
	if ($highestpulse['download']['count'] < $user[$thisuser['rank']]['downloaddiff']) {
		$highestpulse['download']['count'] 		= $user[$thisuser['rank']]['downloaddiff'];
		//$highestpulse['download']['user'] 		= $user[$thisuser['rank']]['username'];
	}
	
	// Update team (total) stats
	$teamstats['keys'] 			+= $user[$thisuser['rank']]['keys'];
	$teamstats['clicks']	 	+= $user[$thisuser['rank']]['clicks'];
	$teamstats['uptime'] 		+= $user[$thisuser['rank']]['uptime'];
	$teamstats['download'] 		+= $user[$thisuser['rank']]['download'];
	$teamstats['upload'] 		+= $user[$thisuser['rank']]['upload'];
	$teamstats['keysdiff'] 		+= $user[$thisuser['rank']]['keysdiff'];
	$teamstats['clicksdiff'] 	+= $user[$thisuser['rank']]['clicksdiff'];
	$teamstats['uptimediff'] 	+= $user[$thisuser['rank']]['uptimediff'];
	$teamstats['downloaddiff'] 	+= $user[$thisuser['rank']]['downloaddiff'];
	$teamstats['uploaddiff'] 	+= $user[$thisuser['rank']]['uploaddiff'];
}

// Obsolete in this version
//usort($user, "arraySortCmp");
$user = shuffle_assoc($user);

// Fix the 'just-left' bug (other members won't go rank-up if someone leaves)
$fixrank = array();
$totalusers = sizeOf($user);
for($i=1;$i<=$totalusers;$i++) {
	$fixrank[$i] = 0;
}
$usersleft = q("SELECT * FROM `3_users` WHERE `status`='just-left'");
while($a = fetchAssoc($usersleft)) {
	$tuser = fetchAssoc(q("SELECT * FROM `3_updates` WHERE `userid`='".$a['id']."' ORDER BY `seqnum` DESC LIMIT 0,1"));
	
	for($i=$tuser['rank'];$i<=$totalusers;$i++) {
		$fixrank[$i]++;
	}
}


$stattimes = q("SELECT timestamp FROM `3_global` ORDER BY `timestamp` DESC");
$statstill = fetchAssoc($stattimes)['timestamp'];
$statsfrom = fetchAssoc($stattimes)['timestamp'];
echo '[b][size=14pt]Statistieken gegenereerd op '.generateDateTime($statstill).'[/size] ('.date("H:i:s", $statstill).')'.'<br />';
echo 'Geteld vanaf '.generateDateTime($statsfrom).' '.date('H:i:s', $statsfrom).'<br /><br />';

// Determine if we're going to use network or uptime stats
if (date('z', $statstill) % 2 == 0) {		// If day of the year is even...
	$thirdstat = 'uptime';
	$thirdstath = 'Uptime';
}
else {
	$thirdstat = 'network';
	$thirdstath = 'Download';
}

// Milestone stuff
$milestone = array( 
        array('name' => 'N00b',       'keyvalue' =>        10), 
        array('name' => 'Hout',       'keyvalue' =>    100000), 
        array('name' => 'Kool',       'keyvalue' =>    250000), 
        array('name' => 'Klei',       'keyvalue' =>    500000), 
        array('name' => 'Steen',      'keyvalue' =>   1000000), 
        array('name' => 'Metaal',     'keyvalue' =>   2500000), 
        array('name' => 'Brons',      'keyvalue' =>   5000000), 
        array('name' => 'Zilver',     'keyvalue' =>   7500000), 
        array('name' => 'Goud',       'keyvalue' =>  10000000), 
        array('name' => 'Titanium',   'keyvalue' =>  25000000), 
        array('name' => 'Diamant',    'keyvalue' =>  50000000), 
        array('name' => 'Platina',    'keyvalue' =>  75000000), 
        array('name' => 'Kryptonite', 'keyvalue' => 100000000) 
); 
$curmilst = count($milestone) - 1;

// Show table header
echo '[table][tr][td][b]#[/td][td][b]Gebruikersnaam[/td][td][b]Keys[/td][td][b]Kliks[/td][td][b]'.ucfirst($thirdstath).'[/td][/tr]';

// Loop through the list of users
foreach($user as $k=>$v) {
	global $curmilst;
	$userextrakeysbtag = '';
	$userextrakeysendadd = '';
	
	// Check if new milestone should be displayed
/*	$milechange = false;
	while ($user[$k]['keys'] < $milestone[$curmilst]['keyvalue']) {
		$curmilst--;
		$milechange = true;
	}
	if ($curmilst >= 0 && $milechange == true) {
		echo "<br />";
		echo '[tr][td][/td][td][b]'.$milestone[$curmilst]['name'].'[/td][td][b]'.formatNumber($milestone[$curmilst]['keyvalue']).'[/td][td][/td][td][/td][/tr]';
	}*/
	if(rand(1, 8) == 1) {
		$curmilst = rand(0, 12);
		echo '<br />[tr][td][/td][td][b]'.$milestone[$curmilst]['name'].'[/td][td][b]'.formatNumber($milestone[$curmilst]['keyvalue']).'[/td][td][/td][td][/td][/tr]';
	}
	
	if (trim(strtolower($v['username'])) == 'lucb1e') {
		continue;
	}
	
	// Display user row
	echo '<br />';
	echo '[tr][td]';
	if ($v['oldrank'] == 0) {
		$v['oldrank'] = sizeOf($user);
	}
	$v['oldrank'] -= $fixrank[($k+1)];
/*	if ((($k+1) - $v['oldrank']) < 0) {
		echo '[green][abbr=+'.($v['oldrank']-($k+1)).']';
	}
	elseif ((($k+1) - $v['oldrank']) > 0) {
		echo '[red][abbr='.($v['oldrank']-($k+1)).']';
	}*/
	echo ($k+1).' [/td][td]';
	if (rand(1, 20) == 1) {
		echo '[img]'.$basedir.'rank_up.png[/img] ';
	}
	echo trim(str_replace('superice', '[abbr=Super-ice that is!][red]su[/red][color=orange]pe[/color][green]ri[/green][blue]ce[/blue][/abbr]', str_replace(":D", ":[b][/b]D", str_replace("[", "[[b][/b]", $v['username']))).' [/td][td]'.formatNumber($v['keys']));
	if ($v['keysdiff'] > 0) {
		$teamstats['memberspulsed']++;
		$v['saveddays'] = ceil(($statfrom - $v['oldlastpulse'])/(24*60*60));
		//echo ' '.($statsfrom - $v['oldlastpulse']);
		if ($v['saveddays'] > 1) {
			$userextrakeysbtag = '[abbr=Verdeeld over '.$v['saveddays'].' dagen, gemiddeld '.formatNumber(round(($v['keysdiff']/$v['saveddays']))).' keys per dag]';
			$userextrakeysendadd = '*';
			$teamstats['spaarders']++;
		}
		if ($v['keysdiff'] == $highestpulse['keys']['count']) {
			echo ' [blue]';
		}
		else {
			echo ' [green]';
		}
		echo $userextrakeysbtag.'+'.formatNumber($v['keysdiff']).$userextrakeysendadd;
	}
	echo ' [/td][td]'.formatNumber($v['clicks']);
	if ($v['clicksdiff'] > 0) {
		if ($v['clicksdiff'] == $highestpulse['clicks']['count']) {
			echo ' [blue]';
		}
		else {
			echo ' [green]';
		}
		echo '+'.formatNumber($v['clicksdiff']);
	}
	echo ' [/td][td]';
	if ($thirdstat == 'uptime') {
		echo generateUptime($v['uptime']);
		if ($v['uptimediff'] > 0) {
			if ($v['uptimediff'] == $highestpulse['uptime']['count']) {
				echo ' [blue]';
			}
			else {
				echo ' [green]';
			}
			echo '+'.generateUptime($v['uptimediff']);
		}
	}
	else {
		echo generateBandwidth($v['download']);
		if ($v['downloaddiff'] > 0 or $v['uploaddiff'] > 0) {
			if ($v['downloaddiff'] == $highestpulse['download']['count']) {
				$btag = '[blue]';
				$etag = '[/blue]';
			}
			else {
				$btag = '[green]';
				$etag = '[/green]';
			}
			echo ' '.$btag.'+'.generateBandwidth($v['downloaddiff']).$etag;
		}
	}
	echo '[/td][/tr]';
}
echo '<br /><br />';

// Get users who joined or left the team
$leftjoined = q("SELECT * FROM `3_users` WHERE `status`='just-joined' OR `status`='just-left'");
while($a = fetchAssoc($leftjoined)) {
	if ($a['status'] == 'just-joined') {
		echo 'Welkom '.trim(str_ireplace($teamtag, '', $a['username'])).'! :D';
	}
	else {
		echo trim(str_ireplace($teamtag, '', $a['username'])).' heeft besloten ons te verlaten :(';
	}
	echo '<br />';
}

echo '<br /><br />';
// Display total stats
echo '[b]Totalen[/b]';
echo '[table]';
echo '[tr][td]Keys [/td][td]'.formatNumber($teamstats['keys']).'[/td][td]'.(($teamstats['keysdiff'] >= 0) ? '[green]+' : '[red]-').formatNumber($teamstats['keysdiff']).'[/td][/tr]';
echo '[tr][td]Clicks [/td][td]'.formatNumber($teamstats['clicks']).'[/td][td]'.(($teamstats['clicksdiff'] >= 0) ? '[green]+' : '[red]-').formatNumber($teamstats['clicksdiff']).'[/td][/tr]';
echo '[tr][td]Uptime [/td][td]'.generateUptime($teamstats['uptime']).'[/td][td]'.(($teamstats['uptimediff'] >= 0) ? '[green]+' : '[red]-').generateUptime($teamstats['uptimediff']).'[/td][/tr]';
echo '[tr][td]Download [/td][td]'.generateBandwidth($teamstats['download']).'[/td][td]'.(($teamstats['downloaddiff'] >= 0) ? '[green]+' : '[red]-').generateBandwidth($teamstats['downloaddiff']).'[/td][/tr]';
echo '[tr][td]Upload [/td][td]'.generateBandwidth($teamstats['upload']).'[/td][td]'.(($teamstats['uploaddiff'] >= 0) ? '[green]+' : '[red]-').generateBandwidth($teamstats['uploaddiff']).'[/td][/tr]';
//echo '[tr][td]-[/td][td] [/td][td] [/td][/tr]';
//echo '[tr][td]Spaarders[/td][td]'.$teamstats['spaarders'].'[/td][td][abbr=Van de mensen die pulsten]'.round(($teamstats['spaarders']/$teamstats['memberspulsed'])*100, 2).'%[/abbr][/td][/tr]';
echo '[/table]';

echo '<br /><br />';
echo '[url='.$scripturl.']Deze statistieken[/url] ([url='.$scripturl.'?source]Broncode[/url])';
exit();
