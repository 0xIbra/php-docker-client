# DockerClient

* Full name: `\IterativeCode\Component\DockerClient\DockerClient`

***

## Methods


### __construct

DockerClient constructor.

```php
public __construct(array $options = []): mixed
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$options` | **array** |  |


***

### info

```php
public info(): array
```

***

### version

```php
public version(): array
```

***

### listContainers

```php
public listContainers(array $options = []): array
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$options` | **array** |  |

***

### stopContainer

```php
public stopContainer( $id): bool
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$id` | **** |  |

***

### startContainer

```php
public startContainer( $id): array
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$id` | **** |  |

***

### runContainer

```php
public runContainer( $name,  $payload): false|string
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$name` | **** |  |
| `$payload` | **** |  |

***

### inspectContainer

```php
public inspectContainer( $id): array
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$id` | **** |  |

***

### getContainerStats

```php
public getContainerStats( $id, false $oneShot = false): array
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$id` | **** |  |
| `$oneShot` | **false** |  |

***

### getContainerLogs

```php
public getContainerLogs( $id, string $level = &#039;all&#039;): string
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$id` | **** |  |
| `$level` | **string** |  |

***

### deleteContainer

```php
public deleteContainer( $id, bool $force = false): bool
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$id` | **** |  |
| `$force` | **bool** |  |

***

### pruneContainers

Deletes stopped containers

```php
public pruneContainers(mixed $label = null): array
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$label` | **mixed** |  |

***

### imageExists

```php
public imageExists( $name): bool
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$name` | **** |  |

***

### listImages

```php
public listImages(null $label = null): array
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$label` | **null** |  |

***

### inspectImage

```php
public inspectImage( $nameOrId): array
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$nameOrId` | **** |  |

***

### pullImage

```php
public pullImage( $image): void
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$image` | **** |  |

***

### removeImage

```php
public removeImage( $image,  $force = false): void
```
**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$image` | **** |  |
| `$force` | **** |  |

***

> Automatically generated from source code comments on 2022-09-27 using [phpDocumentor](http://www.phpdoc.org/) and [saggre/phpdocumentor-markdown](https://github.com/Saggre/phpDocumentor-markdown)
