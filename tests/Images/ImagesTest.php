<?php

namespace IterativeCode\Component\DockerClient\Tests\Images;

use IterativeCode\Component\DockerClient\Tests\MainTestCase;

class ImagesTest extends MainTestCase
{
    private $pullImage = 'node:16';

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->docker->imageExists($this->pullImage)) {
            $this->docker->removeImage($this->pullImage, true);
        }
    }

    public function testEmptyImagesList()
    {
        $this->assertEmpty($this->docker->listImages());

        $images = $this->docker->listImages('SYMFONY_IMAGE');
        $this->assertIsArray($images);
        $this->assertEmpty($images);
    }

    public function testPullImage()
    {
        $this->docker->pullImage($this->pullImage);
        $images = $this->docker->listImages();

        $this->assertIsArray($images);
        $this->assertNotEmpty($images[0]['RepoTags'][0]);
        $this->assertEquals($this->pullImage, $images[0]['RepoTags'][0]);
    }

    public function testPulledImageExists()
    {
        $this->assertFalse($this->docker->imageExists($this->pullImage));

        $this->docker->pullImage($this->pullImage);
        $this->assertTrue($this->docker->imageExists($this->pullImage));
    }

    public function testImageExistsError()
    {
        $this->expectException(\Exception::class);
        $this->docker->imageExists('\n');
    }

    public function testInspectImage()
    {
        $this->docker->pullImage($this->pullImage);
        $imageDetails = $this->docker->inspectImage($this->pullImage);
        $this->assertIsArray($imageDetails);
        $this->assertArrayHasKey('Id', $imageDetails);
        $this->assertArrayHasKey('RepoTags', $imageDetails);
    }

    public function testInspectImageError()
    {
        $this->expectException(\Exception::class);
        $this->docker->inspectImage('\n');
    }

    public function testDeletingImage()
    {
        $this->docker->pullImage($this->pullImage);
        $this->docker->removeImage($this->pullImage, true);
        $this->assertFalse($this->docker->imageExists($this->pullImage));
    }
}