<?php

namespace IterativeCode\Component\DockerClient\Tests;

class DockerClientTest extends MainTestCase
{
    public function testConnection()
    {
        $info = $this->docker->info();
        $this->assertNotNull($info);
        $this->assertArrayHasKey('ID', $info);
    }

    public function testVersion()
    {
        $version = $this->docker->version();
        $this->assertArrayHasKey('ApiVersion', $version);
    }
}