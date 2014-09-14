<?php

/**
 * Static formatting class
 */

class Format {
    
    /**
     * Function to 'disarm' any BBCode formatting in a string.
     */
    static function NoBBC($string) {
        return '[nobbc]' . str_replace(
            array('[', ']'),
            array('(', ')'),
            $string
        ) . ' [/nobbc]';
    }
    
    /**
     * Function to generate a proper date from a timestamp (with dutch month- and daynames)
     */
    static function DateTime($timestamp) {
        $dagen = array(
            'maandag',
            'dinsdag',
            'woensdag',
            'donderdag',
            'vrijdag',
            'zaterdag',
            'zondag'
        );
        
        $maanden = array(
            'januari',
            'februari',
            'maart',
            'april',
            'mei',
            'juni',
            'juli',
            'augustus',
            'september',
            'oktober',
            'november',
            'december'
        );
        
        return $dagen[date('N', $timestamp) - 1] . date(' j ', $timestamp) . $maanden[date('n', $timestamp) - 1] . date(' Y', $timestamp); 
    }
    
    static function Number($number) {
        return number_format($number, 0, ',', '.');
    }
    
    static function StatNumber($number) {
        switch($number) {
        case 1337:
            return '[abbr=1337][b]leet[/b][/abbr]';
        case 1787569:
            return '[abbr=1.787.569 ofwel 1337²][b]leet^2!!![/b][/abbr]';
        case 9999999:
            return '[abbr=Sjongejonge, er kan tegenwoordig geen key meer vanaf hè... De jeugd van tegenwoordig...]9.999.999[/abbr]';
        default:
            return Format::Number($number);
        }
    }
    
    static function Uptime($input) {
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
    
    static function Bandwidth($input) {
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
}