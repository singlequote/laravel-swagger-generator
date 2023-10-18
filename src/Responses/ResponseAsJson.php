<?php

namespace SingleQuote\SwaggerGenerator\Responses;

use Illuminate\Support\Facades\File;

use function str;

/**
 * Description of PrimaryKeyParameter
 *
 * @author wim_p
 */
class ResponseAsJson
{
    /**
     * @var string
     */
    protected string $stubJson;
    /**
     * @var string
     */
    protected string $stubEmpty;
    /**
     * @var string
     */
    protected string $schema;

    /**
     * @param int $code
     * @param string $resource
     * @param array $route
     * @return string
     */
    public function handle(int $code, string $resource, array $route): string
    {
        $this->schema = str($resource)
                ->camel()
                ->ucFirst()
                ->append($route['type'])
                ->prepend($route['method']);

        $this->stubJson = File::get(__DIR__ . "/../stubs/responses/json.stub");
        $this->stubEmpty = File::get(__DIR__ . "/../stubs/responses/empty.stub");

        switch ($code) {
            case 200:
                return $this->codeSuccessFull($code, $route);
            case 422:
                return $this->codeFail($code, $route);
            case 500:
                return $this->codeFail($code, $route);
            case 404:
                return $this->codeFail($code, $route);
            default:
                return $this->codeFail($code, $route);
        }
    }

    /**
     * @param int $code
     * @param array $route
     * @return string
     */
    private function codeSuccessFull(int $code, array $route): string
    {
        if (count($route['rules']) === 0) {
            return str($this->stubEmpty)->replace(['<description>', '<code>'], ["Successful operation", $code]);
        }

        return str($this->stubJson)
                ->replace("<description>", 'Successful operation')
                ->replace("<code>", $code)
                ->replace("<schema>", "'#/components/schemas/{$this->schema}Schema'");
    }

    /**
     * @param int $code
     * @param array $route
     * @return string
     */
    private function codeFail(int $code, array $route): string
    {
        return str($this->stubEmpty)->replace(['<description>', '<code>'], ["Unsuccessfull operation", $code]);
    }
}
