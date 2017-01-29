<?php

require_once __DIR__ . "/../vendor/autoload.php";

use Curler\Request;

$urls = [
    "https://mdl-tst.htwchur.ch/local/powertla/rest.php/lrs/xapi", // 403
    "https://mdl-tst.htwchur.ch/local/powertla/rest.php", // 400
    "http://www.htwchur.ch/robots.txt", // 200
    "https://moodle.htwchur.ch/robots.txt" // 404
];

// create a Curler Request.
$req = new Request();

foreach ($urls as $url) {
    echo "\n" . $url . "\n";
    // change the location for the next request.
    $req->setLocation($url);

    // then work with the request.
    $req->get()                                                    // first perform the request using the HTTP method
        ->then(function($req) { echo "success\n"; })            // handle 200 and 204 responses
        ->forbidden(function($req) {echo "unauthorized\n";}) // handle 401 and 403 responses
        ->notFound(function ($req) { echo "not found\n";})        // handle 404 responses
        ->fails(function($req) { echo "other error " . $req->getStatus() . "\n";}); // handle all other responses
}

function call_url($url) {
    echo "\n" . $url . "\n";
    $result = null;

    $req = new Request($url);
    $req->get()
        // if you need to pass some information back to your script , no problem.
        ->then(function($req)  { echo "success\n"; return $req->getBody();})            // handle 200 and 204 responses
        // ->then(function($body) { echo "BODY: " . $body . "\n";})                        // handle the body separately
        ->forbidden(function($req) {  echo "unauthorized\n"; }) // handle 401 and 403 responses
        ->notFound(function ($req) { echo "not found\n";}) // handle 404 responses
        ->fails(function($req) { echo "other error " . $req->getStatus() . "\n";}); // handle all other responses

    if ($result) {
        echo $result . "\n";
    }
}

call_url($urls[2]);
call_url($urls[3]);

class testHandler {
    public function resolved($req) {
        echo "C success\n";
        // echo $req->getBody() . "\n";
    }
    public function failed($req) {
        echo "C other error " . $req->getStatus() . "\n";
    }

    public function forbidden($req) {
        echo "C forbidden\n";
    }
    public function notFound($req) {
        echo "C not found\n";
    }
}

$h = new testHandler();
$req = new Request($urls[2]);
echo "\n" . $urls[2] . "\n";

$req->get()
    ->then($h)
    ->fails($h);

echo "\n" . $urls[0] . "\n";
$req->setLocation($urls[0]);
$req->get()
    ->then($h)
    ->fails($h);

foreach ($urls as $url) {
    echo "\n" . $url . "\n";
    // change the location for the next request.
    $req->setLocation($url);

    // then work with the request.
    $req->get()          // first perform the request using the HTTP method
        ->then($h)            // handle 200 and 204 responses
        ->forbidden($h) // handle 401 and 403 responses
        ->notFound($h)        // handle 404 responses
        ->fails($h); // handle all other responses
}
