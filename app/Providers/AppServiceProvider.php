<?php namespace App\Providers;

use Illuminate\Routing\Router;
use Illuminate\Routing\Stack\Builder as Stack;
use Illuminate\Foundation\Support\Providers\AppServiceProvider as ServiceProvider;

class AppServiceProvider extends ServiceProvider {

	/**
	 * All of the application's route middleware keys.
	 *
	 * @var array
	 */
	protected $middleware = [
		'auth' => 'App\Http\Middleware\AuthMiddleware',
		'auth.basic' => 'App\Http\Middleware\BasicAuthMiddleware',
		'csrf' => 'App\Http\Middleware\CsrfMiddleware',
		'guest' => 'App\Http\Middleware\GusetMiddleware',
	];

	/**
	 * The application's middleware stack.
	 *
	 * @var array
	 */
	protected $stack = [
		'App\Http\Middleware\MaintenanceMiddleware',
		'Illuminate\Cookie\Guard',
		'Illuminate\Cookie\Queue',
		'Illuminate\Session\Middleware\Reader',
		'Illuminate\Session\Middleware\Writer',
	];

}
