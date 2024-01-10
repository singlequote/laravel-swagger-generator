<?php
namespace SingleQuote\SwaggerGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as Route2;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Stringable;
use SingleQuote\SwaggerGenerator\Actions\RetrieveFilesByParent;
use SingleQuote\SwaggerGenerator\Parameters\PrimaryKeyParameter;
use SingleQuote\SwaggerGenerator\Parameters\RequestRules;
use SingleQuote\SwaggerGenerator\Parameters\Schemas;
use SingleQuote\SwaggerGenerator\Responses\ResponseAsJson;
use function collect;
use function config;
use function str;

class Make extends Command
{

    /**
     * @var  string
     */
    protected $signature = 'swagger:generate {--f} {--skip-missing}';

    /**
     * @var  string
     */
    protected $description = 'Create seeders from your models using the database';

    /**
     * @var array
     */
    protected array $requests = [];

    /**
     * @var array
     */
    protected array $routeKeys = [];

    /**
     * @var array|Collection
     */
    protected array|Collection $routes = [];

    /**
     * @var array
     */
    protected array $parsed = [];

    /**
     * @var string
     */
    protected string $prefixName = 'api.';

    /**
     * @var string
     */
    protected string $fileName = "index";

    /**
     * @var string
     */
    protected string $title = "Generated with Laravel Swagger Generator";

    /**
     * @var bool
     */
    protected bool $storeAsSeperateFiles = false;

    /**
     * @param PrimaryKeyParameter $primaryKeyParameter
     * @param RequestRules $requestRules
     * @param Schemas $schemas
     * @param ResponseAsJson $responseAsJson
     * @param RetrieveFilesByParent $retrieveFilesByParent
     */
    public function __construct(
        protected PrimaryKeyParameter $primaryKeyParameter,
        protected RequestRules $requestRules,
        protected Schemas $schemas,
        protected ResponseAsJson $responseAsJson,
        protected RetrieveFilesByParent $retrieveFilesByParent,
    )
    {
        parent::__construct();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        $this->storeAsSeperateFiles = $this->confirm("Would you like to store the yaml files seperated by resource?", $this->storeAsSeperateFiles);

        $prefix = $this->ask("What is the API prefix", $this->prefixName);

        $this->prefixName = str($prefix)->endsWith('.') ? $prefix : "$prefix.";

        $this->extractApiRoutes();
        $this->groupByResource();

        $this->info("Trying to locate the request files...");

        $this->requests = $this->retrieveFilesByParent->handle(FormRequest::class);

        $this->finishUp();
    }

    /**
     * @return void
     */
    private function finishUp(): void
    {
        $this->extractResourceRoutes();

        if (!File::isDirectory(config('laravel-swagger-generator.output_path'))) {
            File::makeDirectory(config('laravel-swagger-generator.output_path'));
        }

        if ($this->storeAsSeperateFiles) {
            $this->storeAsSeperatedFiles();
        } else {

            $this->fileName = $this->ask("Save generated file as", $this->fileName);

            $this->storeAsSingleFile();
        }
        $this->line('');
        $this->line('================================================');

        $this->info("Successfull imported all selected api routes!");
    }

    /**
     * @return void
     */
    private function storeAsSeperatedFiles(): void
    {
        $bar = $this->output->createProgressBar(count($this->parsed));
        $bar->start();

        foreach ($this->parsed as $resource => $routes) {
            $stubFile = __DIR__ . "/../stubs/resource.stub";
            $content = str(File::get($stubFile));
            $schemas = $paths = "";

            foreach ($routes as $url => $route) {
                $paths .= $this->parseResourceFile($resource, $url, $route);
                $schemas .= $this->extractSchema($resource, $route);
            }

            $replaced = $content->replace("<schemas>", $schemas)
                ->replace(['<version>', '<title>'], [config('laravel-swagger-generator.version'), config('laravel-swagger-generator.title')])
                ->replace(['<securitySchemes>', '<paths>'], [$this->extractSecurity(), $paths]);

            $outputPath = config('laravel-swagger-generator.output_path');

            File::put("$outputPath/{$resource}.yaml", $replaced);

            $bar->advance();
        }

        $bar->finish();
    }

    /**
     * @return void
     */
    private function storeAsSingleFile(): void
    {
        $bar = $this->output->createProgressBar(count($this->parsed));
        $bar->start();

        $stubFile = __DIR__ . "/../stubs/resource.stub";
        $content = str(File::get($stubFile));
        $schemas = $paths = "";

        foreach ($this->parsed as $resource => $routes) {
            foreach ($routes as $url => $route) {
                $paths .= $this->parseResourceFile($resource, $url, $route);
                $schemas .= $this->extractSchema($resource, $route);
            }
            $bar->advance();
        }

        $replaced = $content->replace("<schemas>", $schemas)
            ->replace('<securitySchemes>', $this->extractSecurity())
            ->replace(['<version>', '<title>'], [config('laravel-swagger-generator.version'), config('laravel-swagger-generator.title')])
            ->replace('<paths>', $paths);

        $outputPath = config('laravel-swagger-generator.output_path');

        File::put("$outputPath/$this->fileName.yaml", $replaced);

        $bar->finish();
    }

    /**
     * @param string $resource
     * @param array $routes
     * @return string
     */
    private function extractSecurity(): string
    {
        $stubFile = __DIR__ . "/../stubs/security-schemes.stub";

        return str(File::get($stubFile));
    }

    /**
     * @param string $resource
     * @param array $routes
     * @return string
     */
    private function extractSchema(string $resource, array $routes): string
    {
        $stubFile = __DIR__ . "/../stubs/schemas.stub";

        return str(File::get($stubFile))
                ->replace("<schema>", $this->schemas->handle($resource, $routes));
    }

    /**
     * @param string $resource
     * @param array $routes
     * @return string
     */
    private function parseResourceFile(string $resource, string $url, array $routes): string
    {
        $stubFile = __DIR__ . "/../stubs/resource-route.stub";
        $pathsFile = File::get(__DIR__ . "/../stubs/paths.stub");

        $paths = "";
        $content = File::get($stubFile);

        foreach ($routes as $route) {

            $paths .= str($pathsFile)
                ->replace("<resource>", $resource)
                ->replace("<resourceCamel>", str($resource)->camel()->ucFirst())
                ->replace("<type>", $route['type'])
                ->replace("<method>", $route['method'])
                ->replace("<parameters>", $this->extractParameters($resource, $route))
                ->replace("<requestBody>", $this->extractRequestBody($resource, $route))
                ->replace("<responses>", $this->extractResponses($resource, $route));
        }

        return str($content)
                ->replace('<url>', $url)
                ->replace('<paths>', $paths);
    }

    /**
     * @param string $resource
     * @param array $route
     * @return string
     */
    private function extractParameters(string $resource, array $route): string
    {
        $stubFile = __DIR__ . "/../stubs/parameters.stub";
        $content = str(File::get($stubFile));
        $parameters = "";

        $model = str($resource)->singular()->replace('-', '_')->toString();

        if (str($route['url'])->contains("{") && str($route['url'])->contains("}")) {
            $parameters .= $this->primaryKeyParameter->handle($model, $resource, $route);
        }

        if (!in_array($route['method'], ['post', 'put', 'patch'])) {
            $parameters .= $this->requestRules->handle($resource, $route);
        }

        return $content->replace('<parameters>', $parameters);
    }

    /**
     * @param string $resource
     * @param array $route
     * @return string
     */
    private function extractRequestBody(string $resource, array $route): string
    {
        if (in_array($route['method'], ['get']) || count($route['rules']) === 0) {
            return "";
        }

        $stubFile = __DIR__ . "/../stubs/request-body.stub";
        $content = File::get($stubFile);

        $schema = str($resource)
            ->camel()
            ->ucFirst()
            ->append($route['type'])
            ->prepend($route['method']);

        return str($content)->replace("<schema>", "'#/components/schemas/{$schema}Schema'");
    }

    /**
     * @param string $resource
     * @param array $route
     * @return string
     */
    private function extractResponses(string $resource, array $route): string
    {
        $stubFile = __DIR__ . "/../stubs/responses.stub";
        $content = str(File::get($stubFile));

        $responses = "";

        foreach ([200, 422, 500, 404] as $code) {
            $responses .= $this->responseAsJson->handle($code, $resource, $route);
        }

        return $content->replace('<codes>', $responses);
    }

    /**
     * @return void
     */
    private function extractResourceRoutes(): void
    {
        $this->routes->each(function ($routes, $group) {

            if (in_array($group, config('laravel-swagger-generator.exclude_resources'))) {
                return true;
            }

            $this->resolveRequiredData($group, $routes);
        });
    }

    /**
     * @param string $group
     * @param Collection $routes
     * @return void
     */
    private function resolveRequiredData(string $group, Collection $routes): void
    {
        $resource = str($group)->camel()->ucfirst();

        $predictedName = $resource->singular()->value();

        $requests = collect($this->requests)->filter(function ($request) use ($predictedName) {
                return str($request)->contains($predictedName);
            })->prepend("Don't use a request class");

        $model = $this->extractModel($resource);

        foreach ($routes as $route) {

            $request = $this->findRequestByName($requests, $resource, $route);

            if (!$request && !$this->option('skip-missing')) {
                $request = $this->option('f') ? $requests->first() : $this->choice("Please select the request file for {$route->getName()} - $predictedName", $requests->values()->toArray(), 0);
            }

            $rules = class_exists($request) ? (new $request())->rules() : [];

            $method = str($route->methods()[0])->lower()->value();

            $this->parsed[$group][$route->uri()][$method] = [
                'url' => $route->uri(),
                'model' => $model,
                'rules' => $rules,
                'type' => str($route->getName())->afterLast('.')->ucFirst()->value(),
                'method' => $method,
            ];
        }
    }

    /**
     * @param string $resource
     * @return Model|null
     */
    private function extractModel(string $resource): ?Model
    {
        $primaryKey = str($resource)->singular()->replace('-', '_')->toString();
        $modelName = str($primaryKey)->singular()->camel()->ucFirst();

        $models = $this->retrieveFilesByParent->handle(Model::class);
        $modelClass = $this->findModelsByName($resource, $models, $modelName);

        return $modelClass ? new $modelClass() : null;
    }

    /**
     * @param string $resource
     * @param array $models
     * @param string $modelName
     * @return string|null
     */
    private function findModelsByName(string $resource, array $models, string $modelName): ?string
    {
        $possible = collect($models)->where(function ($request) use ($modelName) {
            return str($request)->contains($modelName);
        });

        if ($possible->count() === 1) {
            return $possible->first();
        }

        if ($possible->isEmpty()) {
            return null;
        }
        if ($this->option('f')) {
            return $possible->first();
        }

        return $this->choice("Which model should i use for the resource $resource?", $possible->values()->toArray(), 0);
    }

    /**
     * @param Collection $requests
     * @param Stringable $resource
     * @param Route2 $route
     * @return string|null
     */
    private function findRequestByName(Collection $requests, Stringable $resource, Route2 $route): ?string
    {
        $routeName = str($route->getName())->camel()->afterLast('.')->ucFirst()->value();
        $resourceName = $resource->singular()->value();

        $shouldNamed = "{$routeName}{$resourceName}Request";

        $requestsFound = $requests->firstWhere(function ($request) use ($shouldNamed) {
            return str($request)->endsWith($shouldNamed);
        });

        if (!$requestsFound && !$this->option('f') && !$this->option('skip-missing')) {
            $this->error("Could not found a request file named $shouldNamed");
        }

        return $requestsFound;
    }

    /**
     * @return void
     */
    private function groupByResource(): void
    {
        $this->routes = collect($this->routeKeys)->groupBy(function ($route, $key) {
            return str($key)->betweenFirst($this->prefixName, '.');
        });
    }

    /**
     * @return void
     */
    private function extractApiRoutes(): void
    {
        $routes = Route::getRoutes();

        foreach ($routes->getRoutesByName() as $name => $route) {
            if (str($name)->startsWith($this->prefixName)) {
                $this->routeKeys[$name] = $route;
            }
        }
    }
}
