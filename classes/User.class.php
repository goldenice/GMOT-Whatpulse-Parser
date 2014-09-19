<?php

/**
 * User Class
 */

class User {
    private $data;
    private $rankDelta;
    
    public function __construct($data, $rankDelta) {
        $this->data = $data;
        $this->rankDelta = $rankDelta;
    }
    
    public function setRawData($key, $value) {
        $this->data[$key] = $value;
    }
    
    public function isSaver() {
        if (isset($this->data['saver'])) {
            return ($this->data['saver']);
        }
        return false;
    }
    
    public function hasPulsed() {
        if (isset($this->data['keysDiff']) && isset($this->data['clicksDiff'])) {
            return ($this->data['keysDiff'] != 0 || $this->data['clicksDiff'] != 0);
        }
        return false;
    }
    
    /**
     * Get safe (no bbcode) username (without [GMOT] tag)
     */
    public function getUsername() {
        global $teamtag;
        
        return Format::NoBBC(
            trim(substr(
                trim($this->data['username']),
                strlen($teamtag)
            ))
        );
    }
    
    public function getRankDiff() {
        return ($this->data['rank'] - $this->data['oldrank']) - $this->rankDelta;
    }
    
    public function getRawData($key) {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        } else {
            return null;
        }
    }
    
    public function isActive() {
        if (isset($this->data['status'])) {
            return ($this->data['status'] != 'ex-member' && $this->data['status'] != 'just-left');
        }
        return false;
    }
}