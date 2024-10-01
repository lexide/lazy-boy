# LazyBoy
A skeleton REST API application, using [Slim], [Syringe] and [Puzzle-DI]

## Summary
LazyBoy will create a skeleton [Slim] framework, using [ProForma] to generate files, so you can create REST APIs without
having to bother with boilerplate code.

It is packaged with a route loader and uses [Syringe], which allows you to define both your routes and services in 
configuration files, rather than PHP

If you have the [Symfony console] installed, it will also create a console script including and command services that
have been defined in [Syringe] DI config. You can also use [Puzzle-DI] to load service configuration from modules.

## Requirements

* PHP 8.0+
* [Slim] 4.0+
* [Syringe] 2.2+

## Installation
install using composer:

    composer require lexide/lazy-boy:~4.0.0

LazyBoy will automatically generate several files from templates, whenever `composer update` or `composer install` is run.
You are free to make modifications to the generated output; LazyBoy will not overwrite a file which already exists, so 
committing those changes to a VCS is safe and recommended. Having your VCS ignore the files will mean they are generated
when you install vendors on a freshly cloned repository, however it means that you will always get the very latest 
version of the templates.

If you want to disable automatic file generation, so you can use LazyBoy classes on their own, add the following to your 
composer file:

```json
{
  "extra": {
    "lexide/pro-forma": {
      "config": {
        "lexide/lazy-boy": {
          "preventTemplating": true
        }
      }
    }
  }
}
```

All that is left to do is create a vhost or otherwise point requests to `web/index.php`.

## Code Generation

### Application types

By default, LazyBoy will create files required for a standard REST application. It also supports adding console scripts, 
as a straight Symfony Console app or integrated for AWS Lambda. In addition, you can replace the default REST application
with one for AWS ApiGateway.

The application type is configured with ProForma config:

```json
{
  "extra": {
    "lexide/pro-forma": {
      "config": {
        "lexide/lazy-boy": {
          "rest": false,
          "apiGateway": true,
          "lambda": true
        }
      }
    }
  }
}
```

This example would create an ApiGateway application with Lambda support

### Configuration

The full list of ProForma config options is as follows:

| Option              | Data Type | Description                              | Notes                                                  |
|---------------------|-----------|------------------------------------------|--------------------------------------------------------|
| rest                | bool      | Create a REST application                | Defaults to true                                       |
| apiGateway          | bool      | Create an AWS ApiGateway application     | Mutually exclusive with "rest" which takes precedence  |
| console             | bool      | Create a Symfony console application     | Requires symfony/console to be installed               |
| lambda              | bool      | Create an AWS Lambda application         | Requires symfony/console to be installed               |
| useCors             | bool      | Disable the LazyBoy Slim CORS middleware | Only applicable to "rest" applications                 |
| consoleName         | string    | Set the name of the console application  | Only applicable to "console" and "lambda" applications |
| preventTemplating   | bool      | Disable all code generation              |                                                        |

### Templates

LazyBoy creates files from the following templates, base on the application types that are configured in `composer.json`

| Template Name   | Application Type | Output Location         |
|-----------------|------------------|-------------------------|
| bootstrap       | All              | app/bootstrap.php       |
| apiConfig       | REST, ApiGateway | app/config/api.yml      |
| consoleConfig * | Console, Lambda  | app/config/console.yml  |
| loggingConfig   | All              | app/config/logging.yml  |
| routes          | REST, ApiGateway | app/config/routes.yml   |
| serviceConfig   | All              | app/config/services.yml |
| console *       | Console          | app/console             |
| lambda *        | Lambda           | app/lambda              |
| api             | ApiGateway       | web/api.php             |
| index           | REST             | web/index.php           |

\* *This template depends on the `symfony/console` library being present in the package list*

## Routing

### Routes

If you are using the standard LazyBoy route loader, you can define your routes in YAML configuration files. Each route 
is defined as follows:

```yaml
routes:
    route-name:
        url: "/sub/directory"
        method: "post"
        action: 
          controller: "test_controller"
          method: "doSomething"
        public: true     # or
        security:
          # custom security parameters
  
```

`routes` is an associative array of routes that you want to allow access to.

In this case, a HTTP request that was `POST`ed to `/sub/directory`, would access a service in the container called
`test-controller` and call its method `doSomething`. This route can be referenced as `route-name` when using the 
router.

For each route, the `url` and `action` parameters are required, but `method` is optional and defaults to `GET`.

Route URLs are processed by Slim, so you can add parameters and assertions 
[as you normally would](https://www.slimframework.com/docs/v4/objects/routing.html#route-placeholders) for that framework 

```yaml
    routes:
        route-one:
            url: "/user/{id:[0-9+]}"
            action:
              controller: "test_controller"
              method: "doSomething"
```

The URL `/user/56` would match and the `id` parameter would be set to `56`.
The URL `/user/56/foo` or `/user/foo` would not match.

### Groups

If you have many routes with similar URLs, such as:

* /users
* /users/{id}
* /users/login
* /users/logout

you can use a group to wrap them with a common url prefix.

```yaml
groups:
    users:
        url: "/users"
        routes:
            user-list:
                # Omitting a route URL leaves the effective URL for this route as "/users" 
                # ...
            get-user:
                url: "/{id}"
                # ...
            user-login:
                url: "/login"
                method: post
                # ...
            user-logout:
                url: "/logout"
                # ...
```

### Imports

If you have a large API, it can be unwieldy to have all routes in the same file. Luckily, because LazyBoy uses syringe 
for route config, we can use imports to allow the routes file to be split up 

```yaml
imports:
  - "usersRoutes.yml"
  - "adminRoutes.yml"
  # ...
  
parameters:
  routes:
    otherRoutesAsNormal:
      # ...
```

```yaml
# usersRoutes.yml
parameters:
  routes:
    userRoute:
      # ...
```

Syringe combines imported files using `array_replace_recursive()` so the only caveat to note is that you **MUST** use
route names that are unique across all the route files. If not, the routes will get merged with unpredictable results.

### Controllers

A route must define a controller through which a matching request can be processed. These are PHP classes that include
methods that will return a `Psr\Http\Message\ResponseInterface` when called.

Controller methods can be passed the Request, the initial Response object (for when middleware needs to add to a 
response) and any named parameters that slim parsed from the route URL. These values are assigned to method arguments 
that match the following criteria:

| Argument  | Criteria                                                                                  |
|-----------|-------------------------------------------------------------------------------------------|
| Request   | Has the name `$request` or has the type `Psr\Http\Message\RequestInterface`               |
| Response  | Has the name `$response` or has the type `Psr\Http\Message\ResponseInterface`             |
| Parameter | Named for the URL parameter in question e.g. `$id` would match from the URL `/user/{id}`* | 

\* *Parameter types are not checked by LazyBoy; it is your responsibility to ensure the correct type is assigned*

LazyBoy provides a `ResponseFactory` service which can be used as a convenient method of creating common response types, 
such as error responses, JSON responses, no content responses, etc...

### Configuration

By default, the `RouteLoader` restricts HTTP methods to a set of the most commonly used methods: `GET`, `POST`, `PATCH`, 
`PUT` and `DELETE`. This can be customised by changing the Syringe DI configuration value `router.allowedMethods`:

```yaml
parameters:
    # ...
    router.allowedMethods:
        - "get"
        - "post"
        - "delete"
        - "connect" # added CONNECT method
        - "upsert"  # added custom / non-standard method 
        # PUT and PATCH methods are now disabled (not present in the list)
```

Allowed method values are case-insensitive.

## API Middleware

### CORS Middleware

The LazyBoy CORS middleware can be used to give your API the ability to accept cross domain requests. It is enabled by 
default and can be configured by changing the following parameters to your app's syringe config:

```yaml
parameters:
  api.cors.allowedMethods: [] # List of allowed methods (defaults to the same methods as the router allows)
  api.cors.allowedOrigins: [] # List of allowed origin domains
  api.cors.allowedHeaders: [] # List of allowed headers
```

For allowed Origins and Headers, an empty list will insert `"*"` as the value in the CORS response headers. This is a
fallback and not recommended for general use; if you're using CORS it should be configured only for the origins and 
headers that you need.

To disable the middleware, set "useCors" to false in ProForma config when generating code files, or remove the middleware 
from the Slim application in DI config.

### Security Middleware

LazyBoy has a security system that aims to prevent access to a non-public route unless specific conditions are met.
LazyBoy itself only provides the ability to implement security controls; it makes no assumptions about what level of 
security you want or what services or data you use to provide it.

The system uses a series of Authorisers to run checks on a request to see if it should be allowed to continue. Each 
Authoriser implements the `AuthoriserInterface` and will be passed the request and the security context for a route.
To implement an Authoriser, you should create a class implementing this interface and add the logic you require to 
validate a request. For example:

```php
class RoleAuthoriser implements AuthoriserInterface
{

    protected $userDao;

    public function __construct(UserDao $userDao)
    {
        $this->userDao = $userDao;
    }

    public function checkAuthorisation(RequestInterface $request, array $securityContext): bool
    {
        $userId = $request->getAttribute("userId");
        $user = $this->usersDao->getUser($userId);
        return $user->getRole() == $securityContext["role"];
    }

}
```

This authoriser gets a users ID from the request* loads the user record from a data store and checks the users role 
against the role that the route requires. If the two match then the check passes.

This is a convoluted example that wouldn't be used in a real system, but serves to show how authorisers can be created 
and the types of things they should do. As a general rule, a single Authoriser should check a single thing, so that they
are composable and reusable.

Authorisers can be combined by using an `AuthoriserContainer`. This is itself an Authoriser, but one that loops over a
list of other Authorisers, checking the request against each one in turn. It has two modes, "requireAll" and "requireOne",
which determines which of the Authorisers need to pass before returning its own result. "requireAll" is similar to a 
logical AND operation, whereas "requireOne" is a logical OR

Using containers, Authorisers can be chained and combined in complex ways, allowing complete flexibility in applying
your security requirements. LazyBoy sets up a default AuthoriserContainer, which you can use by adding the `api.authorisers`
tag to your Authoriser service definition:

```yaml
services:
  myAuthoriser:
    class: MyAuthoriser
    tags:
      - "api.authorisers"
```

Alternatively, you can use your own authoriser by replacing the `api.authoriser` service definition

## Logging

LazyBoy provides a stub service to allow logging, found in the `logging.yml` DI config file. It is integrated into the 
generated code using the PSR-3 `Psr\Log\LoggerInterface`, but you will need to set up your own logger in order for errors
and other logs to be handled correctly

## Contributing

If you have improvements you would like to see, open an issue in this github project or better yet, fork the project,
implement your changes and create a pull request.

The project uses [PSR-12] code styles and we insist that these are strictly adhered to. Also, please make sure that your
code works with php 8.0.

## Why "LazyBoy"?
Because it likes REST, of course :)


[Slim]: https://github.com/slimphp/slim
[Syringe]: https://github.com/Lexide/syringe
[Puzzle-DI]: https://github.com/lexide/puzzle-di
[ProForma]: https://github.com/lexide/pro-forma
[Symfony console]: https://github.com/symfony/console
[PSR-12]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-12-extended-coding-style-guide.md
