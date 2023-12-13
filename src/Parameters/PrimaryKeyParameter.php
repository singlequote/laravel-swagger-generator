<?php
namespace SingleQuote\SwaggerGenerator\Parameters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use SingleQuote\SwaggerGenerator\Actions\RetrieveFilesByParent;
use function str;

/**
 * Description of PrimaryKeyParameter
 *
 * @author wim_p
 */
class PrimaryKeyParameter
{

    /**
     * @param RetrieveFilesByParent $retrieveFilesByParent
     */
    public function __construct(protected RetrieveFilesByParent $retrieveFilesByParent)
    {
        
    }

    /**
     * @param string $modelName
     * @param string $resource
     * @param array $route
     * @return string
     */
    public function handle(string $modelName, string $resource, array $route): string
    {
        $pathParamaters = [];

        $this->extractPathParameters($route['url'], $pathParamaters);

        $model = $route['model'];

        return $this->parseParameters($pathParamaters, $modelName, $resource, $model);
    }

    /**
     * @param array $pathParamaters
     * @param string $modelName
     * @param string $resource
     * @param Model|null $model
     * @return string
     */
    private function parseParameters(array $pathParamaters, string $modelName, string $resource, ?Model $model): string
    {
        $stubFile = __DIR__ . "/../stubs/parameters/primaryKey.stub";
        $content = "";

        foreach ($pathParamaters as $parameter) {

            if (!$model) {
                continue;
            }

            if ($parameter->toString() === $modelName) {
                $primaryKey = str($resource)->singular()->replace('-', '_')->toString();
                $format = $model->getKeyType() === 'string' ? 'uuid' : 'int';
                $type = $model->getKeyType() === 'string' ? 'string' : 'number';
            } else {
                $primaryKey = $parameter;
                $format = 'string';
                $type = 'string';
            }


            $content .= str(File::get($stubFile))
                ->replace('<required>', 'true')
                ->replace('<description>', "$primaryKey parameter")
                ->replace('<primary>', $primaryKey)
                ->replace('<resource>', $resource)
                ->replace('<format>', $format)
                ->replace('<type>', $type);
        }

        return $content;
    }

    /**
     * @param string $url
     * @param array $pathParamaters
     * @return array
     */
    private function extractPathParameters(string $url, &$pathParamaters): array
    {
        if (!str($url)->contains("{") || !str($url)->contains("}")) {
            return $pathParamaters;
        }

        $parameter = str($url)->betweenFirst("{", "}");

        $pathParamaters[] = $parameter;

        return $this->extractPathParameters(str($url)->replace("{{$parameter}}", "[$parameter]"), $pathParamaters);
    }
}
