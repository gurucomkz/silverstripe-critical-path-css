<?php

namespace Gurucomkz\Critpath\Extensions;

use Gurucomkz\Critpath\Helpers\CritpathHelper;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ResourceURLGenerator;
use SilverStripe\View\Requirements;

/**
 * @property ContentController $owner
 */
class ControllerExtension extends Extension
{

    public function afterCallActionHandler()
    {
        if (defined('CRITPATH_SCANNER_RUNNING')) {
            return;
        }
        if ($inline = CritpathHelper::getCriticalCSS($this->owner->dataRecord)) {
            Requirements::customCSS($inline, 'critical-path-css');
            Requirements::customScript("
                /* critical-path-loader */
                function __CPCSSLOADER(s) {
                    var isFirefox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1;
                    var head = document.getElementsByTagName('head')[0];
                    var tag = document.createElement('link');
                    tag.href = s;
                    if(isFirefox) {
                        tag.rel = 'stylesheet';
                    } else {
                        tag.rel = 'preload';
                        tag.addEventListener('load',__loadcss);
                        tag.as='style';
                    }
                    head.appendChild(tag);
                }
            ", 'critical-path-loader');

            $backend = Requirements::backend();
            $css = $backend->getCSS();
            foreach ($css as $file => $_) {
                if (Director::is_relative_url($file) || preg_match('/^\//', $file)) {
                    /** @var ResourceURLGenerator */
                    $urlGenerator = Injector::inst()->get(ResourceURLGenerator::class);
                    $backend->clear($file);

                    $fileURL = $urlGenerator->urlForResource($file);

                    Requirements::customScript("__CPCSSLOADER('$fileURL')", $file);
                }
            }
        }
    }
}
