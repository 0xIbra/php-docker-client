<?php

namespace IterativeCode\Component\DockerClient\Tests\Images;

use IterativeCode\Component\DockerClient\Tests\MainTestCase;

class ImagesTest extends MainTestCase
{
    public function testEmptyImagesList()
    {
        $images = $this->docker->listImages();
        $this->assertEmpty($images);
    }

//    public function testPullImage()
//    {
//
//    }
}