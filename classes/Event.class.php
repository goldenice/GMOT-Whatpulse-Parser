<?php

/**
 * Event class
 */

class Event {
    private $message;
    private $username;
    
    public function __construct($message, $username) {
        $this->message = $message;
        $this->username = $username;
    }
    
    public function getString() {
        return sprintf($this->message, $this->username);
    }
}