<?php
/**
 * EasyRest - a PHP framework to create RESTful services with ease.
 *
 * @author      Marcel Härle <marcel.haerle@tarienna.eu>
 * @copyright   2015 tarienna GmbH
 * @link        https://github.com/tarienna/easy-rest
 * @license     https://github.com/tarienna/easy-rest/blob/master/LICENSE
 * @package     tarienna\easyrest
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace tarienna\easyrest;

/**
 * Class Request
 * @package tarienna\easyrest
 */
class Request
{
    /**
     * @var array
     */
    private $params;

    /**
     * @var mixed
     */
    private $body;

    /**
     * @var array
     */
    private $query;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var mixed
     */
    private $principal;

    /**
     * @param array $params
     * @param mixed $body
     * @param array $query
     * @param array $headers
     * @param mixed $principal
     */
    public function __construct($params = array(), $body = null, $query = array(), $headers = array(), $principal = null)
    {
        $this->params = $params;
        $this->body = $body;
        $this->query = $query;
        $this->headers = $headers;
        $this->principal = $principal;
    }

    /**
     * Set the params.
     * @param array $params
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * Set the request body.
     * @param mixed $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * Set the query params.
     * @param array $query
     */
    public function setQuery(array $query)
    {
        $this->query = $query;
    }

    /**
     * Set the HTTP headers.
     * @param array $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * Get the request param for the given name.
     * @param $name
     * @return string|null
     */
    public function param($name)
    {
        if (array_key_exists($name, $this->params)) {
            return $this->params[$name];
        } else {
            return null;
        }
    }

    /**
     * Get the request body.
     * @return mixed|null
     */
    public function body()
    {
        return $this->body;
    }

    /**
     * Get the query param for the given name.
     * @param $name
     * @return string|null
     */
    public function query($name)
    {
        if (array_key_exists($name, $this->query)) {
            return $this->query[$name];
        } else {
            return null;
        }
    }

    /**
     * @return array
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * Get the principal.
     * @return mixed
     */
    public function getPrincipal()
    {
        return $this->principal;
    }

    /**
     * Set the principle.
     * @param mixed $principal
     */
    public function setPrincipal($principal)
    {
        $this->principal = $principal;
    }
}
