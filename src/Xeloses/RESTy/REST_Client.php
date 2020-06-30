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
 * @method string        getContentType()
 * @method REST_Client   addHeader(string $name, string $value)
 * @method REST_Client   addHeaders(array $headers)
 * @method ?object|string get(string $endpoint, ?array $data, ?array $headers)
 * @method ?object|string post(string $endpoint, ?array $data, ?array $headers)
 * @method ?object|string head(string $endpoint, ?array $headers)
 * @method ?object|string put(string $endpoint, ?array $data, ?array $headers)
 * @method ?object|string patch(string $endpoint, ?array $data, ?array $headers)
 * @method ?object|string delete(string $endpoint, ?array $headers)
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

        $this->headers = [
            'Content-Type' => $this->getContentType()
        ];
    }

    /**
     * Get expected content-type of REST API service response.
     *
     * @return string
     */
    public function getContentType()
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
        if(!isset($endpoint) || empty($endpoint))
        {
            throw new \InvalidArgumentException('Endpoint URL required.');
        }

        $url = rtrim($this->base_url,'/').'/'.ltrim($endpoint,'/');
        if(!empty($data))
        {
            $url .= ((strpos($url,'?') === false)?'?':'&').http_build_query($data);
        }

        return $this->request($url,'GET',$headers);
    }

    /**
     * "POST" request.
     *
     * @param string $endpoint
     * @param array  $data
     * @param array  $headers
     *
     * @return object|string|null
     *
     * @throws InvalidArgumentException
     */
    public function post(string $endpoint, array $data = [], array $headers = [])
    {
        if(empty($endpoint))
        {
            throw new \InvalidArgumentException('Endpoint URL required.');
        }

        $options = [
            CURLOPT_POSTFIELDS => $this->processData($data)
        ];

        $headers = [];
        if(!is_array($data))
        {
            $headers['Content-Length'] = strlen($data);
            $headers['Content-Type'] = 'multipart/form-data';
        }

        return $this->request($url,'POST',$headers,$options);
    }

    /**
     * "HEAD" request.
     *
     * @param string $endpoint
     * @param array  $headers
     *
     * @return object|string|null
     *
     * @throws InvalidArgumentException
     */
    public function head(string $endpoint, array $headers = [])
    {
        if(empty($endpoint))
        {
            throw new \InvalidArgumentException('Endpoint URL required.');
        }

        $options = [
            CURLOPT_NOBODY => true
        ];

        return $this->request($url,'HEAD',$headers,$options);
    }

    /**
     * "PUT" request.
     *
     * @param string $endpoint
     * @param array  $data
     * @param array  $headers
     *
     * @return object|string|null
     *
     * @throws InvalidArgumentException
     */
    public function put(string $endpoint, array $data = [], array $headers = [])
    {
        if(empty($endpoint))
        {
            throw new \InvalidArgumentException('Endpoint URL required.');
        }

        $options = [
            CURLOPT_POSTFIELDS => $this->processData($data)
        ];

        $headers = [];
        if(!is_array($data))
        {
            $headers['Content-Length'] = strlen($data);
            $headers['Content-Type'] = 'multipart/form-data';
        }

        return $this->request($url,'PUT',$headers,$options);
    }

    /**
     * "PATCH" request.
     *
     * @param string $endpoint
     * @param array  $data
     * @param array  $headers
     *
     * @return object|string|null
     *
     * @throws InvalidArgumentException
     */
    public function patch(string $endpoint, array $data = [], array $headers = [])
    {
        if(empty($endpoint))
        {
            throw new \InvalidArgumentException('Endpoint URL required.');
        }

        $options = [
            CURLOPT_POSTFIELDS => http_build_query($data)
        ];

        $headers['Content-Length'] = strlen($data);

        return $this->request($url,'PATCH',$headers,$options);
    }

    /**
     * "DELETE" request.
     *
     * @param string $endpoint
     * @param array  $headers
     *
     * @return object|string|null
     *
     * @throws InvalidArgumentException
     */
    public function delete(string $endpoint, array $headers = [])
    {
        if(empty($endpoint))
        {
            throw new \InvalidArgumentException('Endpoint URL required.');
        }

        return $this->request($url,'DELETE',$headers,$options);
    }

    /**
     * HTTP (cURL) request.
     *
     * @param string $url
     * @param string $method   "GET" or "POST"
     * @param array  $headers
     * @param array  $options  Additional cURL options
     *
     * @return object|string|null
     *
     * @throws RuntimeException
     */
    private function request(string $url, string $method = 'GET', array $headers = [], array $options = [])
    {
        $curl = curl_init($url);
        if($curl === false)
        {
            throw new \RuntimeException(curl_error($curl));
        }

        //$options = array_merge($this->curl_options,$options);
        foreach($this->curl_options as $option => $value)
        {
            if(!array_key_exists($option,$options))
            {
                $options[$option] = $value;
            }
        }

        $options[CURLOPT_HTTPHEADER] = $this->processHeaders($headers);

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

        $response = curl_exec($curl);
        if($response === false)
        {
            throw new \RuntimeException(curl_error($curl));
        }

        curl_close($curl);

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
     * @param array $headers
     *
     * @return array
     */
    private function processHeaders(array $headers = [])
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
     * @param array $data
     *
     * @return array|string
     */
    protected function processData(array $data = [])
    {
        if($data)
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
}
