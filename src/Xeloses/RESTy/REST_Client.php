<?php

/*
 * REST API client.
 *
 * @author     Xeloses (https://github.com/Xeloses)
 * @package    RESTy (https://github.com/Xeloses/resty)
 * @version    1.0
 * @copyright  Xeloses 2019
 * @license    MIT (http://en.wikipedia.org/wiki/MIT_License)
 */

namespace Xeloses\RESTy;

/**
 * REST_Client class
 *
 * @package RESTy
 *
 * @method REST_Client   addHeader(string $name, string $value)
 * @method REST_Client   addHeaders(array $headers)
 * @method ?object|string get(string $endpoint, ?array $data, ?array $headers)
 * @method ?object|string post(string $endpoint, ?array $data, ?array $headers)
 * @method ?object|string head(string $endpoint, ?array $headers)
 * @method ?object|string put(string $endpoint, ?array $data, ?array $headers)
 * @method ?object|string patch(string $endpoint, ?array $data, ?array $headers)
 * @method ?object|string delete(string $endpoint, ?array $headers)
 * @method ?object|string customRequest(string $endpoint, string $method, ?array $data, ?array $headers)
 */
class REST_Client{
    /**
     * REST API service base URL.
     *
     * @var string
     */
    protected $base_url;

    /**
     * REST API service response content-type.
     *
     * @var int
     */
    protected $mode;

    /**
     * Response content-types.
     *
     * @const int
     */
    const MODE_JSON = 1;
    const MODE_XML  = 2;

    /**
     * HTTP headers for REST API.
     *
     * @var array
     */
    protected $headers;

    /**
     * Default CURL options
     *
     * @var array
     */
    protected $curl_options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_HTTPHEADER => []
    ];

    /**
     * Constructor.
     *
     * @param string $url  Base REST service URL
     * @param int    $mode Responce content-type
     *
     * @throws Exception                when cURL unavailable
     * @throws InvalidArgumentException
     */
    public function __construct(string $url, int $mode = self::MODE_JSON)
    {
        if(!function_exists('curl_init'))
        {
            throw new \Exception('CURL module required. See https://www.php.net/manual/book.curl.php for more info and installation instructions.');
        }

        if(!$mode)
        {
            $mode = self::MODE_JSON;
        }
        elseif($mode != self::MODE_JSON)
        {
            if(!in_array($mode,[self::MODE_JSON,self::MODE_XML])){
                throw new \InvalidArgumentException('Unsupported mode.');
            }
            if($mode == self::MODE_XML && !function_exists('simplexml_load_string')){
                throw new \Exception('SimpleXML module required. See https://www.php.net/manual/book.simplexml.php for more info and installation instructions.');
            }
        }

        $this->mode = $mode;

        if(empty($url))
        {
            throw new \InvalidArgumentException('REST service URL required.');
        }
        elseif(!preg_match('/^http[s]?:\/\/[\w\-]+.[a-z]{2,10}\/[\S]+$/i',$url))
        {
            throw new \InvalidArgumentException('Invalid REST service URL.');
        }

        $this->base_url = $url;

        $mime = $this->getContentType();
        $this->headers = [
            'Content-Type' => $mime,
            'Accept'       => $mime
        ];
    }

    /**
     * Add HTTP header to all requests.
     *
     * @param string $name
     * @param string $value
     *
     * @return self
     */
    public function addHeader(string $name, string $value)
    {
        if(!empty($name) && !empty($value))
        {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Add multiple HTTP headers to all requests.
     *
     * @param array $headers
     *
     * @return self
     */
    public function addHeaders(array $headers)
    {
        foreach($headers as $name => $value)
        {
            $this->addHeader($name,$value);
        }

        return $this;
    }

    /**
     * "GET" request.
     *
     * @param string $endpoint
     * @param array  $data
     * @param array  $headers
     *
     * @return object|string|null
     *
     * @throws InvalidArgumentException
     */
    public function get(string $endpoint, array $data = [], array $headers = [])
    {
        if(!empty($data))
        {
            $endpoint .= ((strpos($endpoint,'?') === false)?'?':'&').http_build_query($data);
        }

        return $this->request($endpoint,'GET',$headers);
    }

    /**
     * "POST" request.
     *
     * @param string        $endpoint
     * @param string|array  $data      String will be sent as "plain/text" (if not defined in $headers), array - as "form" or "multipart" (if includes file links)
     * @param array         $headers
     *
     * @return object|string|null
     */
    public function post(string $endpoint, mixed $data = [], array $headers = [])
    {
        if(is_array($data)){
            $options = [
                CURLOPT_POSTFIELDS => $this->processData($data)
            ];

            if(is_array($data))
            {
                $headers['Content-Length'] = strlen($data);
                $headers['Content-Type'] = 'multipart/form-data';
            }
            else
            {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }
        else
        {
            $options = [
                CURLOPT_POSTFIELDS => $data
            ]

            if(!in_array('content-type',array_map(strtolower,array_keys($headers))))
            {
                $headers['Content-Type'] = 'text/plain';
            }
        }

        return $this->request($endpoint,'POST',$headers,$options);
    }

    /**
     * "HEAD" request.
     *
     * @param string $endpoint
     * @param array  $headers
     *
     * @return object|string|null
     */
    public function head(string $endpoint, array $headers = [])
    {
        $options = [
            CURLOPT_NOBODY => true
        ];

        return $this->request($endpoint,'HEAD',$headers,$options);
    }

    /**
     * "PUT" request.
     *
     * @param string $endpoint
     * @param string|array  $data      String will be sent as "raw" (if not defined in $headers), array - as "form" or "multipart" (if includes file links)
     * @param array  $headers
     *
     * @return object|string|null
     */
    public function put(string $endpoint, mixed $data = [], array $headers = [])
    {
        if(is_array($data)){
            $options = [
                CURLOPT_POSTFIELDS => $this->processData($data)
            ];

            if(is_array($data))
            {
                $headers['Content-Length'] = strlen($data);
                $headers['Content-Type'] = 'multipart/form-data';
            }
            else
            {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }
        else
        {
            $options = [
                CURLOPT_POSTFIELDS => $data
            ]

            if(!in_array('content-type',array_map(strtolower,array_keys($headers))))
            {
                $headers['Content-Type'] = 'text/plain';
            }
        }

        return $this->request($endpoint,'PUT',$headers,$options);
    }

    /**
     * "PATCH" request.
     *
     * @param string $endpoint
     * @param array  $data
     * @param array  $headers
     *
     * @return object|string|null
     */
    public function patch(string $endpoint, array $data = [], array $headers = [])
    {
        $options = [
            CURLOPT_POSTFIELDS => http_build_query($data),
        ];

        $headers['Content-Length'] = strlen($data);
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';

        return $this->request($endpoint,'PATCH',$headers,$options);
    }

    /**
     * "DELETE" request.
     *
     * @param string $endpoint
     * @param array  $headers
     *
     * @return object|string|null
     */
    public function delete(string $endpoint, array $headers = [])
    {
        return $this->request($endpoint,'DELETE',$headers);
    }

    /**
     * Custom request.
     *
     * @param string $endpoint
     * @param string $method
     * @param mixed  $data
     * @param array  $headers
     *
     * @return string|null
     *
     * @throws InvalidArgumentException
     */
    public function customRequest(string $endpoint, string $method = '', mixed $data = null, array $headers = [])
    {
        if(empty($method))
        {
            throw new \InvalidArgumentException('Request method required.');
        }

        $options = [];
        if(!is_null($data) && !empty($data)){
            $options = [
                CURLOPT_POSTFIELDS => $data,
            ];
        }

        return $this->request($endpoint,$method,$headers,$options);
    }

    /**
     * HTTP (cURL) request.
     *
     * @internal
     *
     * @param string $endpoint
     * @param string $method
     * @param array  $headers
     * @param array  $options  Additional cURL options
     *
     * @return object|string|null
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function request(string $endpoint, string $method = 'GET', array $headers = [], array $options = [])
    {
        // Validate params: endpoint
        if(!isset($endpoint) || empty($endpoint))
        {
            throw new \InvalidArgumentException('Endpoint URL required.');
        }

        // assemble request URL:
        $url = rtrim($this->base_url,'/').'/'.ltrim($endpoint,'/');

        // init cURL:
        $curl = curl_init($url);
        if($curl === false)
        {
            throw new \RuntimeException(curl_error($curl));
        }

        // cURL options:
        foreach($this->curl_options as $option => $value)
        {
            if(!array_key_exists($option,$options))
            {
                $options[$option] = $value;
            }
        }

        // Headers:
        $options[CURLOPT_HTTPHEADER] = $this->processHeaders($headers);

        // request method:
        $method = strtoupper($method);
        switch($method)
        {
            case 'GET':
                //$options[CURLOPT_HTTPGET] => true;
                break;
            case 'POST':
                $options[CURLOPT_POST] = true;
                break;
            default:
                $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($curl,$options);

        // send request:
        $response = curl_exec($curl);
        if($response === false)
        {
            throw new \RuntimeException(curl_error($curl));
        }

        curl_close($curl);

        // process response:
        if($response && !empty(trim($response)))
        {
            switch($this->mode)
            {
                case self::MODE_JSON:
                    $_response = json_decode($response,false,512,JSON_BIGINT_AS_STRING|JSON_INVALID_UTF8_SUBSTITUTE);
                    if(json_last_error() === JSON_ERROR_NONE)
                    {
                        return $_response;
                    }
                    break;
                case self::MODE_XML:
                    $_response = simplexml_load_string($response,'SimpleXMLElement',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
                    if($_response !== false)
                    {
                        return $_response;
                    }
                    break;
            }
            return $response;
        }
        else
        {
            return null;
        }
    }

    /**
     * Converts assoc array of headers to cURL compatoble array.
     *
     * @internal
     *
     * @param array $headers
     *
     * @return array
     */
    protected function processHeaders(array $headers = [])
    {
        $result = [];

        foreach(array_merge($this->headers,$headers) as $name => $value)
        {
            $result[] = $name.': '.$value;
        }

        return $result;
    }

    /**
     * Prepare data for use in POST/PUT requests.
     *
     * @internal
     *
     * @param array $data
     *
     * @return array|string
     */
    protected function processData(array $data = [])
    {
        if(count($data))
        {
            $multipart = false;
            foreach($data as $item)
            {
                if(is_string($item) && substr($item,0,1) == '@' && is_file(substr($item,1)))
                {
                    $multipart = true;
                    break;
                }
            }

            if(!$multipart)
            {
                return http_build_query($data);
            }
            return $data;
        }
        else
        {
            return '';
        }
    }

    /**
     * Get expected content-type of REST API service response.
     *
     * @internal
     *
     * @return string
     */
    protected function getContentType()
    {
        switch($this->mode)
        {
            case self::MODE_JSON:
                return 'application/json';
            case self::MODE_XML:
                return 'application/xml';
        }
        return null;
    }
}
