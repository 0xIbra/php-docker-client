<?php

namespace IterativeCode\Component\DockerClient;

use GuzzleHttp\Exception\GuzzleException;
use IterativeCode\Component\DockerClient\Exception\BadParameterException;
use IterativeCode\Component\DockerClient\Exception\DockerConnectionFailed;
use IterativeCode\Component\DockerClient\Exception\ResourceBusyException;
use IterativeCode\Component\DockerClient\Exception\ResourceNotFound;
use GuzzleHttp\Client as HttpClient;

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

        $this->http = new HttpClient([
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
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws GuzzleException
     */
    private function request($method, $url, $options = [], $resolveResponse = true)
    {
        $response =  $this->http->request($method, $url, $options);
        if ($resolveResponse) {
            return json_decode($response->getBody()->getContents(), true);
        } else {
            return $response;
        }
    }

    /**
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws GuzzleException
     */
    public function info()
    {
        return $this->request('GET', '/info');
    }

    /**
     * @param array $options
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws BadParameterException
     * @throws GuzzleException
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
            $filters['filters'] = $options['filters'];
        }

        if (!empty($filters)) {
            $endpoint .= '?' . http_build_query($filters);
        }

        $endpoint = urldecode($endpoint);
        try {
            return $this->request('GET', $endpoint);
        } catch (GuzzleException $e) {
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
     * @throws GuzzleException
     * @throws ResourceNotFound
     */
    public function stopContainer($id)
    {
        try {
            $response = $this->request('POST', sprintf('/containers/%s/stop', $id), [], false);
            if ($response->getStatusCode() === 204) {
                return true;
            }
        } catch (GuzzleException $e) {
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
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws GuzzleException
     * @throws ResourceNotFound
     */
    public function startContainer($id)
    {
        try {
            return $this->request('POST', sprintf('/containers/%s/start', $id));
        } catch (GuzzleException $e) {
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
     * @throws GuzzleException
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
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws GuzzleException
     * @throws ResourceNotFound
     */
    public function inspectContainer($id)
    {
        try {
            return $this->request('GET', sprintf('/containers/%s/json', $id));
        } catch (GuzzleException $e) {
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
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws GuzzleException
     * @throws ResourceNotFound
     */
    public function getContainerStats($id, $oneShot = false)
    {
        try {
            return $this->request('GET', sprintf('/containers/%s/stats?stream=false&one-shot=%s', $id, $oneShot));
        } catch (GuzzleException $e) {
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
     * @throws GuzzleException
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

            $text = $response->getBody()->getContents();
            $text = preg_replace('/(?!\n)[\p{Cc}]/', '', $text);

            return $text;
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, 404);
            }

            throw $e;
        }
    }

    /**
     * @param $id
     *
     * @return bool
     *
     * @throws BadParameterException
     * @throws GuzzleException
     * @throws ResourceBusyException
     * @throws ResourceNotFound
     */
    public function deleteContainer($id)
    {
        try {
            $response = $this->request('DELETE', sprintf('/containers/%s', $id), [], false);

            return $response->getStatusCode() === 204;
        } catch (GuzzleException $e) {
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
     * @return array
     *
     * @throws GuzzleException
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
     * @throws GuzzleException
     */
    public function imageExists($name)
    {
        try {
            $response = $this->inspectImage($name);

            return !empty($response);
        } catch (\Exception $e) {}

        return false;
    }

    /**
     * @param null $label
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws GuzzleException
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
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws GuzzleException
     * @throws ResourceNotFound
     */
    public function inspectImage($nameOrId)
    {
        try {
            return $this->request('GET', sprintf('/images/%s/json', $nameOrId));
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such image: %s', $nameOrId);
                throw new ResourceNotFound($text, 404);
            }

            throw $e;
        }
    }
}
