<?php

namespace SingleQuote\SwaggerGenerator\Actions;

use Illuminate\Support\Facades\File;
use ReflectionClass;

use function base_path;
use function collect;
use function str;

/**
 * Description of PrimaryKeyParameter
 *
 * @author wim_p
 */
class RetrieveFilesByParent
{
    /**
     * @param string $parent
     * @return array
     */
    public function handle(string $parent): array
    {
        $folders = File::directories(base_path());

        $allowed = collect($folders)->filter(function ($folder) {
            return !in_array(str($folder)->replace('\\', '/')->afterLast('/')->value(), [
                'vendor', 'node_modules', 'resources', 'config', 'public', 'storage', 'tests', 'database', 'routes', 'bootstrap', 'lang'
            ]);
        });

        $possibleFolders = [];

        foreach ($allowed as $folder) {
            $found = $this->byFolder($folder, $parent);
            $possibleFolders = array_merge($found, $possibleFolders);
        }

        return $possibleFolders;
    }

    /**
     * @param string $folder
     * @param string $parent
     * @return array
     */
    public function byFolder(string $folder, string $parent): array
    {
        $models = collect(File::allFiles($folder))->filter(function ($class) use ($parent) {
            $className = str($class->getPathName())
                ->after(base_path('\\'))
                ->before('.php')
                ->value();

            if (class_exists("$className")) {
                $reflection = new ReflectionClass($className);
                return $reflection->isSubclassOf($parent);
            }
        })->map(function ($class) {

            $className = str($class->getPathName())
                ->after(base_path('\\'))
                ->before('.php')
                ->value();

            return $className;
        });

        return $models->values()->toArray();
    }
}
