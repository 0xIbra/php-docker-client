<?php

namespace IterativeCode\Component\DockerClient\Tests\Containers;

use IterativeCode\Component\DockerClient\DockerClient;
use IterativeCode\Component\DockerClient\Exception\BadParameterException;
use IterativeCode\Component\DockerClient\Exception\ResourceBusyException;
use IterativeCode\Component\DockerClient\Exception\ResourceNotFound;
use IterativeCode\Component\DockerClient\Tests\MainTestCase;

class ContainersTest extends MainTestCase
{
    private $image = 'alpine:3.16.2';
    private $containerIds = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->docker->pullImage($this->image);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (count($this->containerIds) > 0) {
            foreach ($this->containerIds as $containerId) {
                $this->docker->deleteContainer($containerId, true);
            }

            $this->containerIds = [];
        }
    }

    public function testListEmptyContainers()
    {
        $containers = $this->docker->listContainers();
        $this->assertIsArray($containers);
        $this->assertEmpty($containers);
    }

    public function testFailListContainers()
    {
        $this->expectException(BadParameterException::class);
        $options = [
            'all' => true,
            'limit' => 32,
            'filters' => ['status' => ['badstatus']],
        ];
        $this->docker->listContainers($options);
    }

    public function testExceptionListContainers()
    {
        $this->expectException(\Exception::class);

        $docker = new DockerClient(['local_endpoint' => 'http://127.0.0.1:1234']);
        $docker->listContainers();
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

    public function testFailRunContainer()
    {
        $this->expectException(ResourceNotFound::class);
        $this->docker->startContainer('fakeid');
    }

    public function testStartContainerError()
    {
        $this->expectException(\Exception::class);
        $this->docker->startContainer('\n');
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

    public function testFailInspectContainer()
    {
        $this->expectException(ResourceNotFound::class);
        $this->docker->inspectContainer('fakeid');
    }

    public function testExceptionInspectContainer()
    {
        $this->expectException(\Exception::class);
        $docker = new DockerClient(['local_endpoint' => 'http://127.0.0.1:1234']);
        $docker->inspectContainer('fakeid');
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

    public function testFailStopContainer()
    {
        $this->expectException(ResourceNotFound::class);
        $this->docker->stopContainer('fakeid');
    }

    public function testStopContainerError()
    {
        $this->expectException(\Exception::class);
        $this->docker->stopContainer('\n');
    }

    public function testDeleteContainer()
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

    public function testFailDeleteContainer()
    {
        $this->expectException(ResourceNotFound::class);
        $this->docker->deleteContainer('fakeid', true);
    }

    public function testExceptionDeleteContainer()
    {
        $this->expectException(BadParameterException::class);
        $this->docker->deleteContainer(null);
    }

    public function testDeleteContainerError()
    {
        $this->expectException(\Exception::class);
        $this->docker->deleteContainer('\n');
    }

    public function testFailToDeleteRunningContainer()
    {
        $containerId = $this->docker->runContainer('test-container', [
            'Image' => $this->image,
            'Cmd' => ['sleep', '300'],
        ]);
        sleep(1);

        $this->assertIsString($containerId);

        $this->containerIds[] = $containerId;
        $this->expectException(ResourceBusyException::class);
        $this->docker->deleteContainer($containerId);
    }

    public function testForceDeleteRunningContainer()
    {
        $containerId = $this->docker->runContainer('test-container', [
            'Image' => $this->image,
            'Cmd' => ['sleep', '300'],
        ]);
        sleep(1);

        $this->assertIsString($containerId);

        $result = $this->docker->deleteContainer($containerId, true);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}