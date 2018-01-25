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

    protected function getEnvironmentSetUp($app)
    {
        /** @var Repository $config */
        $config = app()[Repository::class];

        $config->set('apideveloperio_logs.httplog.tmp_storage_path', $this->getTmpPath(__LINE__));
        $config->set('apideveloperio_logs.textlog.tmp_storage_path', $this->getTmpPath(__LINE__));
    }

    public function createApplication()
    {
        $app = parent::createApplication();

        // Drop config value to allow testing
        $config = $app[Repository::class];
        $config->set('apideveloperio_logs.httplog.filtering.ignore_environment', []);
        $config->set('apideveloperio_logs.textlog.filtering.ignore_environment', []);

        // This is to speed up test execution
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
        $path = $this->getTmpDir() . md5(microtime()) . "_" . $suffix;
        mkdir($path, 0777, true);

        return $path;
    }

    protected function getTmpDir()
    {
        return __DIR__ . "/tmp/";
    }

}