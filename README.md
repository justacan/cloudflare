# cloudflare
Scrape websites that use CloudFlare DDoS protection

Node.js is needed to run the JavaScript to solve the CloudFlare challenge 

functional example using Guzzle

````php
$browser = new Browser("http://example.com", "example.com");
$browser->getPage('/about');
````
---
````PHP
<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Cookie\CookieJar;
use Justacan\CloudFlare\CloudFlare;

class Browser {

    protected $client;
    protected $jar;
    protected $body;
    protected $baseUrl;
    protected $host;

    public function __construct($base_url, $host) {
        $this->jar = new CookieJar();
        $this->client = new Client(['cookies' => $this->jar, 'allow_redirects' => true]);
        $this->baseUrl = $base_url;
        $this->host = $host;
    }

    protected function makeRequest($page, $query = array(), $debug = false) {
        try {
            $options = [
              'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36',
                'Host' => $host,
                'Referer' => $this->baseUrl . $page
              ]
            ];

            if (!empty($query)) {
                $options['query'] = $query;
            }

            $res = $this->client->request('GET', $this->baseUrl . $page, $options);
            $this->body = $res->getBody();

            return true; //everything is fine
        } catch (RequestException $e) {
            if ($e->getCode() === 503) { // might be CloudFlare
                $this->body = $e->getResponse()->getBody(); // set body to var
                return false;
            }
        }
    }

    public function getPage($page) {

        $res = $this->makeRequest($page); // try to make request, if it fails on a 503 it might be CloudFlare
        if (!$res) {
            $cloudflare = new CloudFlare($this->body); // create CloudFlare instance 
            if ($cloudflare->detect()) { // bool if CloudFlare
                $cloudflare->addBaseUrl($this->baseUrl); // the JavaScript needs the baseUrl
                $cfo = $cloudflare->challengeForm(); // CloudFlare challengeForm answers
                $this->makeRequest($cfo->url, $cfo->query); // send it back in the query string
                sleep(5); // must wait 5 seconds
                $this->makeRequest($page, array(), true); // try the origial page again
            }
        }
        return $this->body;
    }
    
    public function saveBody($mod = '') {
        file_put_contents('test/output' . $mod . '.html', $this->body);
    }

    public function loadBody() {
        $this->body = file_get_contents('test/output.html');
        return $this->body;
    }

}

````
