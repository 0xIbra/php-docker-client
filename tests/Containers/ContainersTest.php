<?php

namespace IterativeCode\Component\DockerClient\Tests\Containers;

use IterativeCode\Component\DockerClient\Exception\ResourceBusyException;
use IterativeCode\Component\DockerClient\Tests\MainTestCase;

class ContainersTest extends MainTestCase
{
    private $image = 'alpine:3.16.2';

    public function setUp(): void
    {
        parent::setUp();

        $this->docker->pullImage($this->image);
    }

    public function testListEmptyContainers()
    {
        $containers = $this->docker->listContainers();
        $this->assertIsArray($containers);
        $this->assertEmpty($containers);
    }

    public function testRunContainer()
    {
        $containerId = $this->docker->runContainer('test-container', [
            'Image' => $this->image,
            'Cmd' => 'printenv'
        ]);
        sleep(1);

        $this->assertNotFalse($containerId);
        $this->assertIsString($containerId);

        $this->docker->deleteContainer($containerId);
    }

    public function testInspectContainer()
    {
        $hookEnv = 'HOOK_ME=php-docker-client';
        $containerId = $this->docker->runContainer('test-container', [
            'Image' => $this->image,
            'Env' => [
                $hookEnv,
            ],
            'Cmd' => ['printenv'],
        ]);

        sleep(1);

        $this->assertNotFalse($containerId);
        $this->assertIsString($containerId);

        $containerDetails = $this->docker->inspectContainer($containerId);
        $this->assertIsArray($containerDetails);
        $this->assertArrayHasKey('Id', $containerDetails);
        $this->assertTrue(in_array($hookEnv, $containerDetails['Config']['Env']));

        $this->docker->deleteContainer($containerId);
    }

    public function testStopContainer()
    {
        $containerId = $this->docker->runContainer('test-container', [
            'Image' => $this->image,
            'Cmd' => ['sleep', '300'],
        ]);
        sleep(1);

        $this->assertIsString($containerId);

        $result = $this->docker->stopContainer($containerId);
        $this->assertIsBool($result);
        $this->assertTrue($result);

        $containerDetails = $this->docker->inspectContainer($containerId);
        $this->assertIsArray($containerDetails);
        $this->assertIsString($containerDetails['State']['Status']);
        $this->assertFalse($containerDetails['State']['Running']);

        $this->docker->deleteContainer($containerId);
    }


    public function testRemoveContainer()
    {
        $containerId = $this->docker->runContainer('test-container', [
            'Image' => $this->image,
            'Cmd' => ['sleep', '300'],
        ]);
        sleep(1);

        $this->assertIsString($containerId);
        $this->docker->stopContainer($containerId);

        $result = $this->docker->deleteContainer($containerId);
        $this->assertTrue($result);
    }

    public function testFailToDeleteRunningContainer()
    {
        $containerId = $this->docker->runContainer('test-container', [
            'Image' => $this->image,
            'Cmd' => ['sleep', '300'],
        ]);
        sleep(1);

        $this->assertIsString($containerId);

        $this->expectException(ResourceBusyException::class);
        $this->docker->deleteContainer($containerId);

        $this->docker->stopContainer($containerId);
        $this->docker->deleteContainer($containerId);
    }
}