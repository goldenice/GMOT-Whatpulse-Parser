<?php
/*	GMOT BB-code Whatpulse stats parser
 *	Parses stats from the database into BBCode
 *
 *	Source, contributors, changelog and issues:
 *  - https://github.com/goldenice/GMOT-Whatpulse-Parser
 */

# display as plain text
header('Content-Type: text/plain');

# Defines
define('ROOT',              dirname(__FILE__));
define('ENDL',              "\r\n");
define('SECONDS_PER_DAY',   86400);

# Load configuration
require_once('config.php');
require_once('functions.php');

# get script hash (for mirror check)
if (isset($_GET['hash'])) {
    die(sha1Newline(file_get_contents(__FILE__)));
}

# PHP and content settings
$starttime = microtime(true);

if (DEVMODE || isset($_GET['devmode'])) {
    ini_set('display_errors',1);
    ini_set('display_startup_errors',1);
    error_reporting(-1);
    set_time_limit(60);
} else {
    // don't let non-devs use PHP < 5.4
    if (version_compare(PHP_VERSION, '5.4.0', '<')) {
        die('You need at least PHP 5.4 to run this script.' . ENDL . ENDL . 'Time to upgrade! :)');
    }
    
    error_reporting(0);
    set_time_limit(10);
}

# Automatic Class Loader
function autoClassLoader($class) {
    $path = ROOT . '/classes/'.$class.'.class.php';
    if (file_exists($path)) {
        include($path);
    } else {
        die('class ' . $class . ' @ ' . $path . ' not found');
    }
}

spl_autoload_register('autoClassLoader');

# Database Connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($db->connect_errno) {
    die('MySQL verbinding mislukt: ' . $db->connect_error);
}

# Script Settings
$teamtag 		= '[GMOT]'; // important for removing the team tag from the username.
$sourceUrl      = 'https://raw.githubusercontent.com/goldenice/GMOT-Whatpulse-Parser/master/bbcodegenerator.php';
$scriptUrls		= array(
    'https://rpi.ricklubbers.nl/sandbox/gmotwpstats/new/bbcodegenerator.php',
    'http://jochemkuijpers.nl/etc/gmot/whatpulsestats/bbcodegenerator.php',
    'http://private.woutervdb.com/php/gmotwpstats/bbcodegenerator.php',
    'http://squll.io/dev/gmot/wp/bbcodegenerator.php'
);
// $basedir 		= 'http://rpi.ricklubbers.nl/sandbox/gmotwpstats/new';
$rank_up_png    = 'http://is.gd/6aftPs';



# --------------------------------------------------------------------------------------------------------------------
# --------------------------------------------------------------------------------------------------------------------
# --------------------------------------------------------------------------------------------------------------------
# Let's start by gathering information!
#
# 1. gather some global stats
# 2. select all (per-user) data we need to build the scoreboard
# 3. calculate totals and rank offsets
# 4. determine which users have joined/left/returned
# --------------------------------------------------------------------------------------------------------------------
# --------------------------------------------------------------------------------------------------------------------
# --------------------------------------------------------------------------------------------------------------------



// warning when developer mode is enabled 	
if (DEVMODE || isset($_GET['devmode'])) { 	 	
    echo '!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!' . ENDL; 	
    echo '!!               DEVELOPER MODE IS ENABLED               !!' . ENDL; 	
    echo '!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!' . ENDL;
    echo '!!    OUTPUT MAY CONTAIN DEVELOPER DEBUG INFORMATION     !!' . ENDL; 	
    echo '!!        PHP WARNINGS OR INCORRECT INFORMATION          !!' . ENDL; 	
    echo '!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!' . ENDL . ENDL; 	
}


// stat timestamps (from - till)
$sql = '
SELECT
    timestamp
FROM
    `3_global`
ORDER BY
    `timestamp` DESC
LIMIT 3;';

$result = $db->query($sql);
$statsDateTill = $result->fetch_row()[0];
$statsDateFrom = $result->fetch_row()[0];
$statsDateYesterday = $result->fetch_row()[0];

// userdata
$sql = '
SELECT
    users.username,
    users.status,
    today.userid,
    today.rank,
    IFNULL(
        yesterday.rank,
        today.rank
    ) AS `oldrank`,
    today.keys,
    today.clicks,
    today.upload,
    today.download,
    today.uptime,
    today.upload + today.download           AS `bandwidth`,
    today.keys      - yesterday.keys        AS `keysDiff`,
    today.clicks    - yesterday.clicks      AS `clicksDiff`,
    today.upload    - yesterday.upload      AS `uploadDiff`,
    today.download  - yesterday.download    AS `downloadDiff`,
    today.uptime    - yesterday.uptime      AS `uptimeDiff`,
    today.download  - yesterday.download +
    today.upload    - yesterday.upload 	    AS `bandwidthDiff`,
    yesterday.lastpulse
FROM
    3_users AS users
LEFT JOIN 3_updates AS today
    ON today.userid = users.id
    AND today.seqnum = (SELECT MAX(seqnum) FROM 3_updates)
LEFT JOIN 3_updates AS yesterday
    ON yesterday.userid = users.id
    AND yesterday.seqnum = (SELECT MAX(seqnum) FROM 3_updates) - 1
WHERE
    users.status != "ex-member"
GROUP BY
    users.username
ORDER BY
    IFNULL(today.rank, yesterday.rank) ASC;';
    
$result = $db->query($sql);

$users = array();
$rankDelta = 0;

while ($userData = $result->fetch_assoc()) {
    
    // Get the amount of days since the last pulse that is not the pulse of today (yesterday.lastpulse)
    $userData['saverdays'] = max(0, ceil( ($statsDateFrom - $userData['lastpulse']) / SECONDS_PER_DAY ));
    // Someone's considdered a saver if yesterday.lastpulse was not yesterday.
    $userData['saver'] = ($userData['lastpulse'] < $statsDateYesterday);
    
    
    if ($userData['status'] == 'just-joined' || $userData['status'] == 'returned') {
        
        // if the user is inserted in the scoreboard (returning or new member), set his old rank to the current
        // rank and increase the rank offset for each following users.
        $users[] = new User($userData, $rankDelta);
        $rankDelta += 1;
        
    } else if ($userData['status'] == 'just-left') {
        
        // if the user has been removed from the scoreboard, the rank offset is decreased for the following users.
        $users[] = new User($userData, $rankDelta);
        $rankDelta -= 1;
        
    } else {
        
        // normal user: nothing special.
        $users[] = new User($userData, $rankDelta);
        
    }
    
}

// user that just left have a negative diff (because they had NULL today and a value yesterday)
// so the math should be correct.. (right?)
// while we're iterating through the users, also record events (users joining, leaving, etc.)

$events = array();
$statkeys = array('keys', 'clicks', 'upload', 'download', 'uptime', 'bandwidth');

foreach ($statkeys as $key) {
    $totals[$key] = 0;
    $totals[$key . 'Diff'] = 0;
    $highest[$key . 'Diff'] = 0;
}
$totals['savers'] = 0;
$totals['pulsers'] = 0;
$totals['active'] = 0;

foreach ($users as $user) {
    
    foreach ($statkeys as $key) {
        $totals[$key]           += $user->getRawData($key);
        $totals[$key . 'Diff']  += $user->getRawData($key . 'Diff');
        $highest[$key . 'Diff'] = max($highest[$key . 'Diff'], $user->getRawData($key . 'Diff'));
    }
    
    switch($user->getRawData('status')) {
        case 'just-joined':
            $events[] = new Event('Welkom %s! :D', $user);
            break;
        case 'returned':
            $events[] = new Event('Welkom terug %s! :D', $user);
            break;
        case 'just-left':
            $events[] = new Event('%s heeft besloten ons te verlaten :(', $user);
            break;
    }
    
    // count active users, pulsers and savers
    if ($user->isActive()) {
        
        $totals['active'] += 1;
        
        if ($user->hasPulsed()) {
            
            $totals['pulsers'] += 1;
            
            if ($user->isSaver()) {
                
                $totals['savers'] += 1;
            }
        }
    }
}

// now we're going to check whether or not the mirrors are up to date
$ownVersion = filemtime(__FILE__);
$mirrors = array();
$mirrorValidate = true;

$sourceStr = readExternalFile($sourceUrl);
$sourceHash = sha1Newline($sourceStr);

// if somehow github is down; don't bother validating
if ($sourceStr === false) {
    $mirrorValidate = false;
}

foreach($scriptUrls as $url) {
    $mirrors[] = new Mirror($url, $sourceHash);
}
shuffle($mirrors); // Because all mirrors are created equal.



# --------------------------------------------------------------------------------------------------------------------
# --------------------------------------------------------------------------------------------------------------------
# --------------------------------------------------------------------------------------------------------------------
# We now have all the information we need :)
#
# 5.  Determine which stats to use in the third column
# 6.  Display message heading
# 7.  Display scoreboard (with milestones)
# 8.  Display user events
# 9.  Display totals
# 10. Display links
# --------------------------------------------------------------------------------------------------------------------
# --------------------------------------------------------------------------------------------------------------------
# --------------------------------------------------------------------------------------------------------------------



// choose which stat to use in the third column.
if (date('z', $statsDateTill) % 2 == 0) {
    $thirdStat = 'uptime';
    $thirdStatHeading = 'Uptime';
} else {
    $thirdStat = 'bandwidth';
    $thirdStatHeading = 'Bandwidth';
}

// Milestones
$milestones = array(
    array('name' => 'Kryptonite', 'keyvalue' => 100000000),
    array('name' => 'Platina',    'keyvalue' =>  75000000),
    array('name' => 'Diamant',    'keyvalue' =>  50000000),
    array('name' => 'Titanium',   'keyvalue' =>  25000000),
    array('name' => 'Goud',       'keyvalue' =>  10000000),
    array('name' => 'Zilver',     'keyvalue' =>   7500000),
    array('name' => 'Brons',      'keyvalue' =>   5000000),
    array('name' => 'Metaal',     'keyvalue' =>   2500000),
    array('name' => 'Steen',      'keyvalue' =>   1000000),
    array('name' => 'Klei',       'keyvalue' =>    500000),
    array('name' => 'Kool',       'keyvalue' =>    250000),
    array('name' => 'Hout',       'keyvalue' =>    100000), 
    array('name' => 'N00b',       'keyvalue' =>        10)
);

// find first milestone
$milestoneIndex = 0;
$milestonePrint = true;

if (count($users) > 0) {
    
    while ($milestones[$milestoneIndex]['keyvalue'] > $users[0]->getRawData('keys') && $milestoneIndex < count($milestones) - 1) {
        $milestoneIndex += 1;
    }
    
} else {
    $milestoneIndex = count($milestones) - 1;
    $milestonePrint = false;
}

// message heading
echo '[b][size=14pt]Statistieken gegenereerd op ' . Format::DateTime($statsDateTill) . '[/size] ';
echo '(' . date("H:i:s", $statsDateTill) . ')' . ENDL;

echo 'Geteld vanaf ' . Format::DateTime($statsDateFrom) . ' ' . date('H:i:s', $statsDateFrom) . ENDL . ENDL;

// table heading
echo '[table][tr][td][b]#[/td][td][b]Gebruikersnaam[/td]';
echo '[td][b]Keys[/td][td][/td][td][b]Kliks[/td][td][/td]';
echo '[td][b]' . $thirdStatHeading . '[/td][td][/td][/tr]' . ENDL;

// table rows
foreach ($users as $user) {
    // do not display users that have left.
    if (!$user->isActive() || $user->getRawData('keys') < 10) { continue; }
    
    // determine if we need to print another milestone
    if ($milestoneIndex < count($milestones) - 1) {
        while ($milestones[$milestoneIndex]['keyvalue'] > $user->getRawData('keys') && $milestoneIndex < count($milestones) - 1) {
            $milestoneIndex += 1;
            $milestonePrint = true;
        }
    }
    
    // print a milestone if we have to
    if ($milestonePrint) {
        echo '[tr][td][/td][td][b]' . $milestones[$milestoneIndex]['name'] . '[/td]';
        echo '[td][right][b]' . Format::Number($milestones[$milestoneIndex]['keyvalue']) . '[/td][td][/td][td][/td][td][/td][td][/td][td][/td][/tr]' . ENDL;
        $milestonePrint = false;
    }
    
    
    // Start user row
    
    // 1st column: ranking
    
    
    echo '[tr][td]';
    
    $rank = $user->getRawData('rank');
    $rankDiff = $user->getRankDiff();
    
    // red or green text when rank has changed
    if ($rankDiff < 0) {
        echo '[green][abbr=+' . Format::Number(-$rankDiff) . ']'; 
    } elseif ($rankDiff > 0) {
        echo '[red][abbr=-' . Format::Number($rankDiff) . ']';
    }
    echo $rank . '[/td]';
    
    
    // 2nd column: username (and rank up icon)
    
    
    echo '[td]';
    
    // show rank up symbol if keys - keysDiff is smaller than the next milestone.
    if ($milestoneIndex < count($milestones) - 1) {
        if ($user->getRawData('keys') - $user->getRawData('keysDiff') < $milestones[$milestoneIndex]['keyvalue']) {
            echo '[img]' . $rank_up_png . '[/img] ';
        }
    }
    
    // show the username in green if the user has returned (re-joined after leaving)
    if ($user->getRawData('status') == 'returned') {
        echo '[abbr=The prodigal son has returned!][green]';
    }
    
    echo $user->getUsername() . '[/td]';
    
    
    // 3rd column: keys
    
    
    echo '[td][right]' . Format::StatNumber($user->getRawData('keys')) . '[/td][td]';
    
    // keysDiff value
    $keysDiff = $user->getRawData('keysDiff');
    
    if ($keysDiff > 0) {
        
        $saverdays = $user->getRawData('saverdays');
        $prefix = '';
        
        // cannot use StatNumber inside [abbr] tag
        if ($saverdays > 1) {
            $prefix .= '[abbr=Verdeeld over ' . Format::Number($saverdays) . ' dagen, ';
            $prefix .= 'gemiddeld ' . Format::Number($keysDiff / $saverdays) . ' keys per dag]';
        }
        
        if ($keysDiff == $highest['keysDiff']) {
            $prefix .= ' [blue]';
        } else {
            $prefix .= ' [green]';
        }
        echo $prefix . '+' . Format::StatNumber($keysDiff);
    }
    
    echo '[/td]';
    
    
    // 4th column: clicks
    
    
    echo '[td][right]' . Format::StatNumber($user->getRawData('clicks')) . '[/td][td]';
    
    // clicksDiff value
    $clicksDiff = $user->getRawData('clicksDiff');
    
    if ($clicksDiff > 0) {
        
        if ($clicksDiff == $highest['clicksDiff']) {
            $prefix = ' [blue]';
        } else {
            $prefix = ' [green]';
        }
        echo $prefix . '+' . Format::StatNumber($clicksDiff);
    }
    
    echo '[/td]';
    
    
    // 5th column: third stat
    
    echo '[td]';
    
    if ($thirdStat == 'uptime') {
        echo Format::Uptime($user->getRawData('uptime')) . '[/td][td]';
        
        $uptimeDiff = $user->getRawData('uptimeDiff');
        if ($uptimeDiff > 0) {
            if ($uptimeDiff == $highest['uptimeDiff']) {
                $prefix = ' [blue]';
            } else {
                $prefix = ' [green]';
            }
            echo $prefix . '+' . Format::Uptime($uptimeDiff);
        }
    } elseif ($thirdStat == 'bandwidth') {
        echo Format::Bandwidth($user->getRawData('bandwidth')) . '[/td][td]';
        
        $bandwidthDiff = $user->getRawData('bandwidthDiff');
        if ($bandwidthDiff > 0) {
            if ($bandwidthDiff == $highest['bandwidthDiff']) {
                $prefix = ' [blue]';
            } else {
                $prefix = ' [green]';
            }
            echo $prefix . '+' . Format::Bandwidth($bandwidthDiff);
        }
    }
    
    
    echo '[/td][/tr]' . ENDL;
    
    
}

echo '[/table]' . ENDL . ENDL;

// end of table

// display events

foreach ($events as $event) {
    echo $event->getString() . ENDL;
}

if (count($events) > 0) {
    echo ENDL;
}

// display totals

echo '[b]Totalen[/b]' . ENDL;
echo '[table]';

echo '[tr][td]Keys [/td]';
echo '[td]' . Format::StatNumber($totals['keys']) . '[tt]    [/tt][/td]';
echo '[td]' . (($totals['keysDiff'] > 0)?'[green]+':'[red]-') . Format::StatNumber($totals['keysDiff']) . '[/td][/tr]' . ENDL;

echo '[tr][td]Kliks [/td]';
echo '[td]' . Format::StatNumber($totals['clicks']) . '[/td]';
echo '[td]' . (($totals['clicksDiff'] > 0)?'[green]+':'[red]-') . Format::StatNumber($totals['clicksDiff']) . '[/td][/tr]' . ENDL;

echo '[tr][td]Uptime [/td]';
echo '[td]' . Format::Uptime($totals['uptime']) . '[/td]';
echo '[td]' . (($totals['uptimeDiff'] > 0)?'[green]+':'[red]-') . Format::Uptime($totals['uptimeDiff']) . '[/td][/tr]' . ENDL;

echo '[tr][td]Download [/td]';
echo '[td]' . Format::Bandwidth($totals['download']) . '[/td]';
echo '[td]' . (($totals['downloadDiff'] > 0)?'[green]+':'[red]-') . Format::Bandwidth($totals['downloadDiff']) . '[/td][/tr]' . ENDL;

echo '[tr][td]Upload [/td]';
echo '[td]' . Format::Bandwidth($totals['upload']) . '[/td]';
echo '[td]' . (($totals['uploadDiff'] > 0)?'[green]+':'[red]-') . Format::Bandwidth($totals['uploadDiff']) . '[/td][/tr]' . ENDL;

if ($totals['pulsers'] > 0) {
    
    echo '[tr][td]Pulsers[/td][td]' . $totals['pulsers'] . '[/td]';
    echo '[td][abbr=Percentage van alle leden]' . round(($totals['pulsers'] / $totals['active']) * 100, 2).'%[/td][/tr]' . ENDL;
    
    echo '[tr][td]Spaarders[tt]    [/tt][/td][td]' . $totals['savers'] . '[/td]';
    echo '[td][abbr=Percentage van de pulsers]' . round(($totals['savers'] / $totals['pulsers']) * 100, 2).'%[/td][/tr]' . ENDL;
    
}

echo '[/table]' . ENDL . ENDL;

echo Mirror::getMirrorsBBcode($mirrors);

echo '[/sup] ([url=https://github.com/goldenice/GMOT-Whatpulse-Parser]Broncode[/url])' . ENDL . ENDL;

if (DEVMODE || isset($_GET['devmode']) || isset($_GET['gentime'])) {
    echo 'Generated in ' . ((microtime(true) - $starttime) * 1000) . ' milliseconds.';
}
