<?php

namespace IterativeCode\Component\DockerClient;

use IterativeCode\Component\DockerClient\Exception\BadParameterException;
use IterativeCode\Component\DockerClient\Exception\DockerConnectionFailed;
use IterativeCode\Component\DockerClient\Exception\ResourceBusyException;
use IterativeCode\Component\DockerClient\Exception\ResourceNotFound;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DockerClient
{
    /** @var HttpClient */
    private $http;

    /** @var array */
    private $options;

    /** @var string */
    private $dockerApiEndpoint = 'http://localhost:2375';

    /**
     * DockerClient constructor.
     *
     * @param array $options
     *
     * @throws DockerConnectionFailed
     */
    public function __construct($options = [])
    {
        $this->options = $options;

        if (!empty($options['local_endpoint'])) {
            $this->dockerApiEndpoint = $options['local_endpoint'];
        }

        $this->http = HttpClient::create([
            'base_uri' => $this->dockerApiEndpoint,
        ]);

        $this->testConnection();
    }

    private function testConnection()
    {
        try {
            return $this->info();
        } catch (\Exception $e) {
            $text = sprintf('Docker API connection failed: %s', $this->dockerApiEndpoint);

            throw new DockerConnectionFailed($text);
        }
    }

    /**
     * @param $method
     * @param $url
     * @param array $options
     * @param bool $resolveResponse
     *
     * @return array|ResponseInterface
     *
     * @throws ExceptionInterface
     */
    private function request($method, $url, $options = [], $resolveResponse = true)
    {
        $response =  $this->http->request($method, $url, $options);
        if ($resolveResponse) {
            return json_decode($response->getContent(), true);
        } else {
            return $response;
        }
    }

    /**
     * @return ResponseInterface
     *
     * @throws ExceptionInterface
     */
    public function info()
    {
        return $this->request('GET', '/info');
    }

    /**
     * @return ResponseInterface
     *
     * @throws ExceptionInterface
     */
    public function version()
    {
        return $this->request('GET', '/version');
    }

    /**
     * @param array $options
     *
     * @return array
     *
     * @throws BadParameterException
     * @throws ExceptionInterface
     */
    public function listContainers($options = [])
    {
        $endpoint = '/containers/json';
        $filters = [];
        if (!empty($options['all'])) {
            $filters['all'] = $options['all'];
        }
        if (!empty($options['limit'])) {
            $filters['limit'] = $options['limit'];
        }

        if (!empty($options['filters'])) {
            $filters['filters'] = json_encode($options['filters']);
        }

        if (!empty($filters)) {
            $endpoint .= '?' . http_build_query($filters);
        }

        $endpoint = urldecode($endpoint);
        try {
            return $this->request('GET', $endpoint);
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code === 400) {
                throw new BadParameterException($e->getMessage(), $e->getCode());
            }

            throw $e;
        }
    }

    /**
     * @param $id
     *
     * @return bool
     *
     * @throws ExceptionInterface
     * @throws ResourceNotFound
     */
    public function stopContainer($id)
    {
        try {
            $this->request('POST', sprintf('/containers/%s/stop', $id), []);

            return true;
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, $e->getCode());
            }

            throw $e;
        }

        return false;
    }

    /**
     * @param $id
     *
     * @return ResponseInterface
     *
     * @throws ExceptionInterface
     * @throws ResourceNotFound
     */
    public function startContainer($id)
    {
        try {
            return $this->request('POST', sprintf('/containers/%s/start', $id));
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, $e->getCode());
            }

            throw $e;
        }
    }

    /**
     * @param $name
     * @param $payload
     *
     * @return false|mixed
     *
     * @throws ExceptionInterface
     * @throws ResourceNotFound
     */
    public function runContainer($name, $payload)
    {
        $endpoint = sprintf('/containers/create?name=%s', $name);
        $response = $this->request('POST', $endpoint, ['json' => $payload]);

        if (!empty($response['Id'])) {
            $id = $response['Id'];
            $this->startContainer($id);

            return $id;
        }

        return false;
    }

    /**
     * @param $id
     *
     * @return ResponseInterface
     *
     * @throws ExceptionInterface
     * @throws ResourceNotFound
     */
    public function inspectContainer($id)
    {
        try {
            return $this->request('GET', sprintf('/containers/%s/json', $id));
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, 404);
            }

            throw $e;
        }
    }

    /**
     * @param $id
     * @param false $oneShot
     *
     * @return ResponseInterface
     *
     * @throws ExceptionInterface
     * @throws ResourceNotFound
     */
    public function getContainerStats($id, $oneShot = false)
    {
        try {
            return $this->request('GET', sprintf('/containers/%s/stats?stream=false&one-shot=%s', $id, $oneShot));
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, 404);
            }

            throw $e;
        }
    }

    /**
     * @param $id
     * @param string $level
     *
     * @return string|string[]|null
     *
     * @throws ExceptionInterface
     * @throws ResourceNotFound
     */
    public function getContainerLogs($id, $level = 'all')
    {
        $endpoint = sprintf('/containers/%s/logs', $id);
        $query = [];
        if ($level === 'all') {
            $query = ['stdout' => 'true', 'stderr' => 'true'];
        } else if ($level === 'out') {
            $query = ['stdout' => 'true'];
        } else if ($level === 'error') {
            $query = ['stderr' => 'true'];
        }

        if (!empty($query)) {
            $endpoint .= '?' . http_build_query($query);
        }

        try {
            $response = $this->request('GET', $endpoint, [], false);

            $text = $response->getContent();
            $text = utf8_encode($text);

            return $text;
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, 404);
            }

            throw $e;
        }
    }

    /**
     * @param $id
     * @param bool $force
     *
     * @return bool
     *
     * @throws BadParameterException
     * @throws ExceptionInterface
     * @throws ResourceBusyException
     * @throws ResourceNotFound
     */
    public function deleteContainer($id, $force = false)
    {
        $endpoint = sprintf('/containers/%s', $id);
        if ($force === true) {
            $endpoint .= '?' . http_build_query(['force' => 'true']);
        }

        try {
            $this->request('DELETE', $endpoint, []);

            return true;
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code === 400) {
                throw new BadParameterException($e->getMessage(), 400);
            } else if ($code === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, 404);
            } else if ($code === 409) {
                $text = sprintf('You cannot remove a running container: %s. Stop the container before attempting removal or force remove', $id);
                throw new ResourceBusyException($text, 409);
            }

            throw $e;
        }
    }

    /**
     * Deletes stopped containers
     *
     * @return ResponseInterface
     *
     * @throws ExceptionInterface
     */
    public function pruneContainers($label = null)
    {
        $endpoint = '/containers/prune';
        if (!empty($label)) {
            $query = [
                'filters' => json_encode(['label' => [$label]]),
            ];

            $endpoint .= '?' . http_build_query($query);
        }

        return $this->request('POST', $endpoint);
    }


//    DOCKER IMAGES

    /**
     * @param $name
     *
     * @return bool
     *
     * @throws ExceptionInterface
     */
    public function imageExists($name)
    {
        try {
            $response = $this->inspectImage($name);

            return !empty($response);
        } catch (\Exception $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        return false;
    }

    /**
     * @param null $label
     *
     * @return ResponseInterface
     *
     * @throws ExceptionInterface
     */
    public function listImages($label = null)
    {
        $endpoint = '/images/json';
        if (!empty($label)) {
            $query = [
                'filters' => json_encode(['label' => [$label]]),
            ];

            $endpoint .= '?' . http_build_query($query);
        }

        return $this->request('GET', $endpoint);
    }

    /**
     * @param $nameOrId
     *
     * @return array|ResponseInterface
     *
     * @throws ExceptionInterface
     * @throws ResourceNotFound
     */
    public function inspectImage($nameOrId)
    {
        try {
            $nameOrId = urlencode($nameOrId);
            return $this->request('GET', sprintf('/images/%s/json', $nameOrId));
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such image: %s', $nameOrId);
                throw new ResourceNotFound($text, 404);
            }

            throw $e;
        }
    }

    /**
     * @param $image
     *
     * @return void
     *
     * @throws ExceptionInterface
     */
    public function pullImage($image)
    {
        $endpoint = '/images/create';
        $opts = ['fromImage' => $image];
        $endpoint = $endpoint . '?' . http_build_query($opts);

        $this->request('POST', $endpoint);
    }

    /**
     * @param $image
     * @param $force
     *
     * @return void
     *
     * @throws ExceptionInterface
     */
    public function removeImage($image, $force = false)
    {
        $endpoint = sprintf('/images/%s', $image);

        if ($force === true) {
            $endpoint .= '?' . http_build_query(['force' => 'true']);
        }

        $this->request('DELETE', $endpoint);
    }
}
