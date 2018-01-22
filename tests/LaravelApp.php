<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */

namespace Apideveloper\Laravel\Tests;

use Apideveloper\Laravel\Laravel\ApideveloperioServiceProvider;
use Illuminate\Config\Repository;
use Orchestra\Testbench\TestCase;

abstract class LaravelApp extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ApideveloperioServiceProvider::class];
    }

    public function createApplication()
    {
        $app = parent::createApplication();

        // Drop config value to allow testing
        $config = $app[Repository::class];
        $config->set('apideveloperio_logs.httplog.filtering.ignore_environment', []);

        $app['hash']->setRounds(4);

        return $app;
    }

    protected function tearDown()
    {
        `rm -rf {$this->getTmpDir()}*`;
        parent::tearDown();
    }


    protected function getTmpPath($suffix = '')
    {
        $path = $this->getTmpDir() . time() . "_" . $suffix;
        mkdir($path, 0777, true);

        return $path;
    }

    protected function getTmpDir()
    {
        return __DIR__ . "/tmp/";
    }

}