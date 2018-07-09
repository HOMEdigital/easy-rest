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
use Slim\Slim;

/**
 * Class Route
 * @package tarienna\easyrest
 */
class Route
{
    /**
     * @var Slim
     */
    private $slim;

    /**
     * @var string
     */
    private $route;

    /**
     * @var callable
     */
    private $routerClass;

    /**
     * @var string
     */
    private $callback;

    /**
     * @var Secured
     */
    private $secured;

    /**
     * @var SecurityProvider
     */
    private $securityProvider;

    /**
     * @var EasyRest
     */
    private $easyApp;

    /**
     * @var string
     */
    private $filterParam;

    /**
     * @param string $route
     * @param object $routerClass
     * @param string $callback
     * @param Secured $secured
     * @param EasyRest $easyApp
     */
    public function __construct($route, $routerClass, $callback, $secured, $easyApp)
    {
        $this->slim = $easyApp->getSlim();
        $this->route = $route;
        $this->routerClass = $routerClass;
        $this->callback = $callback;
        $this->secured = $secured;
        $this->easyApp = $easyApp;
        $this->securityProvider = $easyApp->getSecurityProvider();
        $this->filterParam = $easyApp->getFilterParam();
    }

    public function call()
    {
        $authenticated = $this->isAuthenticated();
        if ($authenticated && $this->secured !== null) {
            $token = $this->securityProvider->getTokenFromHeaders($this->slim->request->headers);
            $principal = $this->securityProvider->getPrincipal($token);
        } else {
            $principal = null;
        }

        if (!$authenticated) {
            $this->slim->response->setStatus(401);
            return;
        }
        $class = new \ReflectionClass(get_class($this->routerClass));
        $req = new Request(
            $this->getParams($this->route, func_get_args()),
            $this->slim->request->getBody(),
            $this->slim->request->params(),
            $this->slim->request->headers,
            $principal
        );
        try {
            $result = call_user_func_array(array($this->routerClass, $this->callback), array($req));
            if ($result instanceof AbstractResponse) {
                $this->slim->response->setStatus($result->getStatus());
                if ($result->getPayload() != null) {
                    if ($this->filterParam !== null && is_array($result->getPayload()) && array_key_exists($this->filterParam, $this->slim->request->params())) {
                        $filter = $this->slim->request->params()[$this->filterParam];
                        $filteredPayload = $this->filterPayload($this->parseFilter($filter), $result->getPayload());
                        $this->slim->response->write(json_encode($filteredPayload));
                    } else {
                        $this->slim->response->write(json_encode($result->getPayload()));
                    }
                } else {
                    $this->easyApp->setContentType('text/html');
                }
            } else {
                throw new \Exception('Route defined ' . $class->getName() . '->' . $this->callback
                    . ' must return an instance of AbstractResponse');
            }
        } catch (\Exception $e) {
            $this->slim->response->setStatus(500);
            $this->slim->response->write(json_encode(array("exception" => (string)$e)));
        }
    }

    private function isAuthenticated()
    {
        if ($this->secured !== null && $this->securityProvider !== null) {
            $token = $this->securityProvider->getTokenFromHeaders($this->slim->request->headers);
            if (!$this->securityProvider->validateToken($token)) {
                return false;
            }
            if ($this->secured->value !== null) {
                $rolesAllowed = explode(",", $this->secured->value);
                $found = false;
                $roles = $this->securityProvider->getRoles($token);
                foreach ($rolesAllowed as $roleAllowed) {
                    foreach ($roles as $role) {
                        if ($roleAllowed === $role) {
                            $found = true;
                        }
                    }
                }
                if (!$found) {
                    return false;
                }
            }
        }
        return true;
    }

    private function getParams($route, $funcArgs)
    {
        $tokens = explode('/', $route);
        $paramNames = array();
        foreach ($tokens as $token) {
          if (0 === strpos($token, ':')) {
            array_push($paramNames, $token);
          }
        }
        $params = array();
        for ($i = 0, $l = count($paramNames); $i < $l; $i++) {
          $params[substr($paramNames[$i], 1)] = $funcArgs[$i];
        }
        return $params;
    }

    private function filterPayload(array $filterMap, array $payload)
    {
        $filteredPayload = array();
        foreach ($payload as $obj) {
            $objAttributes = get_object_vars($obj);
            if ($this->matchesAllFilterAttributes($filterMap, $objAttributes)) {
                array_push($filteredPayload, $obj);
            }
        }
        return $filteredPayload;
    }

    private function matchesAllFilterAttributes($filterMap, $attributes)
    {
        foreach ($filterMap as $key => $value) {
            if (!$this->matchesAttribute($key, $value, $attributes)) {
                return false;
            }
        }
        return true;
    }

    private function matchesAttribute($key, $value, $attributes)
    {
        return array_key_exists($key, $attributes) && preg_match('/' . $value . '/', $attributes[$key]);
    }

    private function parseFilter($filter)
    {
        $filterMap = array();
        if (strpos($filter, '[') === 0 && strpos($filter, ']') === strlen($filter) - 1) {
            $filter = substr($filter, 1);
            $filter = substr($filter, 0, strlen($filter) - 1);
            if (strlen($filter) > 0) {
                $tokens = explode(';', $filter);
                foreach ($tokens as $token) {
                    $keyValuePair = explode(':', $token);
                    if (count($keyValuePair) === 2) {
                        $filterMap[$keyValuePair[0]] = $keyValuePair[1];
                    }
                }
            }
        }
        return $filterMap;
    }
}
