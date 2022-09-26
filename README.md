PHP Docker client
==================
[![CircleCI](https://dl.circleci.com/status-badge/img/gh/ibra-akv/php-docker-client/tree/ci.svg?style=svg)](https://dl.circleci.com/status-badge/redirect/gh/ibra-akv/php-docker-client/tree/ci)
[![Coverage Status](https://coveralls.io/repos/ibra-akv/php-docker-client/badge.svg?branch=ci&service=github)](https://coveralls.io/github/ibra-akv/php-docker-client?branch=ci)

> Docker API driver for PHP.

Docker configuration
--------------------
Docker Engine API must be exposed on a local port in order to be able to connect.

##### 1. Edit the `docker.service` which by default on debian is located at `/lib/systemd/system/docker.service`  

From this:
```shell
# /lib/systemd/system/docker.service
...
ExecStart=/usr/bin/dockerd -H fd:// --containerd=/run/containerd/containerd.sock
...
```

To this:
```shell
# /lib/systemd/system/docker.service
...
ExecStart=/usr/bin/dockerd
...
```

##### 2. Edit `/etc/docker/daemon.json` to expose docker api at `127.0.0.1:2375`
Add `hosts` to the json file as next:
```json
{
  ...
  "hosts": ["fd://", "tcp://127.0.0.1:2375"]
  ...
}
```

##### 3. Restart Docker completely
```shell
systemctl daemon-reload
systemctl restart docker
service docker restart
```

Installation
------------
    composer require ibra-akv/php-docker-client

Usage
-----

Initialize client

```php
use IterativeCode\Component\DockerClient\DockerClient;

$docker = new DockerClient([
    'local_endpoint' => 'http://localhost:2375/v1.41', # Optional (default: http://localhost:2375)
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


License
-------
 - [Review](LICENSE)
