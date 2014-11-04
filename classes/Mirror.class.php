<?php

class Mirror {
    private $url;
    private $uptodate;
    
    public function __construct($url, $sourceHash) {
        $this->url = $url;
        $this->uptodate = -1;
        
        if ($sourceHash !== false) {
            $hash = readExternalFile($url . '?hash');
            
            if ($hash === $sourceHash) {
                $this->uptodate = 1;
            } elseif ($hash !== false) {
                $this->uptodate = 0;
            }
        }
    }
    
    public function getString($name, $validate) {
        $str = '[url=' . $this->url . ']%[/url]';
        if ($validate) {
            switch ($this->uptodate) {
            case 1:
                $str = str_replace('%', '[color=#408040]%[/color]', $str);
                break;
            case 0:
                $str = str_replace('%', '[color=#804040]%[/color]', $str);
                break;
            default: // unable to determine for this mirror
                $str = str_replace('%', '[color=#808080]%[/color]', $str);
                break;
            }
        }
        $str = str_replace('%', $name, $str);
        
        return $str;
    }
}