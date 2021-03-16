<?php

namespace Polkovnik\Component\DockerClient;

use Polkovnik\Component\DockerClient\Exception\BadParameterException;
use Polkovnik\Component\DockerClient\Exception\DockerSocketNotFound;
use Polkovnik\Component\DockerClient\Exception\ResourceBusyException;
use Polkovnik\Component\DockerClient\Exception\ResourceNotFound;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\ClientException;

class DockerClient
{
    /** @var CurlHttpClient */
    private $http;

    /** @var array */
    private $options;

    /** @var string */
    private $dockerApiEndpoint = 'http://localhost/v1.41';

    /** @var string  */
    private $unixSocket = '/var/run/docker.sock';

    /**
     * DockerClient constructor.
     *
     * @param array $options
     *
     * @throws DockerSocketNotFound
     */
    public function __construct($options = [])
    {
        $this->options = $options;

        if (!empty($options['docker_base_uri'])) {
            $this->dockerApiEndpoint = $options['docker_base_uri'];
        }

        $this->http = new CurlHttpClient([
            'base_uri' => $this->dockerApiEndpoint,
        ]);

        if (!empty($options['unix_socket'])) {
            $this->unixSocket = $options['unix_socket'];
        }

        $this->testConnection();
    }

    private function testConnection()
    {
        try {
            return $this->info();
        } catch (\Exception $e) {
            $search = 'failed binding local connection end';
            if (str_contains(strtolower($e->getMessage()), $search)) {
                $text = sprintf('Could not bind to docker socket at %s', $this->unixSocket);
                throw new DockerSocketNotFound($text);
            }

            throw $e;
        }
    }

    /**
     * @param $method
     * @param $url
     * @param array $options
     * @param bool $resolveResponse
     *
     * @return mixed|\Symfony\Contracts\HttpClient\ResponseInterface
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function request($method, $url, $options = [], $resolveResponse = true)
    {
        $options = array_replace_recursive(['bindto' => $this->unixSocket], $options);
        $response =  $this->http->request($method, $url, $options);
        if ($resolveResponse) {
            return json_decode($response->getContent(), true);
        } else {
            return $response;
        }
    }

    /**
     * Retrieves information about the Docker engine.
     *
     * @return mixed|\Symfony\Contracts\HttpClient\ResponseInterface
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function info()
    {
        return $this->request('GET', '/info');
    }

    /**
     * Returns a list of containers
     *
     * @param array $options
     *
     * @return array|null
     *
     * @throws BadParameterException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function listContainers($options = [])
    {
        $endpoint = '/containers/json';
        $query = [];
        $filters = [];
        if (!empty($options['label'])) {
            $filters['label'] = [$options['label']];
        }
        if (!empty($options['all'])) {
            $filters['all'] = $options['all'];
        }
        if (!empty($options['limit'])) {
            $filters['limit'] = $options['limit'];
        }

        $query['filters'] = json_encode($filters);
        $query = http_build_query($query);
        if (!empty($query)) {
            $endpoint .= '?' . $query;
        }

        try {
            return $this->request('GET', $endpoint);
        } catch (ClientException $e) {
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
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws ResourceNotFound
     */
    public function stopContainer($id)
    {
        try {
            $response = $this->request('POST', sprintf('/containers/%s/stop', $id));
            if ($response->getStatusCode() === 204) {
                return true;
            }
        } catch (ClientException $e) {
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
     * @return mixed
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function startContainer($id)
    {
        try {
            return $this->request('POST', sprintf('/containers/%s/start', $id));
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, $e->getCode(), $e);
            }

            throw $e;
        }
    }

    /**
     * @param $name
     * @param $payload
     *
     * @return string|false
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
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
     * @return array
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function inspectContainer($id)
    {
        try {
            return $this->request('GET', sprintf('/containers/%s/json', $id));
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, 404, $e);
            }

            throw $e;
        }
    }

    /**
     * @param $id
     * @param bool $oneShot
     *
     * @return mixed|\Symfony\Contracts\HttpClient\ResponseInterface
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getContainerStats($id, $oneShot = false)
    {
        try {
            return $this->request('GET', sprintf('/containers/%s/stats?stream=false&one-shot=%s', $id, $oneShot));
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, 404, $e);
            }

            throw $e;
        }
    }

    /**
     * @param $id
     * @param string $level
     *
     * @return string
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
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
            $text = preg_replace('/(?!\n)[\p{Cc}]/', '', $text);

            return $text;
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, 404, $e);
            }

            throw $e;
        }
    }

    /**
     * @param string $id
     *
     * @return bool
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function deleteContainer($id)
    {
        try {
            $response = $this->request('DELETE', sprintf('/containers/%s', $id));

            return $response->getStatusCode() === 204;
        } catch (ClientException $e) {
            $code = $e->getCode();
            if ($code === 400) {
                throw new BadParameterException($e->getMessage(), 400, $e);
            } else if ($code === 404) {
                $text = sprintf('No such container: %s', $id);
                throw new ResourceNotFound($text, 404, $e);
            } else if ($code === 409) {
                $text = sprintf('You cannot remove a running container: %s. Stop the container before attempting removal or force remove', $id);
                throw new ResourceBusyException($text, 409, $e);
            }

            throw $e;
        }
    }

    /**
     * Deletes stopped containers
     *
     * @return array
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
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

    public function imageExists($name)
    {
        try {
            $response = $this->inspectImage($name);

            return !empty($response);
        } catch (\Exception $e) {}

        return false;
    }

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

    public function inspectImage($nameOrId)
    {
        try {
            return $this->request('GET', sprintf('/images/%s/json', $nameOrId));
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                $text = sprintf('No such image: %s', $nameOrId);
                throw new ResourceNotFound($text, 404, $e);
            }

            throw $e;
        }
    }
}
