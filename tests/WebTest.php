<?php

namespace duncan3dc\SessionsTest;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;

class WebTest extends \PHPUnit_Framework_TestCase
{
    private $cookies;
    private $client;

    public function setUp()
    {
        # HHVM no longer has a built in webserver, so don't run these tests
        if (isset($_ENV["TRAVIS_PHP_VERSION"]) && $_ENV["TRAVIS_PHP_VERSION"] === "hhvm") {
            $this->markTestSkipped("No internal webserver available on HHVM for web tests");
        }

        $this->cookies = tempnam(sys_get_temp_dir(), "duncan3dc-sessions-");

        $this->client = new Client([
            "cookies"   =>  new FileCookieJar($this->cookies),
        ]);
    }


    public function tearDown()
    {
        unset($this->client);

        unlink($this->cookies);
    }


    private function request($path, $name = null)
    {
        if ($name !== null) {
            if (strpos($path, "?")) {
                $path .= "&";
            } else {
                $path .= "?";
            }
            $path .= "session_name={$name}";
        }

        return $this->client->request("GET", "http://localhost:" . SERVER_PORT . "/{$path}");
    }


    private function assertRequest($request, array $expected)
    {
        $response = $this->request($request);
        $body = (string) $response->getBody();
        $result = unserialize($body);

        $this->assertSame($expected, $result);
    }


    public function testGetEmpty()
    {
        $this->assertRequest("getall.php", []);
    }


    public function testSetSomething()
    {
        $this->request("set.php?key=ok&value=yep");
        $this->assertRequest("getall.php", [
            "ok"    =>  "yep",
        ]);
    }


    public function testDestroy()
    {
        $this->request("set.php?key=ok&value=yep");
        $this->assertRequest("getall.php", [
            "ok"    =>  "yep",
        ]);

        $this->request("destroy.php");
        $this->assertRequest("getall.php", []);
    }


    public function testDestroyCorrectsession()
    {
        $this->request("set.php?key=ok&value=web1", "web1");
        $this->assertRequest("getall.php?session_name=web1", [
            "ok"    =>  "web1",
        ]);

        $this->request("set.php?key=ok&value=web2", "web2");
        $this->assertRequest("getall.php?session_name=web2", [
            "ok"    =>  "web2",
        ]);

        # Make sure that destroy only wipes the correct session
        $this->request("destroy.php", "web1");
        $this->assertRequest("getall.php?session_name=web1", []);
        $this->assertRequest("getall.php?session_name=web2", [
            "ok"    =>  "web2",
        ]);
    }


    public function testCookies()
    {
        $response = $this->request("getall.php");
        $cookie = $response->getHeader("Set-Cookie")[0];
        $this->assertRegExp("/^web=[a-z0-9]+; path=\/$/", $cookie);
    }
}