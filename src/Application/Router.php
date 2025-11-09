<?php

namespace DFrame\Application;

use Attribute;
use Closure;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;
use ReflectionNamedType;
use DFrame\Application\Middleware;

/**
 * Router – tiny, attribute-friendly, DI-aware HTTP router.
 */
#[Attribute]
class Router
{
    /** @var array<string,array<string,array{handler:mixed,middleware:list<callable|string>}> */
    private array $routes = [];
    /** @var array<string,array<string,array{handler:mixed,middleware:list<callable|string>}> */
    private array $apiRoutes = [];

    /** @var list<callable> */
    private array $globalMiddleware = [];
    /** @var list<callable> */
    private array $globalApiMiddleware = [];

    private mixed $request;
    private array $container = [];

    /** ---------- STATIC REGISTRATION ---------- */
    private static array $staticRoutes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
        'HEAD' => [],
        'OPTIONS' => [],
    ];
    private static array $staticApiRoutes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
        'HEAD' => [],
        'OPTIONS' => [],
    ];

    /** @var array<string,array{method:string,path:string,api:bool}> */
    private static array $routeNames = [];

    private static ?array $lastRegisteredRoute = null;

    private static ?array $defaultHandler = null;

    /** Group stack */
    private static array $groupStack = [];
    private static array $groupContext = [
        'prefix'      => '',
        'middleware'  => [],
        'namePrefix'  => '',
    ];

    /* --------------------------------------------------------------------- */
    public function __construct($request = null, array $container = [])
    {
        $this->request   = $request;
        $this->container = $container;
    }

    /* --------------------------------------------------------------------- */
    public function addMiddleware(callable $mw): void
    {
        $this->globalMiddleware[] = $mw;
    }
    public function addApiMiddleware(callable $mw): void
    {
        $this->globalApiMiddleware[] = $mw;
    }

    /* -------------------------- STATIC ROUTE REGISTRATION -------------------------- */
    private static function registerStaticRoute(string $method, string $path, mixed $handler, array $middleware = []): void
    {
        $fullPath = self::$groupContext['prefix']
            ? self::buildGroupPath($path)
            : $path;

        $fullMw = array_merge(self::$groupContext['middleware'], $middleware);

        $store = &self::$staticRoutes[$method];
        if (isset($store[$fullPath])) {
            throw new Exception("Duplicate route: $method $fullPath");
        }
        $store[$fullPath] = ['handler' => $handler, 'middleware' => $fullMw];
        self::$lastRegisteredRoute = ['method' => $method, 'path' => $fullPath, 'api' => false];
    }

    private static function registerStaticApiRoute(string $method, string $path, mixed $handler, array $middleware = []): void
    {
        $path = self::normalizeApiPath($path);
        $store = &self::$staticApiRoutes[$method];
        if (isset($store[$path])) {
            throw new Exception("Duplicate API route: $method $path");
        }
        $store[$path] = ['handler' => $handler, 'middleware' => $middleware];
        self::$lastRegisteredRoute = ['method' => $method, 'path' => $path, 'api' => true];
    }

    /** Public façade – one line per HTTP verb */
    public static function get(string $p, $h, array $m = []): self
    {
        self::registerStaticRoute('GET', $p, $h, $m);
        return new self();
    }
    public static function post(string $p, $h, array $m = []): self
    {
        self::registerStaticRoute('POST', $p, $h, $m);
        return new self();
    }
    public static function put(string $p, $h, array $m = []): self
    {
        self::registerStaticRoute('PUT', $p, $h, $m);
        return new self();
    }
    public static function delete(string $p, $h, array $m = []): self
    {
        self::registerStaticRoute('DELETE', $p, $h, $m);
        return new self();
    }
    public static function patch(string $p, $h, array $m = []): self
    {
        self::registerStaticRoute('PATCH', $p, $h, $m);
        return new self();
    }
    public static function head(string $p, $h, array $m = []): self
    {
        self::registerStaticRoute('HEAD', $p, $h, $m);
        return new self();
    }
    public static function options(string $p, $h, array $m = []): self
    {
        self::registerStaticRoute('OPTIONS', $p, $h, $m);
        return new self();
    }

    public static function apiGet(string $p, $h, array $m = []): self
    {
        self::registerStaticApiRoute('GET', $p, $h, $m);
        return new self();
    }
    public static function apiPost(string $p, $h, array $m = []): self
    {
        self::registerStaticApiRoute('POST', $p, $h, $m);
        return new self();
    }
    public static function apiPut(string $p, $h, array $m = []): self
    {
        self::registerStaticApiRoute('PUT', $p, $h, $m);
        return new self();
    }
    public static function apiDelete(string $p, $h, array $m = []): self
    {
        self::registerStaticApiRoute('DELETE', $p, $h, $m);
        return new self();
    }
    public static function apiPatch(string $p, $h, array $m = []): self
    {
        self::registerStaticApiRoute('PATCH', $p, $h, $m);
        return new self();
    }
    public static function apiHead(string $p, $h, array $m = []): self
    {
        self::registerStaticApiRoute('HEAD', $p, $h, $m);
        return new self();
    }
    public static function apiOptions(string $p, $h, array $m = []): self
    {
        self::registerStaticApiRoute('OPTIONS', $p, $h, $m);
        return new self();
    }

    public static function all(string $p, $h, array $m = []): self
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'] as $verb) {
            self::registerStaticRoute($verb, $p, $h, $m);
        }
        return new self();
    }

    /* -------------------------- GROUPING -------------------------- */
    public static function group(string $prefix): self
    {
        self::$groupStack[] = self::$groupContext;

        $prefix = trim($prefix, '/');
        self::$groupContext['prefix'] = self::$groupContext['prefix']
            ? rtrim(self::$groupContext['prefix'], '/') . '/' . $prefix
            : '/' . $prefix;

        self::$groupContext['middleware'] = [];
        self::$groupContext['namePrefix'] = '';

        return new self();
    }

    public static function middleware(string|array $mw): self
    {
        self::$groupContext['middleware'] = is_array($mw) ? $mw : [$mw];
        return new self();
    }

    public static function namePrefix(string $prefix): self
    {
        self::$groupContext['namePrefix'] = $prefix;
        return new self();
    }

    public static function name(string $name): self
    {
        if (!self::$lastRegisteredRoute) {
            // name applies to the whole group (rare)
            return new self();
        }

        $full = self::$groupContext['namePrefix'] . $name;
        $info = self::$lastRegisteredRoute;
        self::$routeNames[$full] = [
            'method' => $info['method'],
            'path'   => $info['path'],
            'api'    => $info['api'] ?? false,
        ];
        return new self();
    }

    public static function action(callable $cb): self
    {
        $cb();
        self::$groupContext = array_pop(self::$groupStack) ?? [
            'prefix' => '',
            'middleware' => [],
            'namePrefix' => ''
        ];
        return new self();
    }

    public static function default(callable $handler, ?int $code = 404): self
    {
        self::$defaultHandler = ['handler' => $handler, 'code' => $code];
        return new self();
    }

    /* -------------------------- ATTRIBUTE SCANNER -------------------------- */
    public static function scanControllerAttributes(array $controllers): void
    {
        foreach ($controllers as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $ref = new ReflectionClass($class);
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                foreach ($m->getAttributes() as $attr) {
                    $attrClass = $attr->getName();
                    if ($attrClass !== self::class && $attrClass !== \DFrame\Application\Route::class) {
                        continue;
                    }
                    $args = $attr->getArguments();

                    $http   = strtoupper($args['method'] ?? $args['httpMethod'] ?? 'GET');
                    $path   = $args['router'] ?? $args['path'] ?? $args[0] ?? null;
                    $name   = $args['name'] ?? null;
                    $isApi  = $args['isApi'] ?? $args['api'] ?? false;
                    $mw     = $args['middleware'] ?? [];

                    if (!$path) {
                        continue;
                    }

                    $handler = [$class, $m->getName()];
                    $route   = $isApi
                        ? match ($http) {
                            'GET'     => self::apiGet($path, $handler, $mw),
                            'POST'    => self::apiPost($path, $handler, $mw),
                            'PUT'     => self::apiPut($path, $handler, $mw),
                            'DELETE'  => self::apiDelete($path, $handler, $mw),
                            'PATCH'   => self::apiPatch($path, $handler, $mw),
                            'HEAD'    => self::apiHead($path, $handler, $mw),
                            'OPTIONS' => self::apiOptions($path, $handler, $mw),
                            default   => self::apiGet($path, $handler, $mw),
                        }
                        : match ($http) {
                            'GET'     => self::get($path, $handler, $mw),
                            'POST'    => self::post($path, $handler, $mw),
                            'PUT'     => self::put($path, $handler, $mw),
                            'DELETE'  => self::delete($path, $handler, $mw),
                            'PATCH'   => self::patch($path, $handler, $mw),
                            'HEAD'    => self::head($path, $handler, $mw),
                            'OPTIONS' => self::options($path, $handler, $mw),
                            default   => self::get($path, $handler, $mw),
                        };

                    if ($name && method_exists($route, 'name')) {
                        $route->name($name);
                    }
                }
            }
        }
    }

    public function scanControllerAttributesInstance(array $c): void
    {
        self::scanControllerAttributes($c);
    }

    /* -------------------------- RUNTIME -------------------------- */
    public function runInstance(): void
    {
        $this->mergeStaticRoutes();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = $this->cleanUri();

        // 1. exact match (standard)
        if (isset($this->routes[$method][$uri])) {
            $this->dispatch($this->routes[$method][$uri], [], false);
            return;
        }

        // 2. pattern match (standard)
        if ($match = $this->matchPattern($this->routes[$method] ?? [], $uri, $params)) {
            $this->dispatch($match, $params, false);
            return;
        }

        // 3. API handling
        if ($this->handleApi($method, $uri)) {
            return;
        }

        // 4. 405 – collect allowed methods
        $allowed = $this->collectAllowed($uri);
        if ($allowed) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowed));
            echo "Method Not Allowed";
            return;
        }

        // 5. 400 – missing required param
        if ($bad = $this->detectMissingParam($method, $uri)) {
            http_response_code(400);
            echo "Bad Request: missing parameter for $bad";
            return;
        }

        // 6. default handler
        if (self::$defaultHandler) {
            $this->runDefaultHandler();
            return;
        }

        // 7. 404
        http_response_code(404);
        echo "Not Found";
    }

    public static function run(): void
    {
        (new self())->runInstance();
    }

    /* --------------------------------------------------------------------- */
    private function mergeStaticRoutes(): void
    {
        foreach (self::$staticRoutes as $m => $r) {
            $this->routes[$m] = array_merge($this->routes[$m] ?? [], $r);
        }
        foreach (self::$staticApiRoutes as $m => $r) {
            $this->apiRoutes[$m] = array_merge($this->apiRoutes[$m] ?? [], $r);
        }
    }

    private function cleanUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== '/' && str_starts_with($uri, $script)) {
            $uri = substr($uri, strlen($script));
        }
        return '/' . ltrim(rtrim($uri, '/'), '/');
    }

    private function matchPattern(array $routes, string $uri, ?array &$params = null): ?array
    {
        foreach ($routes as $pattern => $data) {
            $regex = '#^' . preg_replace('#\{[^/]+\}#', '([^/]+)', rtrim($pattern, '/')) . '$#';
            if (preg_match($regex, $uri, $m)) {
                array_shift($m);
                $params = $m;
                return $data;
            }
        }
        return null;
    }

    private function collectAllowed(string $uri): array
    {
        $allowed = [];
        foreach (array_merge($this->routes, $this->apiRoutes) as $meth => $set) {
            if (isset($set[$uri]) || $this->matchPattern($set, $uri)) {
                $allowed[] = $meth;
            }
        }
        return array_unique($allowed);
    }

    private function detectMissingParam(string $method, string $uri): ?string
    {
        foreach ([$this->routes, $this->apiRoutes] as $collection) {
            if (isset($collection[$method])) {
                foreach ($collection[$method] as $pattern => $_) {
                    $clean = preg_replace('#\{[^/]+\}#', '', rtrim($pattern, '/'));
                    if ($clean !== '' && rtrim($uri, '/') === rtrim($clean, '/')) {
                        return $pattern;
                    }
                }
            }
        }
        return null;
    }

    private function handleApi(string $method, string $uri): bool
    {
        if (isset($this->apiRoutes[$method][$uri])) {
            $this->dispatch($this->apiRoutes[$method][$uri], [], true);
            return true;
        }
        if ($match = $this->matchPattern($this->apiRoutes[$method] ?? [], $uri, $params)) {
            $this->dispatch($match, $params, true);
            return true;
        }
        return false;
    }

    private function dispatch(array $route, array $params, bool $isApi): void
    {
        $handler   = $this->normalizeHandler($route['handler']);
        $mwGlobal  = $isApi ? $this->globalApiMiddleware : $this->globalMiddleware;
        $mw        = array_merge($mwGlobal, $route['middleware']);

        $context = ['params' => $params, 'request' => $this->request];

        foreach ($mw as $m) {
            $res = is_string($m)
                ? Middleware::run($m, $context)
                : $m($context);

            if ($res === false) {
                return;
            }
            if ($res !== null) {
                if ($isApi) {
                    $code = is_array($res) && isset($res['code']) ? $res['code'] : 400;
                    header('Content-Type: application/json');
                    http_response_code($code);
                    echo json_encode($res, JSON_UNESCAPED_UNICODE);
                } else {
                    echo $res;
                }
                return;
            }
        }

        $result = $this->invokeHandler($handler, $params);
        if ($isApi) {
            header('Content-Type: application/json');
            $code = is_array($result) && isset($result['code']) ? $result['code'] : 200;
            http_response_code($code);
            if (isset($result['code'])) {
                unset($result['code']);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } else {
            echo $result;
        }
    }

    private function invokeHandler(mixed $handler, array $params): mixed
    {
        $args = $params;
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = $this->resolveClass($class);
            $ref = new ReflectionMethod($instance, $method);
        } else {
            $ref = new ReflectionFunction(Closure::fromCallable($handler));
        }

        // always inject $request as the **last** argument if the callable expects it
        if ($ref->getNumberOfParameters() > count($args)) {
            $args[] = $this->request;
        }

        return $ref instanceof ReflectionMethod
            ? $ref->invokeArgs($instance, $args)
            : $ref->invokeArgs($args);
    }

    private function runDefaultHandler(): void
    {
        $def = self::$defaultHandler;
        $handler = $def['handler'];
        $code    = $def['code'] ?? 404;
        http_response_code($code);

        $result = $this->invokeHandler($handler, []);
        if ($result !== null) {
            echo $result;
        }
    }

    /* --------------------------------------------------------------------- */
    private function resolveClass(string $class): object
    {
        if (isset($this->container[$class])) {
            $entry = $this->container[$class];
            return is_callable($entry) ? $entry($this) : $entry;
        }

        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if (!$ctor) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $p) {
            $type = $p->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->resolveClass($type->getName());
                continue;
            }
            if ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
                continue;
            }
            throw new Exception("Cannot resolve {$p->getName()} for $class");
        }
        return $ref->newInstanceArgs($args);
    }

    private function normalizeHandler(mixed $handler): mixed
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$c, $m] = explode('@', $handler, 2);
            return [$c, $m];
        }
        return $handler;
    }

    private static function buildGroupPath(string $path): string
    {
        return '/' . trim(rtrim(self::$groupContext['prefix'], '/') . '/' . ltrim($path, '/'), '/');
    }

    private static function normalizeApiPath(string $path): string
    {
        $p = trim($path, '/');
        return str_starts_with($p, 'api/') ? '/' . $p : '/api/' . $p;
    }

    /* -------------------------- URL GENERATOR -------------------------- */
    public static function route(string $name, array $params = [], ?string $base = null): ?string
    {
        if (!isset(self::$routeNames[$name])) {
            return null;
        }

        $info = self::$routeNames[$name];
        $path = $info['path'];

        if ($params) {
            foreach ($params as $v) {
                $path = preg_replace('#\{[^}]+\}#', $v, $path, 1);
            }
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   ??= $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');

        $url = rtrim($base, '/') . '/' . ltrim($path, '/');
        return preg_replace('#(?<!:)//+#', '/', $url);
    }
}
