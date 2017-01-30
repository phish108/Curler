<?php
namespace Curler\Test;

use \PHPUnit\Framework\TestCase;
use \Curler\Promise;
use \Exception;

class HandlerModel {
    private $tc;
    public $succeed = true;
    public $final = false;

    public function __construct($testcase) {
        $this->tc = $testcase;
    }
    public function resolved($res) {
        $this->tc->assertEquals("success", $res);
        $this->final = true;
    }

    public function failed($err) {
        $this->tc->assertTrue($err instanceof Exception, "received no Exception");
        $this->tc->assertEquals("fail", $err->getMessage(), "received wrong message");
        $this->final = true;
    }
}

class PromiseTest extends TestCase {

    public function testFailsImmediately() {
        $this->final = false;
        $p = new Promise( function($s,$f){
            throw new Exception("fails immediately");
        });
        $p->then(function ($r){
            $this->assertTrue(false, "must no reach then.");
        });
        $p->fails(function ($err) {
            $this->assertTrue($err instanceof Exception, "received no Exception");
            $this->assertEquals($err->getMessage(), "fails immediately", "received wrong message");
            $this->final = true;
        });
        $this->assertTrue($this->final, "fails immediately final");
    }

    public function testFailsImmediatelyRegular() {
        $this->final = false;
        $p = new Promise( function($s,$f){
            call_user_func($f, new Exception("fails immediately"));
        });
        $p->then(function ($r) {
            $this->assertTrue(false, "must no reach then()");
        });
        $p->fails(function($err)  {
            $this->assertTrue($err instanceof Exception, "received no Exception");
            $this->assertEquals("fails immediately", $err->getMessage(), "received wrong message");
            $this->final = true;
        });
        $this->assertTrue($this->final, "fails regular final");
    }

    public function testFailsLate() {
        $reject;
        $this->final = false;
        $p = new Promise( function($s,$f) use (&$reject){
            $reject = $f;
        });
        $p->then(function ($r){
            $this->assertTrue(false, "must no reach then()");
        });
        $p->fails(function($err) {
            $this->assertTrue($err instanceof Exception,"received no Exception");
            $this->assertEquals("fails late", $err->getMessage(), "received wrong message");
            $this->final = true;
        });

        $this->assertTrue(is_callable($reject), "not callable");

        call_user_func($reject, new Exception("fails late"));
        $this->assertTrue($this->final, "fails late final");
    }

    public function testFailChain() {
        $reject;
        $this->final = false;
        $p = new Promise( function($s,$f) use (&$reject){
            $reject = $f;
        });
        $p->then(function ($r)  {
            $this->assertTrue(false, "must no reach then()");
        });
        $p->fails(function($err) {
            $this->assertTrue($err instanceof Exception, "received no Exception");
            $this->assertEquals("fails late", $err->getMessage(), "received wrong message");
            return new Exception("fails badly");
        });
        $p->fails(function($err) {
            $this->assertTrue($err instanceof Exception, "received no Exception");
            $this->assertEquals("fails badly", $err->getMessage(), "received wrong message");
            $this->final = true;
        });
        $p->fails(function($err) {
            // MUST NOT REACH
            error_log("MUST NOT REACH LAST HANDLER $err");
            $this->assertTrue(false, "MUST NOT REACH LAST HANDLER");
            $this->final = false;
        });

        call_user_func($reject, new Exception("fails late"));
        $this->assertTrue($this->final, "fail chain final");
    }

    public function testResolveImmediately() {
        $this->final = false;
        $p = new Promise( function($s,$f){
            call_user_func($s, "success");
        });
        $p->then(function ($r) {
            $this->assertEquals("success", $r);
            $this->final = true;
        });
        $p->fails(function($err)  {
            $this->assertTrue(false, "must not reach fails()");
        });
        $this->assertTrue($this->final, "resolve immediately final");
    }

    public function testResolveLate() {
        $resolve;
        $this->final = false;
        $p = new Promise( function($s,$f) use (&$resolve){
            $resolve = $s;
        });
        $p->then(function ($r) {
            $this->assertEquals("success", $r);
            $this->final = true;
        });
        $p->fails(function($err) {
            $this->assertTrue(false, "must not reach fails()");
        });

        call_user_func($resolve, "success");
        $this->assertTrue($this->final, "resolve late final");
    }

    public function testChainedSteps() {
        $resolve;
        $this->final = false;
        $p = new Promise( function($s,$f) use (&$resolve){
            $resolve = $s;
        });
        $p->then(function ($r) {
            $this->assertEquals("success", $r);
            return "$r 2";
        });
        $p->then(function ($r) {
            $this->assertEquals("success 2", $r);
            $this->final = true;
        });
        $p->fails(function($err) {
            $this->assertTrue(false, "must not reach fails()");
        });

        call_user_func($resolve, "success");
        $this->assertTrue($this->final, "resolve chained steps final");
    }

    public function testChainedStepsEmpty() {
        $resolve;
        $this->final = false;
        $p = new Promise( function($s,$f) use (&$resolve){
            $resolve = $s;
        });
        $p->then(function ($r) {
            $this->assertEquals("success", $r);
            return "$r 2";
        });
        $p->then(function ($r) {
            $this->assertEquals("success 2", $r);
        });
        $p->then(function($r)  {
            $this->assertNull($r, "");
            $this->final = true;
        });
        $p->fails(function($err) {
            $this->assertTrue(false, "must not reach fails()");
        });

        call_user_func($resolve, "success");
        $this->assertTrue($this->final, "resolve chained steps empty final");
    }

    public function testChainedCascade() {
        $resolve;
        $this->final = false;
        $p = new Promise( function($s,$f) use (&$resolve){
            $resolve = $s;
        });
        $p->then(function ($r) {
            $this->assertEquals("success", $r);
            return "$r 2";
        })
        ->then(function ($r) {
            $this->assertEquals("success 2", $r);
            $this->final = true;
        })
        ->fails(function($err) {
            $this->assertTrue(false, "must not reach fails()");
        });

        call_user_func($resolve, "success");
        $this->assertTrue($this->final, "resolve chained cascade final");
    }

    public function testChainedDefered() {
        $resolve;
        $this->final = false;
        $p = new Promise( function($s,$f) use (&$resolve){
            $resolve = $s;
        });
        $p->then(function ($r)  {
            $this->assertEquals("success", $r);
            return "$r 1";
        });
        $p->then(function ($r) {
            $this->assertEquals("success 1", $r);
            return "$r 2";
        });
        $p->fails(function($err){
            $this->final = false;
        });

        call_user_func($resolve, "success");

        $p->then(function ($r) {
            $this->assertEquals("success 1 2", $r);
            $this->final = true;
        });

        $this->assertTrue($this->final, "defered chain element called");
    }

    public function testModelHandlerOk() {
        $h = new HandlerModel($this);

        $p = new Promise( function($s,$f){
            call_user_func($s, "success");
        });

        $p->then($h);
        $p->fails($h);
        $this->assertTrue($h->final, "handler resolve final");
    }

    public function testModelHandlerFails() {
        $h = new HandlerModel($this);

        $p = new Promise( function($s,$f){
            call_user_func($f, new Exception("fail"));
        });

        $p->then($h);
        $p->fails($h);
        $this->assertTrue($h->final, "handler reject final");
    }

    public function testChainFail() {
        $this->final = false;
        $p = new Promise( function($s,$f){
            call_user_func($s, "success");
        });
        $p->then(function ($r) {
            throw new Exception("failed chain");
        });
        $p->then(function ($r) {
            // $this->assertTrue(false, "success");
            $this->final = false;
        });
        $p->fails(function($err){
            $this->final = true;
        });
        $this->assertTrue($this->final, "resolve immediately final");
    }
}

?>
