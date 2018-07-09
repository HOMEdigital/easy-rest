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

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\FilesystemCache;
use Slim\Middleware;
use Slim\Middleware\ContentTypes;
use Slim\Slim;

/**
 * Class EasyRest
 * @package tarienna\easyrest
 */
class EasyRest
{

    /**
     * @var Slim
     */
    private $app;

    /**
     * @var array
     */
    private $settings;

    /**
     * @var string
     */
    private $filterParam;

    /**
     * @var AnnotationReader
     */
    private $reader;

    /**
     * @var SecurityProvider
     */
    private $securityProvider;

    /**
     * @param array $userSettings
     * @param SecurityProvider|null $securityProvider
     */
    public function __construct(array $userSettings = array(), SecurityProvider $securityProvider = null)
    {
        $this->securityProvider = $securityProvider;

        $this->settings = array_merge(Slim::getDefaultSettings(), $userSettings);
        if (array_key_exists('app', $this->settings) && $this->settings['app'] != null
            && $this->settings['app'] instanceof Slim)
        {
                $this->app = $this->settings['app'];
        } else {
            $this->app = new Slim($this->settings);
        }
        $this->setContentType('application/json');
        $this->addMiddleware(new ContentTypes());
        $this->filterParam = null;
        AnnotationRegistry::registerFile(__DIR__ . '/GET.php');
        AnnotationRegistry::registerFile(__DIR__ . '/PUT.php');
        AnnotationRegistry::registerFile(__DIR__ . '/POST.php');
        AnnotationRegistry::registerFile(__DIR__ . '/DELETE.php');
        AnnotationRegistry::registerFile(__DIR__ . '/Secured.php');
        if (array_key_exists('cache', $this->settings)) {
            $this->reader = new CachedReader(
                new AnnotationReader(),
                new FilesystemCache($this->settings['cache']),
                $debug = $this->settings['debug']
            );
        } else {
            $this->reader = new AnnotationReader();
        }
    }

    /**
     * Set the response content type.
     * @param string $contentType
     */
    public function setContentType($contentType)
    {
        $this->app->response->headers->set('Content-Type', $contentType);
    }

    /**
     * Add a middleware to the application.
     * @param Middleware $middleware
     */
    public function addMiddleware(Middleware $middleware)
    {
        $this->app->add($middleware);
    }

    /**
     * Get the filter param name.
     * @return null|string
     */
    public function getFilterParam()
    {
        return $this->filterParam;
    }

    /**
     * Set the filter param name.
     * @param string $filterParam
     */
    public function setFilterParam($filterParam)
    {
        $this->filterParam = $filterParam;
    }

    /**
     * Get the security provider.
     * @return null|SecurityProvider
     */
    public function getSecurityProvider()
    {
        return $this->securityProvider;
    }

    /**
     * Get the wrapped slim application.
     * @return Slim
     */
    public function getSlim()
    {
        return $this->app;
    }

    /**
     * Set the redirect url for the given route.
     * @param string $route
     * @param string $destination
     */
    public function setRedirect($route, $destination)
    {
        $this->app->get($route, function() use ($destination) {
            $this->app->redirect($destination);
        });
    }

    /**
     * Register the given instance as a router class.
     * @param object $routerClass
     * @throws \Exception
     */
    public function registerRouter($routerClass)
    {
        $reflClass = new \ReflectionClass($routerClass);
        $reflMethods = $reflClass->getMethods();
        foreach ($reflMethods as $reflMethod) {
            $classAnnotations = $this->reader->getMethodAnnotations($reflMethod);
            $method = null;
            $url = null;
            $secured = null;
            foreach ($classAnnotations AS $annot) {
                if ($annot instanceof GET
                    || $annot instanceof POST
                    || $annot instanceof PUT
                    || $annot instanceof DELETE) {
                    $method = $annot->method();
                    $url = $annot->value;
                } elseif ($annot instanceof Secured) {
                    $secured = $annot;
                }
            }
            if ($method !== null && $url !== null) {
                $callback = $reflMethod->getName();
                $this->setRoute($method, $url, $routerClass, $callback, $secured);
            }
        }
    }

    /**
     * @param string $method
     * @param string $route
     * @param object $routerClass
     * @param string $callback
     * @param Secured $secured
     * @throws \Exception
     */
    private function setRoute($method, $route, $routerClass, $callback, $secured)
    {
        $method = strtoupper($method);
        $easyRoute = new Route($route, $routerClass, $callback, $secured, $this);
        if ($method === 'GET') {
            $this->app->get($route, array($easyRoute, 'call'));
        } elseif ($method === 'PUT') {
            $this->app->put($route, array($easyRoute, 'call'));
        } elseif ($method === 'POST') {
            $this->app->post($route, array($easyRoute, 'call'));
        } elseif ($method === 'DELETE') {
            $this->app->delete($route, array($easyRoute, 'call'));
        } else {
            throw new \Exception('Unsupported HTTP method ' . $method);
        }
    }

    /**
     * Start the application.
     */
    public function run()
    {
        $this->app->run();
    }
}
