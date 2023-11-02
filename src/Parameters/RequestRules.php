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
class RequestRules
{
    /**
     * @param RetrieveFilesByParent $retrieveFilesByParent
     */
    public function __construct(protected RetrieveFilesByParent $retrieveFilesByParent)
    {

    }

    /**
     * @param string $resource
     * @param array $route
     * @return string
     */
    public function handle(string $resource, array $route): string
    {
        $content = "";

        foreach ($route['rules'] as $key => $rule) {

            $ruleLine = is_array($rule) ? implode('|', $rule) : $rule;
            $rules = is_array($rule) ? $rule : explode('|', $rule);

            if (in_array('array', $rules)) {
                continue;
            }

            if (str($key)->contains('*')) {
                $content .= $this->parseArrayRule($key, $rules);
                continue;
            }

            if (str($ruleLine)->prepend('|')->contains('|in:')) {
                $content .= $this->parseEnum($key, $rules);
                continue;
            }

            $content .= $this->parseSingleRule($key, $rules);
        }

        return $content;
    }

    /**
     * @param string $key
     * @param array $rules
     * @return string
     */
    private function parseArrayRule(string $key, array $rules): string
    {
        return str(File::get(__DIR__ . "/../stubs/parameters/rule-array.stub"))
                ->replace('<name>', str($key)->before('.'))
                ->replace('<required>', !str($key)->contains('*') && in_array('required', $rules))
                ->replace('<type>', in_array('string', $rules) ? 'string' : 'int')
                ->replace('<description>', $this->parseDescription(str($key)->before('.'), $rules))
                ->replace('<format>', 'format')
                ->replace('<default>', $this->getDefault($key, $rules));
    }

    /**
     * @param string $key
     * @param array $rules
     * @return string
     */
    private function parseSingleRule(string $key, array $rules): string
    {
        return str(File::get(__DIR__ . "/../stubs/parameters/rule.stub"))
                ->replace('<name>', $key)
                ->replace('<required>', in_array('required', $rules))
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
    private function parseEnum(string $key, array $rules): string
    {
        $ruleLine = str(implode('|', $rules))->prepend('|');

        return str(File::get(__DIR__ . "/../stubs/parameters/enum.stub"))
                ->replace('<name>', $key)
                ->replace('<required>', in_array('required', $rules))
                ->replace('<type>', in_array('string', $rules) ? 'string' : 'int')
                ->replace('<format>', $this->parseFormat($key, $rules))
                ->replace('<description>', $this->parseDescription($key, $rules))
                ->replace('<enums>', $ruleLine->betweenFirst('|in:', "|"))
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
            return null;
        }

        if ($ruleLine->contains('|in:')) {
            return $ruleLine->betweenFirst('|in:', ',');
        }

        if (in_array('int', $rules)) {
            return $ruleLine->contains('min') ? $ruleLine->betweenFirst('min:', ',') : 1;
        }

        if (in_array('required', $rules)) {
            return '';
        }

        return '';
    }
}
