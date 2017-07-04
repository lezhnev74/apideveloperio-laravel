<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */

namespace HttpAnalyzerTest;

use HttpAnalyzer\Laravel\HttpAnalyzerServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Logging\Log;
use Orchestra\Testbench\TestCase;

abstract class LaravelApp extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [HttpAnalyzerServiceProvider::class];
    }
    
    public function createApplication()
    {
        $app = parent::createApplication();
        
        // Drop config value to allow testing
        $config = $app[Repository::class];
        $config->set('http_analyzer.filtering.ignore_environment', []);
        
        return $app;
    }
    
    protected function getTmpPath($suffix = '')
    {
        $path = __DIR__ . "/tmp/" . time() . "_" . $suffix;
        mkdir($path);
        
        return $path;
    }
    
}