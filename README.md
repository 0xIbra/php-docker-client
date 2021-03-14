PHP Docker client
==================
> Docker API driver for PHP.

Installation
------------
    composer require polkovnik/php-docker-client

Usage
-----

Initialize client

```php
use Polkovnik\Component\DockerClient\DockerClient;

$docker = new DockerClient([
    'docker_base_uri' => 'http://localhost/v1.41', # Optional (default: http://localhost/v1.41)
    'unix_socket' => '/var/run/docker.sock' # Optional (defaukt: /var/run/docker.sock)
]);

```

Check if image exists
```php
$exists = $docker->imageExists('436aed837ea2');
# true | false

$details = $docker->inspectImage('436aedXXXXXX');
# array | @throws Exception

```

API Reference
-------------
- #### [DockerClient](docs/DockerClient.md)

Tested Docker versions
--------
- #### [1.41](https://docs.docker.com/engine/api/v1.41/)
