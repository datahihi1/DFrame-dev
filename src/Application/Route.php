<?php

namespace DFrame\Application;

use Attribute;

/**
 * Route extends Router to define route attributes for controller methods.
 */
#[Attribute]
class Route extends Router
{
    /**
     * Constructor to initialize route properties (using for attributes)
     *
     * @param string $path The URL path for the route (e.g., '/users', '/api/data')
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param bool $isApi Indicates if the route is an API route
     * @param string|null $name Optional name for the route
     * @param array|null $middleware Optional middleware for the route
     */
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public bool $isApi = true,
        public ?string $name = null,
        public ?array $middleware = null,
    ) {
    }
}
