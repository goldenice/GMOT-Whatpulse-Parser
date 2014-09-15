<?php

/**
 * Event class
 */

class Event {
    private $message;
    private $user;
    
    public function __construct($message, $user) {
        $this->message = $message;
        $this->user = $user;
    }
    
    public function getString() {
        return sprintf($this->message, $this->user->getUsername());
    }
}