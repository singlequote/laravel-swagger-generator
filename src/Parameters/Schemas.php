<?php

namespace SingleQuote\SwaggerGenerator\Parameters;

use Illuminate\Support\Facades\File;
use SingleQuote\SwaggerGenerator\Actions\RetrieveFilesByParent;

use function collect;
use function str;

/**
 * Description of PrimaryKeyParameter
 *
 * @author wim_p
 */
class Schemas
{
    /**
     * @param RetrieveFilesByParent $retrieveFilesByParent
     */
    public function __construct(protected RetrieveFilesByParent $retrieveFilesByParent)
    {

    }


    public function handle(string $resource, array $routes): string
    {
        $content = "";

        $stubFile = File::get(__DIR__ . "/../stubs/schemas/schema.stub");


        foreach($routes as $key => $route) {

            if(count($route['rules']) === 0) {
                continue;
            }

            $content .= str($stubFile)
                ->replace('<type>', $route['type'])
                ->replace('<method>', $route['method'])
                ->replace("<resourceCamel>", str($resource)->camel()->ucFirst())
                ->replace('<properties>', $this->extractProperties($resource, $route));
        }

        return $content;
    }

    private function extractProperties(string $resource, array $route): string
    {
        $content = "";
        $stubFile = File::get(__DIR__ . "/../stubs/schemas/property.stub");

        $fillables = $route['model']?->getFillable() ?? [];

        foreach ($route['rules'] as $key => $rule) {
            if(! in_array($key, $fillables)) {
                continue;
            }

            $rules = is_array($rule) ? $rule : explode('|', $rule);

            $content .= $this->parseSingleRule($stubFile, $key, $rules);
        }

        return $content;
    }

    /**
     * @param string $stubRule
     * @param string $key
     * @param array $rules
     * @return string
     */
    private function parseSingleRule(string $stubRule, string $key, array $rules): string
    {
        return str($stubRule)
                ->replace('<name>', $key)
                ->replace('<nullable>', in_array('nullable', $rules) ? 'true' : 'false')
                ->replace('<type>', in_array('string', $rules) ? 'string' : 'int')
                ->replace('<format>', $this->parseFormat($key, $rules))
                ->replace('<description>', $this->parseDescription($key, $rules))
                ->replace('<default>', $this->getDefault($key, $rules));
    }

    /**
     * @param string $key
     * @param array $rules
     * @return string
     */
    private function parseFormat(string $key, array $rules): string
    {
        if (in_array('string', $rules)) {
            return 'string';
        }

        if (in_array('int', $rules)) {
            return 'int';
        }

        return "string";
    }

    /**
     * @param string $key
     * @param array $rules
     * @return string
     */
    private function parseDescription(string $key, array $rules): string
    {
        $ruleLine = str(implode('|', $rules))->prepend('|');

        if ($ruleLine->contains('|in:')) {
            return $ruleLine->betweenFirst('|in:', "|")->prepend('eg: ');
        }

        if ($ruleLine->containsAll(['|min:', '|max:'])) {
            return collect($rules)->filter(function ($rule) {
                return str($rule)->contains(['min:', 'max:']);
            })->implode(' | ');
        }

        return "Filter by $key";
    }

    /**
     * @param string $key
     * @param array $rules
     * @return null|string|int
     */
    private function getDefault(string $key, array $rules): null|string|int
    {
        $ruleLine = str(implode('|', $rules))->prepend('|');

        if (in_array('nullable', $rules) || str($ruleLine)->contains('_unless')) {
            return 'null';
        }

        if ($ruleLine->contains('|in:')) {
            return $ruleLine->betweenFirst('|in:', ',');
        }

        if (in_array('int', $rules)) {
            return $ruleLine->contains('min') ? $ruleLine->betweenFirst('min:', ',') : 1;
        }

        if (in_array('required', $rules)) {
            return 'string';
        }

        return 'null';
    }
}
