<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 */
declare(strict_types=1);

namespace HttpAnalyzerTest;

use HttpAnalyzer\Laravel\HttpAnalyzerServiceProvider;
use Orchestra\Testbench\TestCase;

abstract class LaravelApp extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [HttpAnalyzerServiceProvider::class];
    }
}