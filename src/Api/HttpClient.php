<?php
namespace SkypeBot\Api;

use SkypeBot\Exception\CurlException;
use SkypeBot\Exception\ResponseException;
use SkypeBot\Interfaces\ApiLogger;
use SkypeBot\Storage\SimpleApiLogger;

class HttpClient {
    const METHOD_POST = 'post';
    const METHOD_GET = 'get';

    protected $headers = array();
    protected $cookies = array();
    protected $error;
    protected $result;
    protected $info;
    private static $instance;

    /**
     * @var ApiLogger
     */
    private $logger;

    private function __construct()
    {
    }

    /**
     * @return HttpClient
     */
    public static function getInstance()
    {
        if (static::$instance == null) {
            static::$instance = new HttpClient();
        }
        return static::$instance;
    }

    /**
     * @param ApiLogger $logger
     * @return $this
     */
    public function setLogger(ApiLogger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param $header
     * @param null $value
     * @return $this
     */
    public function setHeader($header, $value = null)
    {
        if ($value === null) {
            $this->headers[] = $header;
        } else {
            $this->headers[$header] = $value;
        }
        return $this;
    }

    /**
     * @param $url
     * @param array $params
     * @return bool
     */
    public function get($url, $params = array())
    {
        if (empty($params)) {
            $params = array();
        }
        $strParams = http_build_query($params);
        $ch = $this->initCurl();
        if (strpos($url, '?')) {
            if (substr($url, -1) == '&') {
                $url .= $strParams;
            } else {
                $url .= '&' . $strParams;
            }
        } else {
            if (count($params)) {
                $url .= '?' . $strParams;
            }
        }
        $this->log('>>>>>> GET >>>>>>');
        $this->log($url);
        curl_setopt($ch, CURLOPT_URL, $url);

        $this->result = $this->fetchResult($ch);
        if (!$this->result) {
            $this->error = curl_error($ch);
        }
        $this->closeCurl($ch);
        return $this->result;
    }

    public function post($url, $params = array())
    {
        $this->log('>>>>>> POST >>>>>>');
        $this->log($url);
        $this->log(print_r($params, true));
        if (is_string($params)) {
            $strParams = $params;
        } else {
            $strParams = http_build_query($params);
        }
        $ch = $this->initCurl();
        
        curl_setopt($ch,CURLOPT_URL, $url);
        if (!is_string($params)) {
            curl_setopt($ch, CURLOPT_POST, count($params));
        }
        curl_setopt($ch,CURLOPT_POSTFIELDS, $strParams);

        $this->result = $this->fetchResult($ch);
        if (!$this->result) {
            $this->error = curl_error($ch);
            $this->log($this->error);
        }
        $this->closeCurl($ch);
        return $this->result ? true : false;
    }

    public function getError() {
        return $this->error;
    }

    protected function fetchResult($ch)
    {
        $this->log('===============================');
        $response = curl_exec($ch);
        $this->log($response);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);
        return $body;
    }

    public function getReturnCode()
    {
        if (is_array($this->info) && isset($this->info[CURLINFO_HTTP_CODE])) {
            return $this->info[CURLINFO_HTTP_CODE];
        }
        return null;
    }

    private function initCurl()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders());
        return $ch;
    }

    private function closeCurl($ch)
    {
        $this->info = curl_getinfo($ch);
        curl_close($ch);
    }

    private function buildHeaders()
    {
        $headers = array();
        foreach ($this->headers as $key => $value) {
            if (is_numeric($key)) {
                $headers[] = $value;
            } else {
                $headers[] = $key . ': ' . $value;
            }
        }
        return $headers;
    }

    private function log($message)
    {
        if ($this->logger === null) {
            return;
        }
        $this->logger->log($message);
    }
}