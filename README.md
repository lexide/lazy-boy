# lazy-boy
A skeleton REST API application, using [Silex] and [Syringe] with support for [Puzzle-DI]

## Summary
Lazy boy will create a skeleton [Silex] framework, so you can create REST APIs without having to bother with 
boilerplate code.

It is packaged with a route loader and uses [Syringe], which allows you to define both your routes and services in 
configuration files, rather than PHP

If you have the [Symfony console] installed, it will also create a console script and automatically load any commands it
finds in the service container (any service name which ends with ".command" and is an instance of the Symfony Command 
class). You can also use [Puzzle-DI] to load service configuration from modules.

## Requirements

* [Silex] 2.0+

## Installation
install using composer:

    composer require silktide/lazy-boy:^2.0

Lazy Boy will automatically generate several files from templates, whenever `composer update` or `composer install` is run.
You are free to make modifications; Lazy Boy will not overwrite a file which already exists, so committing those changes 
to a VCS is safe. Having your VCS ignore the files will mean they are generated when you install vendors on a freshly 
cloned repository.

If you want to disable automatic file generation, so you can use the FrontController or RouteLoader perhaps, add the 
following to your composer file:

```json
"extra": {
  "lazy-boy": {
    "prevent-install": true
  }
}
```

All that is left to do is create a vhost or otherwise point requests to `web/index.php`.
 
## Routing

### Routes

If you are using the standard Lazy-Boy route loader, you can define your routes in configuration files, using YAML or
JSON. Each route is defined as follows:

```yaml
routes:
    route-name:
        url: /sub/directory
        action: "test_controller:doSomething"
        method: post
```

`routes` is an associative array of routes that you want to allow access to.

In this case, a HTTP request that was `POST`ed to `/sub/directory`, would access a service in the container called
`test-controller` and call it's method `doSomething`. This route could be referenced as `route-name` when using the 
router.

For each route, the `url` and `action` parameters are required, but `method` is optional and defaults to `GET`.

You can also use the `assert` parameter to overwrite the default regex for parameter of a route. For example

```yaml
    routes:
        route-one:
            url: /user/{id}
            action: "test_controller:doSomething"
            method: get
```

The URL `/user/56` would match and the `id` parameter would come back as `56`.
The URL `/user/56/foo` would not match.
```yaml
    routes:
        route-two:
            url: /user/{my_wildcard}
            action: "test_controller:doSomethingElse
            method: get
            assert:
                my_wildcard: ".*"
```

Going to the URL `/user/56` would match and again, the `my_wildcard` parameter would come back as `56`.
Going the the URL `/user/56/foo` would match and the `my_wildcard` parameter would return `56/foo`

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
        urlPrefix: /users
        routes:
            user-list:
                url: /
                action: "..."
            get-user:
                url: /{id}
                action: "..."
            user-login:
                url: /login
                action: "..."
                method: post
            user-logout:
                url: /logout
                action "..."
```

### Imports

if you have a lot of routes, it can be convenient to separate related routes into different files. In this case, you can
import files into a parent file by using the `imports` array:

```yaml
imports:
    - users.yml
    - shop/products.yml
    - shop/checkout.yml

groups:
    group: "..."
routes:
    route: "..."
```

Imported files are merged into a single configuration array before routes and groups are processed. Where route naming 
conflicts arise, the latter import will overwrite the former and the importing file will take precedence over any 
imported routes.

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

Allowed method values are case insensitive

## Providers

### CORS Provider

The CORS provider can be used to give your API the ability to accept cross domain requests. It is enabled by default and 
can be configured by adding the following parameters to your app's syringe config:

#### Allowed Headers

```yaml
parameters:
    cors.request.defaultHeaders:
# optional, automatically set to:
#     ["Content-Type", "Authorization"]
    cors.request.headers:
      - "X-CUSTOM-REQUEST_HEADER"
    cors.response.headers:
      - "X-CUSTOM-RESPONSE-HEADER"
```

These parameters allow for additional headers to be sent and received by the client. `cors.request.defaultHeaders` 
contains the headers commonly required by Lazy-Boy apps, but can be overwritten if these headers need to be removed. The 
headers and defaultHeaders are merged together, so you only need to set these configuration options if you need to use
additional headers or to restrict headers.

#### Allowed Methods

```yaml
parameters:
    # defaults to the value of router.allowedMethods
    cors.allowedMethods:
        - "get" 
        - "post"
        - "put"
```

The `allowedMethods` parameters set which HTTP methods can be used with your API. You can use these to use a more 
restricted list than the RouteLoader allows. In the above example, CORS requests will only be allowed for `GET`, `POST` 
and `PUT` methods, so cross origin sources cannot make a request to `DELETE`. 

The `OPTIONS` method is required for CORS to work, so is always added automatically; it is not required for it to be in 
the configuration list

Also, it should be obvious, but is worth noting that any allowed CORS methods that aren't in the `router.allowedMethods` 
configuration list will not work as the `RouteLoader` will reject them

## Contributing

If you have improvements you would like to see, open an issue in this github project or better yet, fork the project,
implement your changes and create a pull request.

The project uses [PSR-2] code styles and we insist that these are strictly adhered to. Also, please make sure that your
code works with php 5.4, so things like generators, `finally`, `empty(someFunction())`, etc... should be avoided

## Why "Lazy Boy"
Because it likes REST, of course :)


[Silex]: https://github.com/silexphp/silex
[Syringe]: https://github.com/silktide/syringe
[Puzzle-DI]: https://github.com/downsider/puzzle-di
[Symfony console]: https://github.com/symfony/console
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
