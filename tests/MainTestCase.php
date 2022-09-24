<?php

namespace IterativeCode\Component\DockerClient\Tests;

use PHPUnit\Framework\TestCase;
use IterativeCode\Component\DockerClient\DockerClient;

class MainTestCase extends TestCase
{
    /** @var DockerClient */
    protected $docker;

    public function setUp(): void
    {
        $this->docker = new DockerClient(['local_endpoint' => 'http://127.0.0.1:2375']);
    }
}