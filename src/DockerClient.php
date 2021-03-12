<?php

namespace Polkovnik;

use Polkovnik\Exception\DockerSocketNotFound;
use Symfony\Component\HttpClient\CurlHttpClient;

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
        } catch (\Exception $exception) {
            $search = 'failed binding local connection end';
            if (str_contains(strtolower($exception->getMessage()), $search)) {
                $text = sprintf('Could not bind to docker socket at %s', $this->unixSocket);
                throw new DockerSocketNotFound($text);
            }
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

        try {
            $response =  $this->http->request($method, $url, $options);

            if ($resolveResponse) {
                return json_decode($response->getContent(), true);
            } else {
                return $response;
            }
        } catch (\Exception $exception) {
            throw $exception;
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
     * @param string $label
     *
     * @return array|null
     *
     * @throws \Exception
     */
    public function listContainers($label = null)
    {
        $endpoint = '/containers/json';
        $query = ['all' => true];
        if (!empty($label)) {
            $filters = ['label' => [$label]];
            $query['filters'] = json_encode($filters);
        }

        $query = http_build_query($query);
        if (!empty($query)) {
            $endpoint .= '?' . $query;
        }

        return $this->request('GET', $endpoint);
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
     */
    public function stopContainer($id)
    {
        $response = $this->request('POST', sprintf('/containers/%s/stop', $id), [], false);
        if ($response->getStatusCode() === 204) {
            return true;
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
        return $this->request('POST', sprintf('/containers/%s/start', $id));
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
        return $this->request('GET', sprintf('/containers/%s/json', $id));
    }

    /**
     * @param $id
     *
     * @return string
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getContainerLogs($id)
    {
        $response = $this->request('GET', sprintf('/containers/%s/logs?stdout=true&stderr=true&timestamps=false', $id), [], false);

        return $response->getContent();
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
        $response = $this->request('DELETE', sprintf('/containers/%s', $id), [], false);

        return $response->getStatusCode() === 204;
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
        return $this->request('GET', sprintf('/images/%s/json', $nameOrId));
    }
}
