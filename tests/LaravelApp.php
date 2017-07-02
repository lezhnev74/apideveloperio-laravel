<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */
declare(strict_types=1);

namespace HttpAnalyzerTest;

use HttpAnalyzer\Laravel\HttpAnalyzerServiceProvider;
use Illuminate\Config\Repository;
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
    
    
}