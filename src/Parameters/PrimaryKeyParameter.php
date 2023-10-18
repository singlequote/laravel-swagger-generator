<?php

namespace SingleQuote\SwaggerGenerator\Parameters;

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
     * @param string $resource
     * @param array $route
     * @return string
     */
    public function handle(string $resource, array $route): string
    {
        $stubFile = __DIR__ . "/../stubs/parameters/primaryKey.stub";
        $content = File::get($stubFile);

        $primaryKey = str($resource)->singular()->replace('-', '_')->toString();

        $model = $route['model'];

        if(! $model) {
            return "";
        }

        return str($content)
            ->replace('<primary>', $primaryKey)
            ->replace('<resource>', $resource)
            ->replace('<format>', $model->getKeyType() === 'string' ? 'uuid' : 'int')
            ->replace('<type>', $model->getKeyType() === 'string' ? 'string' : 'number');

    }
}
