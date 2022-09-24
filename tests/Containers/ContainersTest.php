<?php

namespace IterativeCode\Component\DockerClient\Tests\Containers;

use IterativeCode\Component\DockerClient\Tests\MainTestCase;

class ContainersTest extends MainTestCase
{
    public function testListEmptyContainers()
    {
        $containers = $this->docker->listContainers();
        $this->assertIsArray($containers);
        $this->assertEmpty($containers);
    }
}