<?php
/*	GMOT BB-code Whatpulse stats parser
 *	Parses stats from the database into BBCode
 *			Version: 1.1
 *
 *  Rewritten by Jochem Kuijpers
 *	Originally written by Rick Lubbers
 *	Special thanks to Lucb1e (I stole some code from the old parser) and to any other person who helped me code this.
 *
 *	------------
 *	Known bugs
 *	- None so far (report on GitHub)
 *
 *	------------
 *	Changelog
 *	2013-03-07: Added the 'milestone up'-icon
 *	2013-03-07: Probably fixed the just-left-rank-up bug. Testing tomorrow.
 *	2013-03-26: Thanks to Ericlegomeer for giving me the solution to the multiple-guys-have-highest-pulse-but-do-not-show-up-in-blue-bug.
 *	2013-08-17: Removed SQL-query from loop, so the loadtime improved a lot (approx. 26s to 800ms)
 * 	2013-10-29: Added [nobbc] tags around usernames, so users can't use bbcode or smileys anymore.
 *	2014-03-03: Fixed bug that caused new users not to be shown on the first day
 *	2014-06-?: Rank displaymode has been changed so the numbers are in a more logical order at the milestones.
 *	2014-06-09: turned off E_NOTICE error reports
 *	2014-08-18: fixed bug where someone that rejoins the team would fuck up the rankings. Oh, and added a welcome back text.
 *	2014-09-14: Rewritten by Jochem Kuijpers hopefully fixing the savers (spaarders) bug and making the code more easily maintanable. Load time is about 270ms now.
 */

# Defines
define('ROOT',              dirname(__FILE__));
define('ENDL',              "\r\n");
define('DEVMODE',           false);
define('SECONDS_PER_DAY',   86400);

# PHP and content settings
$starttime      = microtime(true);
if (DEVMODE) {
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

header('Content-Type: text/plain');

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
require_once('db.php');
$db = new mysqli($dbhost, $dbuser, $dbpass, $dbname, $dbport);

# Script Settings
$teamtag 		= '[GMOT]'; // important for removing the team tag from the username.
$scripturl 		= 'http://rpi.ricklubbers.nl/sandbox/gmotwpstats/new/bbcodegenerator.php';
$basedir 		= 'http://rpi.ricklubbers.nl/sandbox/gmotwpstats';



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
        ( SELECT t.rank
        FROM 3_updates AS t
        WHERE t.userid = today.userid
        AND t.seqnum < today.seqnum
        ORDER BY t.seqnum DESC
        LIMIT 1)
    ) AS `oldrank`,
    today.keys,
    today.clicks,
    today.upload,
    today.download,
    today.uptime,
    today.upload + today.download           AS `bandwidth`,
    today.keys      - yesterday.keys        AS `keysdiff`,
    today.clicks    - yesterday.clicks      AS `clicksdiff`,
    today.upload    - yesterday.upload      AS `uploaddiff`,
    today.download  - yesterday.download    AS `downloaddiff`,
    today.uptime    - yesterday.uptime      AS `uptimediff`,
    today.download  - yesterday.download +
    today.uptime    - yesterday.uptime 	    AS `bandwidthdiff`,
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
    $totals[$key . 'diff'] = 0;
    $highest[$key . 'diff'] = 0;
}
$totals['savers'] = 0;
$totals['pulsers'] = 0;

foreach ($users as $user) {
    
    foreach ($statkeys as $key) {
        $totals[$key]           += $user->getRawData($key);
        $totals[$key . 'diff']  += $user->getRawData($key . 'diff');
        $highest[$key . 'diff'] = max($highest[$key . 'diff'], $user->getRawData($key . 'diff'));
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
    
    // count active users (users who pulsed this day)
    if ($user->getRawData('keysdiff') > 0 || $user->getRawData('clicksdiff') > 0) {
        $totals['pulsers'] += 1;
    }
    
    // get saver days if the user is a saver
    if ($user->isSaver() && $user->hasPulsed()) {
        $totals['savers'] += 1;
    }
}



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
echo '[table][tr][td][b]#[/td][td][b]Gebruikersnaam[/td][td][b]Keys[/td][td][b]Kliks[/td]';
echo '[td][b]' . $thirdStatHeading . '[/td][/tr]' . ENDL;

// table rows
foreach ($users as $user) {
    // do not display users that have left.
    if (!$user->isActive()) { continue; }
    
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
        echo '[td][b]' . Format::Number($milestones[$milestoneIndex]['keyvalue']) . '[/td][td][/td][td][/td][/tr]' . ENDL;
        $milestonePrint = false;
    }
    
    
    // Start user row
    
    // 1st column: ranking
    
    
    echo '[tr][td]';
    
    $rank = $user->getRawData('rank');
    $rankdiff = $user->getRankDiff();
    
    // red or green text when rank has changed
    if ($rankdiff < 0) {
        echo '[green][abbr=+' . Format::Number(-$rankdiff) . ']'; 
    } elseif ($rankdiff > 0) {
        echo '[red][abbr=-' . Format::Number($rankdiff) . ']';
    }
    echo $rank . '[/td]';
    
    
    // 2nd column: username (and rank up icon)
    
    
    echo '[td]';
    
    // show rank up symbol if keys - keysdiff is smaller than the next milestone.
    if ($milestoneIndex < count($milestones) - 2) {
        if ($user->getRawData('keys') - $user->getRawData('keysdiff') < $milestones[$milestoneIndex + 1]['keyvalue']) {
            echo '[img]' . $basedir . '/rank_up.png[/img]';
        }
    }
    
    // show the username in green if the user has returned (re-joined after leaving)
    if ($user->getRawData('status') == 'returned') {
        echo '[abbr=The prodigal son has returned!][green]';
    }
    
    echo $user->getUsername() . '[/td]';
    
    
    // 3rd column: keys
    
    
    echo '[td]' . Format::StatNumber($user->getRawData('keys'));
    
    // keysdiff value
    $keysdiff = $user->getRawData('keysdiff');
    
    if ($keysdiff > 0) {
        
        $saverdays = $user->getRawData('saverdays');
        $prefix = '';
        
        // cannot use StatNumber inside [abbr] tag
        if ($saverdays > 1) {
            $prefix .= '[abbr=Verdeeld over ' . Format::Number($saverdays) . ' dagen, ';
            $prefix .= 'gemiddeld ' . Format::Number($keysdiff / $saverdays) . ' keys per dag]';
        }
        
        if ($keysdiff == $highest['keysdiff']) {
            $prefix .= ' [blue]';
        } else {
            $prefix .= ' [green]';
        }
        echo $prefix . '+' . Format::StatNumber($keysdiff);
    }
    
    echo ' [/td]';
    
    
    // 4th column: clicks
    
    
    echo '[td]' . Format::StatNumber($user->getRawData('clicks'));
    
    // clicksdiff value
    $clicksdiff = $user->getRawData('clicksdiff');
    
    if ($clicksdiff > 0) {
        
        if ($clicksdiff == $highest['clicksdiff']) {
            $prefix = ' [blue]';
        } else {
            $prefix = ' [green]';
        }
        echo $prefix . '+' . Format::StatNumber($clicksdiff);
    }
    
    echo ' [/td]';
    
    
    // 5th column: third stat
    
    echo '[td]';
    
    if ($thirdStat == 'uptime') {
        echo Format::Uptime($user->getRawData('uptime'));
        
        $uptimediff = $user->getRawData('uptimediff');
        if ($uptimediff > 0) {
            if ($uptimediff == $highest['uptimediff']) {
                $prefix = ' [blue]';
            } else {
                $prefix = ' [green]';
            }
            echo $prefix . '+' . Format::Uptime($uptimediff);
        }
    } elseif ($thirdStat == 'bandwidth') {
        echo Format::Bandwidth($user->getRawData('bandwidth'));
        
        $bandwidthdiff = $user->getRawData('downloaddiff');
        if ($bandwidthdiff > 0) {
            if ($bandwidthdiff == $highest['bandwidthdiff']) {
                $prefix = ' [blue]';
            } else {
                $prefix = ' [green]';
            }
            echo $prefix . '+' . Format::Bandwidth($bandwidthdiff);
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
echo '[td]' . Format::StatNumber($totals['keys']) . ' [/td]';
echo '[td]' . (($totals['keysdiff'] > 0)?'[green]+':'[red]-') . Format::StatNumber($totals['keysdiff']) . '[/td][/tr]' . ENDL;

echo '[tr][td]Kliks [/td]';
echo '[td]' . Format::StatNumber($totals['clicks']) . ' [/td]';
echo '[td]' . (($totals['clicksdiff'] > 0)?'[green]+':'[red]-') . Format::StatNumber($totals['clicksdiff']) . '[/td][/tr]' . ENDL;

echo '[tr][td]Uptime [/td]';
echo '[td]' . Format::Uptime($totals['uptime']) . ' [/td]';
echo '[td]' . (($totals['uptimediff'] > 0)?'[green]+':'[red]-') . Format::Uptime($totals['uptimediff']) . '[/td][/tr]' . ENDL;

echo '[tr][td]Download [/td]';
echo '[td]' . Format::Bandwidth($totals['download']) . ' [/td]';
echo '[td]' . (($totals['downloaddiff'] > 0)?'[green]+':'[red]-') . Format::Bandwidth($totals['downloaddiff']) . '[/td][/tr]' . ENDL;

echo '[tr][td]Upload [/td]';
echo '[td]' . Format::Bandwidth($totals['upload']) . ' [/td]';
echo '[td]' . (($totals['uploaddiff'] > 0)?'[green]+':'[red]-') . Format::Bandwidth($totals['uploaddiff']) . '[/td][/tr]' . ENDL;

if ($totals['pulsers'] > 0) {
    
    echo '[tr][td]-[/td][td] [/td][td] [/td][/tr]' . ENDL;
    
    echo '[tr][td]Spaarders[/td][td]'.$totals['savers'].'[/td]';
    echo '[td][abbr=' . $totals['savers'] . ' van de ' . (($totals['pulsers'] > 1)?$totals['pulsers'] . ' mensen die pulsten': '1 mens die pulste') . ']';
    echo round(($totals['savers'] / $totals['pulsers']) * 100, 2).'%[/abbr][/td][/tr]' . ENDL;
    
}

echo '[/table]' . ENDL . ENDL;

echo '[url=' . $scripturl . ']Deze statistieken[/url] ([url=https://github.com/goldenice/GMOT-Whatpulse-Parser]Broncode[/url])' . ENDL . ENDL;

if (DEVMODE) {
    echo 'Generated in ' . ((microtime(true) - $starttime) * 1000) . ' milliseconds.';
}