<?php

namespace Justacan\CloudFlare;

class CloudFlare {

    private $body;
    private $baseUrl;
    private $body_one_line;

    public function __construct($body = '', $baseUrl = '') {
        $this->addBody($body);
        $this->addBaseUrl($baseUrl);
    }
    
    public function challengeForm($override = false) {     
        
        if (empty($this->baseUrl)) {
            throw new Exception('Missing: baseUrl');
        }
        
        if (!$override && !$this->detect()) {
            throw new Exception('Warring: This page might not be CloudFlare, pass (true) to override');
        }
        
        $answer = $this->extractJavascript();
        $form = $this->extractForm($answer);
        sleep(5);
        return new ChallengeFormObject('/cdn-cgi/l/chk_jschl', $form);
    }
        
    public function detect($body = '') {
        if (!empty($body)) {
            $this->addBody($body);
        }
        return (preg_match('/\<title\>Please wait 5 seconds\.\.\.\<\/title\>/', $this->body_one_line));
    }
    
    public function addBody($body) {
        $this->body = $body;
        $this->makeOneLine();
    }    
    
    public function addBaseUrl($baseUrl) {
        $this->baseUrl = $baseUrl;
    }

    private function makeOneLine() {
        $one_line = preg_replace('/\n|\r/', '', $this->body);
        $one_line = preg_replace('/\s\s+/', ' ', $one_line);
        $this->body_one_line = $one_line;
        unset($one_line);
    }

    private function extractForm($answer) {

        $match = preg_match_all('/\<input type\=\"hidden\" name=\"(.*?)\" value\=\"(.*?)\"\/\>/', $this->body_one_line, $matches);
        if (!$match) {
            die(__METHOD__ . " died\n");
        }
        return array($matches[1][0] => $matches[2][0], $matches[1][1] => $matches[2][1], 'jschl_answer' => $answer);
    }   

    private function extractJavascript() {

        $match = preg_match('/setTimeout\(function\(\)\{(.*?)\, \d+\)\;/', $this->body_one_line, $matches);
        if (!$match) {
            die("Cannot get JavaScript!\n");
        }

        $lines = explode(';', $matches[1]);

        foreach ($lines as $key => &$value) {
            $value = trim($value);
            $pattern = '/createElement|innerHTML|firstChild|match|substr|getElementById|submit/';
            if (preg_match($pattern, $value) || strlen($value) <= 1) {
                unset($lines[$key]);
            }
            $value = str_replace('.value', '', $value);
            $value = str_replace('"', '\'', $value);
        }

        array_unshift($lines, 't = ' . '\'' . $this->getDomain() . '\'');
        array_push($lines, 'console.log(a);');
        $javascript = implode(";", $lines);
        file_put_contents('/tmp/script.js', $javascript);
        $output = trim(`nodejs /tmp/script.js`);
        unlink('/tmp/script.js');
        return $output;
    }   

    private function getDomain() {
        $match = preg_match('/(?:http|https)\:\/\/(.*?\.\w+)/', $this->baseUrl, $matches);
        return $matches[1];
    }

}
