<?php

namespace Lexide\LazyBoy\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enables application to handles CORS requests
 *
 * TODO: Review security implications
 */
class CorsServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{

    public function register(Container $pimple)
    {
        $pimple["cors.request.defaultHeaders"] = ["Content-Type", "Authorization"];
        $pimple["cors.request.headers"] = [];
        $pimple["cors.response.headers"] = [];
    }

    public function boot(Application $app) {
        $allowedResponseHeaders = $app["cors.response.headers"];
        $allowedRequestHeaders = array_merge($app["cors.request.defaultHeaders"], $app["cors.request.headers"]);

        // default the CORS allowed methods to what is set for the router, if we don't have any set spcifically for CORS
        $allowedMethods = $app->offsetExists("cors.allowedMethods")
            ? $app["cors.allowedMethods"]
            : $app["router.allowedMethods"];

        // The OPTIONS method is required for CORS, so add that regardless
        $allowedMethods[] = "options";

        //handling CORS preflight request
        $app->before(function (Request $request) use ($allowedRequestHeaders, $allowedMethods) {
            if ($request->getMethod() === "OPTIONS") {
                $response = new Response();
                $response->headers->set("Access-Control-Allow-Origin", "*");
                $response->headers->set("Access-Control-Allow-Methods", strtoupper(implode(",", $allowedMethods)));
                $response->headers->set("Access-Control-Allow-Headers", implode(",", $allowedRequestHeaders));

                $response->setStatusCode(200);
                $response->send();
                exit();
            }
        }, Application::EARLY_EVENT);

        //handling CORS response with right headers
        $app->after(function (Request $request, Response $response) use ($allowedResponseHeaders, $allowedMethods) {
            $response->headers->set("Access-Control-Allow-Origin", "*");
            $response->headers->set("Access-Control-Allow-Methods", strtoupper(implode(",", $allowedMethods)));
            $response->headers->set("Access-Control-Expose-Headers", implode(",", $allowedResponseHeaders));
        });
    }

} 
