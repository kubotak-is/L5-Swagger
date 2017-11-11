<?php

namespace L5Swagger;

use Illuminate\Filesystem\Filesystem;

class Generator
{
    /** @var Filesystem  */
    private $file;
    
    /**
     * Generator constructor.
     * @param Filesystem $file
     */
    public function __construct(Filesystem $file)
    {
        $this->file = $file;
    }
    
    public function generateDocs()
    {
        $appDir = config('l5-swagger.paths.annotations');
        $docDir = config('l5-swagger.paths.docs');
        if (! $this->file->exists($docDir) || is_writable($docDir)) {
            // delete all existing documentation
            if ($this->file->exists($docDir)) {
                $this->file->deleteDirectory($docDir);
            }
            
            $this->defineConstants(config('l5-swagger.constants') ?: []);
            
            $this->file->makeDirectory($docDir);
            $excludeDirs = config('l5-swagger.paths.excludes');
            $swagger = \Swagger\scan($appDir, ['exclude' => $excludeDirs]);
            
            if (config('l5-swagger.paths.base') !== null) {
                $swagger->basePath = config('l5-swagger.paths.base');
            }
            
            $filename = $docDir.'/'.config('l5-swagger.paths.docs_json', 'api-docs.json');
            $swagger->saveAs($filename);
            
            self::appendSecurityDefinisions($filename);
        }
    }
    
    protected function defineConstants(array $constants)
    {
        if (! empty($constants)) {
            foreach ($constants as $key => $value) {
                defined($key) || define($key, $value);
            }
        }
    }
    
    protected function appendSecurityDefinisions(string $filename)
    {
        $securityConfig = config('l5-swagger.security', []);
        
        if (is_array($securityConfig) && ! empty($securityConfig)) {
            $documentation = collect(
                json_decode(file_get_contents($filename))
            );
            
            $securityDefinitions = $documentation->has('securityDefinitions') ? collect($documentation->get('securityDefinitions')) : collect();
            
            foreach ($securityConfig as $key => $cfg) {
                $securityDefinitions->offsetSet($key, $this->arrayToObject($cfg));
            }
            
            $documentation->offsetSet('securityDefinitions', $securityDefinitions);
            
            file_put_contents($filename, $documentation->toJson());
        }
    }
    
    public function arrayToObject($array)
    {
        return json_decode(json_encode($array));
    }
}
