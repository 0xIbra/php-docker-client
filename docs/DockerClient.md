**IterativeCode \ Component \ DockerClient**

## DockerClient

#### Table of Contents
- [__construct()](#__construct)
- [listContainers()](#listcontainers) : array
- [stopContainer($id)](#stopcontainer) : bool
- [startContainer($id)](#startcontainer)
- [runContainer($name, $payload)](#runcontainer) : string | false
- [deleteContainer($id)](#deletecontainer): boolean
- [inspectContainer($id)](#inspectcontainer)
- [getContainerStats($id)](#getcontainerstats)
- [getContainerLogs($id)](#getcontainerlogs): string
- [pruneContainers($label = null)](#prunecontainers) : array
- [listImages()](#listimages) : array
- [inspectImage($id)](#inspectimage) : array
- [imageExists($id)](#imageexists) : bool

#### Methods

#### __construct()
DockerClient constructor.
```
public __construct($options = [])
```
###### Parameters
$options : array
###### Tags
**throws**
- DockerSocketNotFound


#### listContainers()
Returns a list of containers.
```
public listContainers() : array
```

###### Return values
array -


#### stopContainer()
Stops a running container.
```
public stopContainer($id) : bool
```

###### Parameters
$id: string

###### Return values
bool -


#### startContainer()
Starts a newly created container.
```
public startContainer($id) : bool
```

###### Parameters
$id: string

###### Return values
bool -


#### runContainer()
Creates and starts a new container.
```
public runContainer($name, $config) : string|false
```

###### Parameters
$name: string
$config: array - refer to official [Docker API documentation](https://docs.docker.com/engine/api/v1.41/#operation/ContainerCreate) for more information

###### Return values
string -
bool -

#### deleteContainer()
Removes a stopped container.
```
public deleteContainer() : bool
```

###### Parameters
$id: string

###### Return values
bool -


#### inspectContainer()
Retrieves all container relatated information.
```
public inspectContainer($id) : array
```

###### Parameters
$id: string

###### Return values
array -



#### getContainerStats()
Retrive container usage statistics.
```
public getContainerStats($id) : array
```

###### Parameters
$id: string

###### Return values
array -


#### getContainerLogs()
Retrieves logs from container.
```
public getContainerLogs($id) : string
```
###### Parameters
$id: string

###### Return values
string -


#### pruneContainers()
Deletes all stopped containers.
```
public pruneContainers() : array
```

###### Return values
array -



#### listImages()
Returns a list of containers.
```
public listImages() : array
```

###### Return values
array -



#### inspectImage()
Retrieves all container relatated information.
```
public inspectImage($id) : array
```

###### Parameters
$id: string

###### Return values
array -


#### imageExists()
Retrieves all container relatated information.
```
public imageExists($id) : bool
```

###### Parameters
$id: string

###### Return values
bool - 
