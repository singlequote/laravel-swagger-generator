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
use function str;

class Make extends Command
{
    /**
     * @var  string
     */
    protected $signature = 'swagger:generate';

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
    protected string $prefixName = 'api.invoices';

    /**
     * @var string|null
     */
    protected string $outputPath = "Modules/Api/resources/docs";

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
    ) {
        parent::__construct();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        $prefix = $this->ask("What is the API prefix", $this->prefixName);

        $this->prefixName = str($prefix)->endsWith('.') ? $prefix : "$prefix.";

        $this->extractApiRoutes();
        $this->groupByResource();

        $this->info("Trying to locate the request files...");

        $this->requests = $this->retrieveFilesByParent->handle(FormRequest::class);

        $this->outputPath = $this->ask("Where do you want to store the generated files? Please use a relative path form the root", $this->outputPath);
        $this->extractResourceRoutes();
    }

    /**
     * @return void
     */
    private function preBuildYamlFiles(): void
    {
        $bar = $this->output->createProgressBar(count($this->parsed));
        $bar->start();

        foreach ($this->parsed as $resource => $routes) {
            $stubFile = __DIR__ . "/../stubs/resource.stub";
            $content = str(File::get($stubFile));
            $schemas = "";
            $paths = "";

            foreach ($routes as $url => $route) {
                $paths .= $this->parseResourceFile($resource, $url, $route);
                $schemas .= $this->extractSchema($resource, $route);
            }

            $replaced = $content->replace("<schemas>", $schemas)
                ->replace('<paths>', $paths);

            if (!File::isDirectory($this->outputPath)) {
                File::makeDirectory($this->outputPath);
            }

            File::put("$this->outputPath/{$resource}.yaml", $replaced);

            $bar->advance();
        }

        $bar->finish();
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

        if (str($route['url'])->contains("{{$model}}")) {
            $parameters .= $this->primaryKeyParameter->handle($resource, $route);
        }

        $parameters .= $this->requestRules->handle($resource, $route);

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

            if (!$this->confirm("Route group $group found, would you like to import it?", true)) {
                return true;
            }

            $this->resolveRequiredData($group, $routes);

            $this->preBuildYamlFiles();

            $this->parsed = [];

            $this->line("$group imported.....");
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

        $requests = collect($this->requests)->filter(function ($request) use ($resource) {
            return str($request)->contains($resource->singular()->value());
        })->prepend("Don't use a request");

        $model = $this->extractModel($resource);

        foreach ($routes as $route) {

            $request = $this->findRequestByName($requests, $resource, $route);

            if (!$request) {
                $request = $this->choice("Please select the request file for {$route->getName()}", $requests->values()->toArray(), 0);
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
        $routeName = str($route->getName())->afterLast('.')->ucFirst()->value();
        $resourceName = $resource->singular()->value();

        $shouldNamed = "{$routeName}{$resourceName}Request";

        return $requests->firstWhere(function ($request) use ($shouldNamed) {
            return str($request)->endsWith($shouldNamed);
        });
    }
    //
    //    /**
    //     * @param SplFileInfo $file
    //     * @return array
    //     */
    //    private function extractClassInfo(SplFileInfo $file): array
    //    {
    //        $data = [
    //            'basePath' => $file->getPathname(),
    //            'relativePath' => $file->getPath(),
    //        ];
    //        $content = $file->getContents();
    //
    //        $lines = preg_split('/\r\n|\r|\n/', $content);
    //
    //        foreach ($lines as $line) {
    //
    //            if (str($line)->startsWith('namespace')) {
    //                $data['namespace'] = str($line)->after('namespace ')->before(';')->toString();
    //            }
    //            if (str($line)->startsWith('class')) {
    //                $data['model'] = str($line)->after('class ')->before(' ')->toString();
    //            }
    //            if (str($line)->startsWith('final class')) {
    //                $data['model'] = str($line)->after('final class ')->before(' ')->toString();
    //            }
    //        }
    //
    //
    //        return $data;
    //    }

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
