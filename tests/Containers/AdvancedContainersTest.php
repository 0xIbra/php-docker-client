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

        $this->docker->deleteContainer($containerId, true);
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

        $outLogs = $this->docker->getContainerLogs($containerId, 'out');
        $this->assertIsString($outLogs);
        $this->assertStringContainsString($envName, $logs);
        $this->assertStringContainsString($envValue, $logs);

        $errorLogs = $this->docker->getContainerLogs($containerId, 'error');
        $this->assertIsString($errorLogs);
        $this->assertEquals('', $errorLogs);

        $this->docker->deleteContainer($containerId, true);
    }

    public function testFailContainerLogs()
    {
        $this->expectException(ResourceNotFound::class);
        $this->docker->getContainerLogs('fakeid');
    }

    public function testPruneContainers()
    {
        $containerIds = [];
        for ($i = 0; $i < 10; $i++) {
            $name = sprintf('testContainer%s', ($i+1));
            $options = [
                'Image' => $this->image,
                'Cmd' => ['printenv'],
            ];

            $containerId = $this->docker->runContainer($name, $options);
            $containerIds[] = $containerId;
        }

        $this->assertCount(10, $containerIds);
        $containersCount = $this->docker->listContainers(['all' => true]);
        $containersCount = count($containersCount);
        $this->assertEquals(10, $containersCount);

        $result = $this->docker->pruneContainers();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('ContainersDeleted', $result);
        $this->assertEquals(10, count($result['ContainersDeleted']));

        $containers = $this->docker->listContainers(['all' => true]);
        $this->assertIsArray($containers);
        $this->assertEmpty($containers);
    }

    public function testFailPruneContainers()
    {
        $result = $this->docker->pruneContainers('IS_DOCKER_JOB');
        $this->assertIsArray($result);
        $this->assertEquals(null, $result['ContainersDeleted']);
    }
}