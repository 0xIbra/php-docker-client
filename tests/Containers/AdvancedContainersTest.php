<?php

namespace IterativeCode\Component\DockerClient\Tests\Containers;

use IterativeCode\Component\DockerClient\Exception\ResourceNotFound;
use IterativeCode\Component\DockerClient\Tests\MainTestCase;

class AdvancedContainersTest extends MainTestCase
{
    private $image = 'alpine:3.16.2';

    public function testContainerStats()
    {
        $containerId = $this->docker->runContainer('test-container', [
            'Image' => $this->image,
            'Cmd' => ['sleep', '300'],
        ]);
        sleep(1);

        $this->assertIsString($containerId);

        $stats = $this->docker->getContainerStats($containerId);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('cpu_stats', $stats);
        $this->assertArrayHasKey('cpu_usage', $stats['cpu_stats']);
        $this->assertEquals('/test-container', $stats['name']);

        $this->docker->stopContainer($containerId);
        $this->docker->deleteContainer($containerId);
    }

    public function testFailContainerStats()
    {
        $this->expectException(ResourceNotFound::class);
        $this->docker->getContainerStats('fakeid');
    }

    public function testContainerLogs()
    {
        $envName = 'HOOK_ME';
        $envValue = 'php-docker-client';
        $hookEnv = "$envName=$envValue";
        $containerId = $this->docker->runContainer('test-container', [
            'Image' => $this->image,
            'Env' => [$hookEnv],
            'Cmd' => ['printenv'],
        ]);
        sleep(1);

        $this->assertIsString($containerId);

        $logs = $this->docker->getContainerLogs($containerId);
        $this->assertIsString($logs);
        $this->assertStringContainsString($envName, $logs);
        $this->assertStringContainsString($envValue, $logs);
    }

    public function testFailContainerLogs()
    {
        $this->expectException(ResourceNotFound::class);
        $this->docker->getContainerLogs('fakeid');
    }
}