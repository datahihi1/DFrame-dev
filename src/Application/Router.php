<?php

namespace DFrame\Application;

use Attribute;
use Closure;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionNamedType;
use DFrame\Application\Middleware;

/**
 * Router Attribute and Static Router Class
 * 
 * A simple HTTP router supporting static route registration,
 * attribute-based route definitions, middleware, and dependency injection.
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
        'prefix' => '',
        'middleware' => [],
        'namePrefix' => '',
    ];

    /* --------------------------------------------------------------------- */
    public function __construct($request = null, array $container = [])
    {
        $this->request = $request;
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
    private static function parseRouteSpec(string $spec): array
    {
        // Hỗ trợ: 'GET|POST /path' hoặc 'GET /path'
        if (!preg_match('#^([A-Z|]+)\s+(.+)$#', trim($spec), $m)) {
            throw new Exception("Invalid route spec: $spec. Use 'METHOD|/path' or 'METHOD1|METHOD2 /path'");
        }

        $methods = array_filter(array_map('trim', explode('|', $m[1])), fn($m) => $m !== '');
        $path = $m[2];

        foreach ($methods as $method) {
            if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])) {
                throw new Exception("Invalid HTTP method: $method");
            }
        }

        return [
            'methods' => $methods,
            'path' => $path,
        ];
    }

    private static function registerStaticRoute(string $spec, mixed $handler, array $middleware = []): void
    {
        $parsed = self::parseRouteSpec($spec);
        $path = $parsed['path'];

        $fullPath = self::$groupContext['prefix']
            ? self::buildGroupPath($path)
            : $path;

        $fullMw = array_merge(self::$groupContext['middleware'], $middleware);

        foreach ($parsed['methods'] as $method) {
            $store = &self::$staticRoutes[$method];
            if (isset($store[$fullPath])) {
                throw new Exception("Duplicate route: $method $fullPath");
            }
            $store[$fullPath] = ['handler' => $handler, 'middleware' => $fullMw];
        }

        // Lưu lại route cuối cùng để hỗ trợ ->name()
        self::$lastRegisteredRoute = [
            'method' => $parsed['methods'][0], // chỉ lưu method đầu tiên cho tên
            'path' => $fullPath,
            'api' => false
        ];
    }

    private static function registerStaticApiRoute(string $spec, mixed $handler, array $middleware = []): void
    {
        $parsed = self::parseRouteSpec($spec);
        $path = self::normalizeApiPath($parsed['path']);

        foreach ($parsed['methods'] as $method) {
            $store = &self::$staticApiRoutes[$method];
            if (isset($store[$path])) {
                throw new Exception("Duplicate API route: $method $path");
            }
            $store[$path] = ['handler' => $handler, 'middleware' => $middleware];
        }

        self::$lastRegisteredRoute = [
            'method' => $parsed['methods'][0],
            'path' => $path,
            'api' => true
        ];
    }

    /**
     * Sign a route specification to a handler with optional middleware.
     * @param string $spec (`GET`, `POST`, etc.) Route specification in the format 'METHOD /path' or 'METHOD1|METHOD2 /path'.
     * @param mixed $handler The handler for the route (callable, or 'Class@method' string).
     * @param array $middleware Optional middleware for the route.
     * @return static
     */
    public static function sign(string $spec, $handler, array $middleware = []): self
    {
        self::registerStaticRoute($spec, $handler, $middleware);
        return new self();
    }

    /**
     * Sign an API route specification to a handler with optional middleware.
     * @param string $spec (`GET`, `POST`, etc.) Route specification in the format 'METHOD /path' or 'METHOD1|METHOD2 /path'.
     * @param mixed $handler The handler for the route (callable, or 'Class@method' string).
     * @param array $middleware Optional middleware for the route.
     * @return static
     */
    public static function signApi(string $spec, $handler, array $middleware = []): self
    {
        self::registerStaticApiRoute($spec, $handler, $middleware);
        return new self();
    }

    /**
     * Sign a route for all HTTP methods to a handler with optional middleware.
     * @param string $path The route path.
     * @param mixed $handler The handler for the route (callable, or 'Class@method' string).
     * @param array $middleware Optional middleware for the route.
     * @return static
     */
    public static function all(string $path, $handler, array $middleware = []): self
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
        $spec = implode('|', $methods) . " $path";
        self::sign($spec, $handler, $middleware);
        return new self();
    }

    /* -------------------------- GROUPING -------------------------- */
    /**
     * Start a route group with a common prefix.
     * @param string $prefix The route prefix for the group.
     * @return static
     */
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

    /**
     * Set middleware for routes in the current group.
     * @param string|array $mw The middleware (string or array of strings).
     * @return static
     */
    public static function middleware(string|array $mw): self
    {
        self::$groupContext['middleware'] = is_array($mw) ? $mw : [$mw];
        return new self();
    }

    /**
     * Set name prefix for routes in the current group.
     * @param string $prefix The name prefix.
     * @return static
     */
    public static function namePrefix(string $prefix): self
    {
        self::$groupContext['namePrefix'] = $prefix;
        return new self();
    }

    /**
     * Name the last registered route.
     * @param string $name The name for the route.
     * @return static
     */
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
            'path' => $info['path'],
            'api' => $info['api'] ?? false,
        ];
        return new self();
    }

    /**
     * Define a group action and end the group context.
     * @param callable $cb The group action callback.
     * @return static
     */
    public static function action(callable $cb): self
    {
        $ref = new ReflectionFunction(Closure::fromCallable($cb));
        if ($ref->getNumberOfParameters() > 0) {
            $cb(new self());
        } else {
            $cb();
        }
        self::$groupContext = array_pop(self::$groupStack) ?? [
            'prefix' => '',
            'middleware' => [],
            'namePrefix' => ''
        ];
        return new self();
    }

    /**
     * Define a default handler for unmatched routes.
     * @param callable $handler The default handler (callable).
     * @param int|null $code Optional HTTP status code to return (default: 404).
     * @return static
     */
    public static function default(callable $handler, ?int $code = 404): self
    {
        self::$defaultHandler = ['handler' => $handler, 'code' => $code];
        return new self();
    }

    /* -------------------------- ATTRIBUTE SCANNER -------------------------- */
    /**
     * Scan controller classes for route attributes and register them.
     * @param array $controllers List of controller class names to scan.
     * @return void
     */
    public static function scanControllerAttributes(array $controllers): void
    {
        foreach ($controllers as $class) {
            if (!class_exists($class))
                continue;
            $ref = new ReflectionClass($class);
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                foreach ($m->getAttributes() as $attr) {
                    $attrClass = $attr->getName();
                    if ($attrClass !== self::class && $attrClass !== \DFrame\Attribute\Route::class) {
                        continue;
                    }
                    $args = $attr->getArguments();

                    $http = strtoupper($args['method'] ?? $args['httpMethod'] ?? 'GET');
                    $path = $args['router'] ?? $args['path'] ?? $args[0] ?? null;
                    $name = $args['name'] ?? null;
                    $isApi = $args['isApi'] ?? $args['api'] ?? false;
                    $mw = $args['middleware'] ?? [];

                    if (!$path)
                        continue;

                    // Hỗ trợ multi-method trong attribute: method: 'GET|POST'
                    $spec = "$http $path";
                    $handler = [$class, $m->getName()];

                    $route = $isApi
                        ? self::signApi($spec, $handler, $mw)
                        : self::sign($spec, $handler, $mw);

                    if ($name) {
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

        // Detect HTTP method and support method override via header or _method form field
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        // support X-HTTP-Method-Override or HTTP_X_HTTP_METHOD_OVERRIDE
        $overrideHeader = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['HTTP_X_HTTP_METHOD'] ?? null;
        $overrideField = $_POST['_method'] ?? $_REQUEST['_method'] ?? null;
        if ($method === 'POST' && ($overrideHeader || $overrideField)) {
            $method = strtoupper($overrideHeader ?: $overrideField);
        }
        $uri = $this->cleanUri();

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
        $handler = $this->normalizeHandler($route['handler']);
        $mwGlobal = $isApi ? $this->globalApiMiddleware : $this->globalMiddleware;
        $mw = array_merge($mwGlobal, $route['middleware']);

        $context = ['params' => $params, 'request' => $this->request];

        foreach ($mw as $m) {
            $res = is_string($m)
                ? Middleware::run($m, $context)
                : $m($context);

            if ($res === false)
                return;
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
            if (isset($result['code']))
                unset($result['code']);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } else {
            echo $result;
        }
    }

    private function invokeHandler(mixed $handler, array $params): mixed
    {
        $ref = null;
        $instance = null;

        // Chuẩn hóa handler thành dạng callable: nếu là chuỗi "Class@method" thì chuyển thành [Class, method], ngược lại giữ nguyên
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = $this->resolveClass($class);
            $ref = new ReflectionMethod($instance, $method);
        } else {
            $ref = new ReflectionFunction(Closure::fromCallable($handler));
        }

        // Xây dựng danh sách tham số truyền vào hàm/method
        $args = [];
        $routeIdx = 0;
        foreach ($ref->getParameters() as $p) {
            // Sử dụng tham số định kiểu class nếu có
            $pType = $p->getType();
            $isClass = $pType instanceof ReflectionNamedType && !$pType->isBuiltin();

            if ($isClass) {
                // Thực hiện giải quyết phụ thuộc từ container/tự động giải quyết
                $args[] = $this->resolveClass($pType->getName());
                continue;
            }

            // sử dụng tham số vị trí từ route nếu có
            if (array_key_exists($routeIdx, $params)) {
                $args[] = $params[$routeIdx++];
                continue;
            }

            // truyền đối tượng Request nếu tham số tên là 'request'
            if ($p->getName() === 'request') {
                $args[] = $this->request;
                continue;
            }

            // sử dụng giá trị mặc định nếu có
            if ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
                continue;
            }

            // không có gì phù hợp, truyền null
            $args[] = null;
        }

        // Xử lý gọi hàm/method
        $result = $ref instanceof ReflectionMethod
            ? $ref->invokeArgs($instance, $args)
            : $ref->invokeArgs($args);

        // xử lý attribute Viewer nếu có được khai báo trên method (cập nhật 2025-13-11)
        if ($ref instanceof ReflectionMethod) {
            $attrs = $ref->getAttributes(\DFrame\Attribute\Viewer::class);
            if (!empty($attrs)) {
                $viewer = $attrs[0]->newInstance();
                if (method_exists($viewer, 'handle')) {
                    $result = $viewer->handle($result);
                }
            }
        }

        return $result;
    }

    private function runDefaultHandler(): void
    {
        $def = self::$defaultHandler;
        $handler = $def['handler'];
        $code = $def['code'] ?? 404;
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
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base ??= $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');

        $url = rtrim($base, '/') . '/' . ltrim($path, '/');
        return preg_replace('#(?<!:)//+#', '/', $url);
    }
}
