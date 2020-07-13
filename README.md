# RESTy
Simple lightweight dependency-free REST API client.

Supports JSON and XML.

# Usage

### Create object instance
Parameters:
* *string* **$url** - base REST API url.
* *int* **$mode** - *(optional)* response-type:
    * `REST_Client::MODE_JSON` *(default value)*
    * `REST_Client::MODE_XML`
```php
use Xeloses\RESTy\REST_Client;

$rest_client = new REST_Client('http://api.myhost.com/v2/');
```
By default client expect JSON response.

To work with XML response use `MODE_XML` constant:
```php
$rest_client = new REST_Client('http://api.myhost.com/v2/',REST_Client::MODE_XML);
```

### Headers
Add custom headers to REST API request:
```php
$rest_client->addHeader('User-Agent','My App, v1.0')->addHeader('X-USER-DATA','Some user data...');
```
or
```php
$rest_client->addHeaders([
    'User-Agent' => 'My App, v1.0',
    'X-USER-DATA' => 'Some user data...'
]);
```
This headers will be added to all requests.

Also additional headers can be added to single request in request sending method.

Methods `addHeader` and `addHeaders` are chainable.

### Request
Send request:
```php
$data = $rest_client->get('/some-entity/',[
    'key' => $my_api_key,
    'id' => $id
]);

var_dump($data);
```

All request methods:
```php
$rest_client->get(string $endpoint, ?array $data, ?array $headers)
$rest_client->post(string $endpoint, ?array $data, ?array $headers)
$rest_client->head(string $endpoint, ?array $headers)
$rest_client->put(string $endpoint, ?array $data, ?array $headers)
$rest_client->patch(string $endpoint, ?array $data, ?array $headers)
$rest_client->delete(string $endpoint, ?array $headers)
$rest_client->customRequest(string $endpoint, string $method, ?mixed $data, ?array $headers)
```
Parameters:
* *string* **$endpoint** - REST API endpoint (without base URL).
* *array* **$data** - *(optional)* data to send to REST API service (for "GET" request this data will be added as parameters to URL).
* *array* **$headers** - *(optional)* additional headers only for this request.

Return value:
* ***object*** - object parsed from response body.
* ***string*** - response body "as is" if response body is not empty and can't be parsed to object.
* ***null*** - if response body is empty.
