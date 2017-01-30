<?php
namespace Curler\Test;

use \PHPUnit\Framework\TestCase;
use \Curler\Request;
use \Exception;

class RequestTest extends TestCase {
    public function testCreateEmpty() {
        $curl = new Request();
        $this->assertTrue($curl instanceof Request, "not a request obejct");
    }
    public function testCreateLocation() {
        $curl = new Request("http://example.com");
        $this->assertTrue($curl instanceof Request, "not a request obejct");
        $this->assertEquals($curl->getUrl(), "http://example.com");
    }
    public function testCreateLocationObject() {
        $curl = new Request([
            "protocol" => "http",
            "host" => "example.com"
        ]);
        $this->assertEquals($curl->getUrl(), "http://example.com");
    }
    public function testSetLocationString() {
        $curl = new Request();
        $curl->setLocation("http://example.com");
        $this->assertEquals($curl->getUrl(), "http://example.com");
    }
    public function testSetLocationStringPort() {
        $curl = new Request();
        $curl->setLocation("http://example.com:8080/foo");
        $this->assertEquals($curl->getUrl(), "http://example.com:8080/foo");
    }
    public function testSetLocationObject() {
        $curl = new Request();
        $curl->setLocation([
            "protocol" => "http",
            "host" => "example.com"
        ]);
        $this->assertEquals($curl->getUrl(), "http://example.com");
    }
    public function testSetPath() {
        $curl = new Request("http://example.com");
        $curl->setPath("/foo");
        $this->assertEquals($curl->getUrl(), "http://example.com/foo");
    }
    public function testSetPathInfo() {
        $curl = new Request("http://example.com/bar");
        $curl->setPathInfo("/foo");
        $this->assertEquals($curl->getUrl(), "http://example.com/bar/foo");
        $curl->setPathInfo("/baz");
        $this->assertEquals($curl->getUrl(), "http://example.com/bar/baz");
    }
    public function testSetPathInfo2() {
        $curl = new Request("http://example.com/bar/");
        $curl->setPathInfo("/foo");
        $this->assertEquals($curl->getUrl(), "http://example.com/bar/foo");
    }
    public function testSetPathInfo3() {
        $curl = new Request("http://example.com/bar/");
        $curl->setPathInfo("foo");
        $this->assertEquals($curl->getUrl(), "http://example.com/bar/foo");
    }
    public function testSetParameters() {
        $curl = new Request("http://example.com/bar");
        $curl->setGetParameter(["foo"=> "baz"]);
        $this->assertEquals($curl->getUrl(), "http://example.com/bar?foo=baz", "single param issue");
        $curl->setGetParameter([
            "foo"=> "baz",
            "hello"=>"world"
        ]);
        $this->assertEquals($curl->getUrl(), "http://example.com/bar?foo=baz&hello=world", "multi param issue");
        $curl->setGetParameter(["foo bar"=> "hello world"]);
        $this->assertEquals($curl->getUrl(), "http://example.com/bar?foo+bar=hello+world", "url encoding issue");
        $curl->setGetParameter("foo bar");
        $this->assertEquals($curl->getUrl(), "http://example.com/bar?foo+bar", "query string issue");
    }
    public function testGetOk() {
        //FIXME: use a valid testing URL
        $this->final = false;
        $curl = new Request("http://www.htwchur.ch/robots.txt");
        $curl->get()
             ->then(function($res) {
                 $this->assertTrue($res instanceof Request);
                 $this->assertGreaterThan(1, strlen($res->getBody()));
                 $this->final = true;
             })
             ->fails(function($err) {
                 $this->final = false;
                 $this->assertTrue(false, "must not reach");
             });
        $this->assertTrue($this->final);
    }
    public function testPutOk() {
        $this->assertTrue(false);
    }
    public function testPostOk() {
        $this->assertTrue(false);
    }
    public function testDeleteOk() {
        $this->assertTrue(false);
    }
    public function testHeadOk() {
        $this->assertTrue(false);
    }
    public function testOptionsOk() {
        $this->assertTrue(false);
    }
    public function testPatchOk() {
        $this->assertTrue(false);
    }
    public function testHeader() {
        $this->assertTrue(false);
    }
    public function testErrorCore() {
        //FIXME: use a valid testing URL
        $this->final = false;
        $curl = new Request("https://telerope.eu/foobar");
        $curl->get()
             ->then(function($res) {
                 $this->assertTrue(false);
             })
             ->fails(function($err) {
                 $this->assertTrue($err instanceof Request);
                 $this->assertEquals($err->getStatus(), 404);
                 $this->final = true;
             });
        $this->assertTrue($this->final);
    }
    public function testNotFoundSpecial() {
        $this->final = false;
        $curl = new Request("https://telerope.eu/foobar");
        $curl->get()
             ->then(function($res) {
                 $this->assertTrue(false);
             })
             ->notFound(function($res) {
                 $this->assertTrue($res instanceof Request);
                 $this->assertEquals($res->getStatus(), 404);
                 $this->final = true;
             })
             ->fails(function($err) {
                 $this->final = false;
                 $this->assertTrue(false, "must not reach");
             });
        $this->assertTrue($this->final);
    }
    public function testForbiddenSpecial() {
        $this->final = false;
        $curl = new Request("https://mdl-tst.htwchur.ch/local/powertla/rest.php/lrs/xapi");
        $curl->get()
             ->then(function($res) {
                 $this->assertTrue(false);
             })
             ->forbidden(function($res) {
                 $this->assertTrue($res instanceof Request);
                 $this->assertEquals($res->getStatus(), 403);
                 $this->final = true;
             })
             ->fails(function($err) {
                 $this->final = false;
                 $this->assertTrue(false, "must not reach");
             });
        $this->assertTrue($this->final);
    }
    public function testUnAuthorizedSpecial() {
        $this->final = false;

        $curl = new Request("");
        $curl->get()
             ->then(function($res) {
                 $this->assertTrue(false);
             })
             ->forbidden(function($res) {
                 $this->assertTrue($res instanceof Request);
                 $this->assertEquals($res->getStatus(), 401);
                 $this->final = true;
             })
             ->fails(function($err) {
                 $this->final = false;
                 $this->assertTrue(false, "must not reach");
             });
        $this->assertTrue($this->final);
    }
    public function testInternalErrorSpecial() {
        $this->assertTrue(false);
    }
    public function testGoneSpecial() {
        $this->assertTrue(false);
    }
    public function testCreatedSpecial() {
        $this->assertTrue(false);
    }
    public function testNoContentSpecial() {
        $this->assertTrue(false);
    }
    public function testNoContentOk() {
        $this->assertTrue(false);
    }
    public function testNotAcceptableSpecial() {
        $this->assertTrue(false);
    }
    public function testConflictSpecial() {
        $this->assertTrue(false);
    }
}
?>
