<?php

namespace Lexide\LazyBoy\ProForma;

use Lexide\ProForma\Template\ProviderConfig\LibraryConfig;
use Lexide\ProForma\Template\ProviderConfig\ProjectConfig;
use Lexide\ProForma\Template\TemplateFactory;
use Lexide\ProForma\Template\TemplateProviderInterface;

class TemplateProvider implements TemplateProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getTemplates(ProjectConfig $projectConfig, LibraryConfig $libraryConfig): array
    {
        $namespace = $projectConfig->getNamespace();
        $usingPuzzle = $projectConfig->hasInstalledPackage("lexide/puzzle-di");

        $apiGateway = !empty($libraryConfig->getValue("apiGateway"));
        $restApi = $libraryConfig->getValue("rest") ?? !$apiGateway;
        $useCors = $libraryConfig->getValue("useCors") ?? $restApi;
        $lambda = !empty($libraryConfig->getValue("lambda"));
        $console = !empty($libraryConfig->getValue("console"));

        $bootstrapReplacements = [
            "puzzleConfigUseStatement" => $usingPuzzle
                ? "use {$namespace}PuzzleConfig;"
                : "",
            "puzzleConfigLoadFiles" => $usingPuzzle
                ? '    $puzzleConfigs = PuzzleConfig::getConfigItems("lexide/syringe");' . "\n" .
                  '    $builder->addConfigFiles($puzzleConfigs);' . "\n"
                : "",
            "lambdaEnvConfigDir" => $apiGateway || $lambda
                ? '"$appDir/environment/$environment",'
                : ""
        ];

        $consoleReplacements = [
            "consoleAppName" => $libraryConfig->getValue("consoleName") ?? $projectConfig->getName() ?: "Application",
            "setAutoExit" => $lambda
                ? "      - method: \"setAutoExit\"\n" .
                  "        arguments:\n" .
                  "          - false'\n\n"
                : "",
            "lambdaInputBuilder" => $lambda
                ? "  lambda.inputBuilder:\n" .
                "    class: Lexide\LazyBoy\Lambda\InputBuilder\n"
                : ""
        ];

        $apiReplacements = [
            "apiGatewayRequestFactory" => $apiGateway
                ? "  apiGateway.requestFactory:\n" .
                  "    class: LexisNexis\SelfDeclarations\Request\RequestFactory\n\n"
                : "",
            "addCorsMiddleware" => $useCors
                ? '      - method: "add"' . "\n" .
                  '        arguments:' . "\n" .
                  '          - "@api.cors.middleware"' . "\n\n"
                : ""
        ];

        $servicesReplacements = [
            "apiImport" => $restApi || $apiGateway
                ? '  - "api.yml"' . "\n"
                : "",
            "consoleImport" => $console || $lambda
                ? '  - "console.yml"' . "\n"
                : "",
        ];

        $templates = [
            TemplateFactory::create(
                "servicesConfig",
                "templates/app/config/services.yml.temp",
                "app/config/services.yml",
                $servicesReplacements
            ),
            TemplateFactory::create(
                "bootstrap",
                "templates/app/bootstrap.php.temp",
                "app/bootstrap.php",
                $bootstrapReplacements
            )
        ];
        if ($restApi || $apiGateway) {
            $templates[] = TemplateFactory::create(
                "apiConfig",
                "templates/app/config/api.yml.temp",
                "app/config/api.yml",
                $apiReplacements
            );
            $templates[] = TemplateFactory::create(
                "routesConfig",
                "templates/app/config/routes.yml.temp",
                "app/config/routes.yml"
            );
            $templates[] = $restApi
                ? TemplateFactory::create(
                    "index",
                    "templates/web/index.php.temp",
                    "web/index.php"
                )
                : TemplateFactory::create(
                    "api",
                    "templates/web/api.php.temp",
                    "web/api.php"
                );
        }

        if ($console || $lambda) {
            $templates[] = TemplateFactory::create(
                "consoleConfig",
                "templates/app/config/console.yml.temp",
                "app/config/console.yml",
                $consoleReplacements
            );
            $templates[] = $console
                ? TemplateFactory::create(
                    "console",
                    "templates/app/console.temp",
                    "app/console"
                )
                : TemplateFactory::create(
                    "lambda",
                    "templates/app/lambda.temp",
                    "app/lambda"
                );
        }

        return $templates;
    }

}