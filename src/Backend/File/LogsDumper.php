<?php
/**
 * @author Dmitriy Lezhnev <lezhnev.work@gmail.com>
 * Date: 25/01/2018
 */

namespace Apideveloper\Laravel\Backend\File;

interface LogsDumper
{
    public function dump($data_array);
}