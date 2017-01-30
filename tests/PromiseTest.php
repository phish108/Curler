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
        $self = $this;
        $final = false;
        $p = new Promise( function($s,$f){
            throw new Exception("fails immediately");
        });
        $p->then(function ($r) use ($self) {
            $self->assertTrue(false, "must no reach then.");
        });
        $p->fails(function ($err) use ($self, &$final) {
            $self->assertTrue($err instanceof Exception, "received no Exception");
            $self->assertEquals($err->getMessage(), "fails immediately", "received wrong message");
            $final = true;
        });
        $this->assertTrue($final, "fails immediately final");
    }

    public function testFailsImmediatelyRegular() {
        $self = $this;
        $final = false;
        $p = new Promise( function($s,$f){
            call_user_func($f, new Exception("fails immediately"));
        });
        $p->then(function ($r) use ($self) {
            $self->assertTrue(false, "must no reach then()");
        });
        $p->fails(function($err) use ($self, &$final) {
            $self->assertTrue($err instanceof Exception, "received no Exception");
            $self->assertEquals("fails immediately", $err->getMessage(), "received wrong message");
            $final = true;
        });
        $this->assertTrue($final, "fails regular final");
    }

    public function testFailsLate() {
        $self = $this;
        $reject;
        $final = false;
        $p = new Promise( function($s,$f) use (&$reject){
            $reject = $f;
        });
        $p->then(function ($r) use ($self) {
            $self->assertTrue(false, "must no reach then()");
        });
        $p->fails(function($err) use ($self, &$final) {
            $self->assertTrue($err instanceof Exception,"received no Exception");
            $self->assertEquals("fails late", $err->getMessage(), "received wrong message");
            $final = true;
        });

        $this->assertTrue(is_callable($reject), "not callable");

        call_user_func($reject, new Exception("fails late"));
        $this->assertTrue($final, "fails late final");
    }

    public function testFailChain() {
        $self = $this;
        $reject;
        $final = false;
        $p = new Promise( function($s,$f) use (&$reject){
            $reject = $f;
        });
        $p->then(function ($r) use ($self) {
            $self->assertTrue(false, "must no reach then()");
        });
        $p->fails(function($err) use ($self){
            $self->assertTrue($err instanceof Exception, "received no Exception");
            $self->assertEquals("fails late", $err->getMessage(), "received wrong message");
            return new Exception("fails badly");
        });
        $p->fails(function($err) use ($self, &$final) {
            $self->assertTrue($err instanceof Exception, "received no Exception");
            $self->assertEquals("fails badly", $err->getMessage(), "received wrong message");
            $final = true;
        });
        $p->fails(function($err) use ($self, &$final) {
            // MUST NOT REACH
            error_log("MUST NOT REACH LAST HANDLER $err");
            $self->assertTrue(false, "MUST NOT REACH LAST HANDLER");
            $final = false;
        });

        call_user_func($reject, new Exception("fails late"));
        $this->assertTrue($final, "fail chain final");
    }

    public function testResolveImmediately() {
        $self = $this;
        $final = false;
        $p = new Promise( function($s,$f){
            call_user_func($s, "success");
        });
        $p->then(function ($r) use ($self, &$final) {
            $self->assertEquals("success", $r);
            $final = true;
        });
        $p->fails(function($err) use ($self) {
            $self->assertTrue(false, "must not reach fails()");
        });
        $this->assertTrue($final, "resolve immediately final");
    }

    public function testResolveLate() {
        $self = $this;
        $resolve;
        $final = false;
        $p = new Promise( function($s,$f) use (&$resolve){
            $resolve = $s;
        });
        $p->then(function ($r) use ($self, &$final) {
            $self->assertEquals("success", $r);
            $final = true;
        });
        $p->fails(function($err) use ($self) {
            $self->assertTrue(false, "must not reach fails()");
        });

        call_user_func($resolve, "success");
        $this->assertTrue($final, "resolve late final");
    }

    public function testChainedSteps() {
        $self = $this;
        $resolve;
        $final = false;
        $p = new Promise( function($s,$f) use (&$resolve){
            $resolve = $s;
        });
        $p->then(function ($r) use ($self) {
            $self->assertEquals("success", $r);
            return "$r 2";
        });
        $p->then(function ($r) use ($self, &$final) {
            $self->assertEquals("success 2", $r);
            $final = true;
        });
        $p->fails(function($err) use ($self) {
            $self->assertTrue(false, "must not reach fails()");
        });

        call_user_func($resolve, "success");
        $this->assertTrue($final, "resolve chained steps final");
    }

    public function testChainedStepsEmpty() {
        $self = $this;
        $resolve;
        $final = false;
        $p = new Promise( function($s,$f) use (&$resolve){
            $resolve = $s;
        });
        $p->then(function ($r) use ($self) {
            $self->assertEquals("success", $r);
            return "$r 2";
        });
        $p->then(function ($r) use ($self) {
            $self->assertEquals("success 2", $r);
        });
        $p->then(function($r) use ($self, &$final) {
            $self->assertNull($r, "");
            $final = true;
        });
        $p->fails(function($err) use ($self) {
            $self->assertTrue(false, "must not reach fails()");
        });

        call_user_func($resolve, "success");
        $this->assertTrue($final, "resolve chained steps empty final");
    }

    public function testChainedCascade() {
        $self = $this;
        $resolve;
        $final = false;
        $p = new Promise( function($s,$f) use (&$resolve){
            $resolve = $s;
        });
        $p->then(function ($r) use ($self) {
            $self->assertEquals("success", $r);
            return "$r 2";
        })
        ->then(function ($r) use ($self, &$final) {
            $self->assertEquals("success 2", $r);
            $final = true;
        })
        ->fails(function($err) use ($self) {
            $self->assertTrue(false, "must not reach fails()");
        });

        call_user_func($resolve, "success");
        $this->assertTrue($final, "resolve chained cascade final");
    }

    public function testChainedDefered() {
        $self = $this;
        $resolve;
        $final = false;
        $p = new Promise( function($s,$f) use (&$resolve){
            $resolve = $s;
        });
        $p->then(function ($r) use ($self) {
            $self->assertEquals("success", $r);
            return "$r 1";
        });
        $p->then(function ($r) use ($self) {
            $self->assertEquals("success 1", $r);
            return "$r 2";
        });
        $p->fails(function($err) use ($self, &$final) {
            $final = false;
        });

        call_user_func($resolve, "success");

        $p->then(function ($r) use ($self, &$final) {
            $self->assertEquals("success 1 2", $r);
            $final = true;
        });

        $this->assertTrue($final, "defered chain element called");
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
        $self = $this;
        $final = false;
        $p = new Promise( function($s,$f){
            call_user_func($s, "success");
        });
        $p->then(function ($r) use ($self, &$final) {
            throw new Exception("failed chain");
        });
        $p->then(function ($r) use ($self, &$final) {
            // $self->assertTrue(false, "success");
            $final = false;
        });
        $p->fails(function($err) use ($self, &$final) {
            $final = true;
        });
        $this->assertTrue($final, "resolve immediately final");
    }
}

?>
