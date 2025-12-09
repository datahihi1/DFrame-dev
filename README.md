# DFrame PHP v8

**DFrame** is a minimalist PHP framework designed for small projects, learning, or as a foundation for personal framework development.

## Features

- **Routing**: Flexible routing system, supporting groups, middleware, named routes, RESTful and API routes.
- **View Engine**: Supports multiple view engines, default is PHP, easily extendable to Blade, Twig, etc.
- **Session & Flash**: Manage sessions, convenient flash messages for one-time notifications.
- **Database Layer**: 
  - Supports multiple database systems (MySQL, SQLite).
  - Supports multiple drivers (mysqli, sqlite3, PDO).
  - Adapter pattern for connections and queries.
  - Query Builder generates dynamic SQL, separated from the adapter.
  - Record Mapper CRUD for each table.
- **Error & Exception Handling**: Error reporting, logging, runtime, parsing, separate exceptions.
- **Helper**: Many utility functions for debugging, var_dump, helper function.
- **Environment Configuration**: Read environment variables from `.env`, supported by [TinyEnv](https://github.com/datahihi1/tiny-env.git).
- **Security**: Supports secure sessions, maintenance mode, security headers, tokens generation (easily extendable).
- **Mailing**: Simple SMTP mail sending with configuration options.

## Basic Usage

**Routing:**
```php
$router = new DFrame\Application\Router();
$router->sign('GET /', [App\Controller\HomeController::class, 'index']); // Single route
$router->group('/api')->action(function($router) { // Grouped routes
    $router->sign('GET /users', [App\Controller\Api\UserController::class, 'list']);
});
$router->sign('GET|POST /demo', [App\Controller\DemoController::class, 'index']); // Multiple methods
$router->signApi('GET /data', [App\Controller\Api\DataController::class, 'fetch']); // API route
$router->runInstance();
```

**Controller:**

```php
class HomeController extends Controller {
    public function index() {
        return $this->render('home', ['message' => 'Hello!']);
    }
}
```

**Database:**
```php
$test = new User();
$allUsers = $test->all(); // Get all users with Mapper

DB::table('users')->where('id', 1)->first(); // Query Builder
```

**View:**
```php
echo DFrame\Application\View::render('home', ['message' => 'Hello!']);
```

**Session & Flash:**
```php
DFrame\Application\Session::flash('msg', 'Success!'); // Set flash message
echo DFrame\Application\Session::getFlash('msg'); // Get and clear flash message
```

**Hash:**

```php
$hashedPassword = DFrame\Application\Hash::default('password123'); // Hash password
$isValid = DFrame\Application\Hash::verify('password123', $hashedPassword); // Verify password
```

**Mail:**
```php
$mail = new DFrame\Application\Mail();
$mail->to('recipient@example.com')
    ->subject('Test Email')
    ->body('This is a test email.')
    ->send();
```

## System Requirements
- PHP 8.0 to 8.5 (Update at 20:00 2025-12-09 UTC+7)
- Composer
- Web Server (Apache, Nginx, etc.)

## Installation

```bash
composer create-project datahihi1/dframe:dev-main my-project
cd my-project
php dli -s
```

Then open your browser and navigate to `http://localhost:8000`.