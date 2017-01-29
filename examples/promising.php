<?php

require_once __DIR__ . "/../vendor/autoload.php";

use Curler\Promise;

// shows how the Curler Promises work.

$foo = ["foo" => "bar", "bar"=> "baz"];

$p = new Promise(function ($resolve, $reject) use ($foo) { $resolve($foo);});

$p->then( function($res) { echo json_encode($res) . "\n"; return $res["foo"];})
  ->fails(function($err) { echo "must not show" . "\n";});

$p = new Promise(function ($resolve, $reject) use ($foo) { $reject($foo);});

$p->then( function($res) { echo json_encode($res) . "\n"; return $res["foo"];})
  ->fails(function($err) { echo json_encode($err) . "\n"; return $err["foo"];})
  ->fails(function($err) { echo $err . "\n";})
  ->fails(function($err) { echo "must not show" . "\n";});

$p = new Promise(function ($resolve, $reject) use ($foo) { $resolve($foo);});

$p->then( function($res) { echo json_encode($res) . "\n"; return $res["foo"];})
  ->then( function($res) { echo "$res\n";})
  ->then( function($res) { if ($res) echo "MUST NOT SHOW\n"; echo "OK\n";})
  ->fails(function($err) { echo "must not show" . "\n";});
