<?php

namespace IterativeCode\Component\DockerClient\Tests;

use IterativeCode\Component\DockerClient\DockerClient;
use IterativeCode\Component\DockerClient\Exception\DockerConnectionFailed;

class DockerClientTest extends MainTestCase
{
    public function testInfo()
    {
        $info = $this->docker->info();
        $this->assertIsArray($info);
        $this->assertArrayHasKey('ID', $info);
        $this->assertEquals(0, $info['Images']);
        $this->assertEquals(0, $info['Containers']);
    }

    public function testVersion()
    {
        $version = $this->docker->version();
        $this->assertArrayHasKey('ApiVersion', $version);
    }

    public function testFailedDockerConnection()
    {
        $localEndpoint = 'http://127.0.0.1:9999';
        $this->expectException(DockerConnectionFailed::class);
        $this->expectExceptionMessage("Docker API connection failed: $localEndpoint");

        $docker = new DockerClient(['local_endpoint' => $localEndpoint]);
    }
}