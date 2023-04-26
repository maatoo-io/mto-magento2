<?php

namespace Maatoo\Maatoo\Adapter;


class Curl implements AdapterInterface
{
    private $config;

    private $auth;

    private $clientResolver;

    private $logger;

    public function __construct(
        \Maatoo\Maatoo\Model\Config\Config $config,
        \Maatoo\Maatoo\Auth\BasicAuth $auth,
        \Maatoo\Maatoo\Model\Client\ClientResolver $clientResolver,
        \Maatoo\Maatoo\Logger\Logger $logger
    )
    {
        $this->config = $config;
        $this->auth = $auth;
        $this->clientResolver = $clientResolver;
        $this->logger = $logger;
    }

    private function getUrl($endpoint)
    {
        $url = $this->config->getMaatooUrl() . 'api/';
        return $url . $endpoint;
    }

    public function makeRequest(string $endpoint, array $parameters = [], $method = 'GET', array $settings = [])
    {
        $url = $this->getUrl($endpoint);
        $username = $this->config->getMaatooUser();
        $password = $this->clientResolver->getPassword($this->config->getMaatooPassword());
        $this->auth->setup($username, $password);
        $result = [];

        try {
            $result = $this->auth->makeRequest($url, $parameters, $method, $settings);
            $this->logger->info("Request successful. Data: url='" . $url . "' parameters='" . json_encode($parameters) . "' method='" . $method . "'");
            if ($this->config->isDebugEnabled()) {
                $this->logger->debug("Request result: " . json_encode($result));
            }
        } catch (\Exception $e) {
            $this->logger->error("Request failed. Data: url='".$url."' parameters='".json_encode($parameters)."' method='".$method."' error='". $e->getMessage()."'");
        }
        return $result;
    }
}
