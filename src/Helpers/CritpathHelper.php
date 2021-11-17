<?php
namespace Gurucomkz\Critpath\Helpers;

use Exception;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;

/**
 * Helper function to read/write Critical path CSS cache that is shared between cli & web server.
 */
class CritpathHelper
{
    public static function getCriticalCSS(SiteTree $page)
    {
        $filename = self::cacheFilename($page);
        if (file_exists($filename) && is_readable($filename)) {
            return @file_get_contents($filename);
        }
    }

    public static function setCriticalCSS(SiteTree $page, $content)
    {
        $dir = self::cacheFolder();
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new Exception("Cannot create cache directory '$dir'");
            }
        }
        $filename = self::cacheFilename($page);
        if (!@file_put_contents($filename, $content)) {
            throw new Exception("Cannot create cache file '$filename'");
        }
    }

    public static function cacheFolder(): string
    {
        return TEMP_PATH . '/critpath-cache/';
    }

    public static function cacheFilename(DataObject $obj): string
    {
        return self::cacheFolder() . '/' . str_replace('\\', '-', $obj->ClassName) . '_' . $obj->ID . '.css';
    }
}
