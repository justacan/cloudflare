<?php

namespace Justacan\CloudFlare;

class ChallengeFormObject {
    public $url;
    public $query;
    
    public function __construct($url, $query) {
        $this->url = $url;
        $this->query = $query;
    }
}
