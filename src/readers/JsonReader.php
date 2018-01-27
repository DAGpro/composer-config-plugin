<?php
/**
 * Composer plugin for config assembling
 *
 * @link      https://github.com/hiqdev/composer-config-plugin
 * @package   composer-config-plugin
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2016-2018, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\composer\config\readers;

/**
 * JsonReader - reads PHP files.
 *
 * @author Andrii Vasyliev <sol@hiqdev.com>
 */
class JsonReader extends AbstractReader
{
    public function readRaw($path, $builder)
    {
        return json_decode($this->getFileContents($path), true);
    }
}
