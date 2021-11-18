<?php
namespace Gurucomkz\Critpath\Tasks;

use Exception;
use Gurucomkz\Critpath\Helpers\CritpathHelper;
use Page;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Control\SimpleResourceURLGenerator;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ModuleResource;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Core\Manifest\ResourceURLGenerator;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;
use SilverStripe\View\Requirements_Backend;

class GenerateCriticalPathCSS extends BuildTask
{
    private static $segment = 'GenerateCriticalPathCSS';

    private $critpathScript;

    /** @var CacheInterface */
    private $cache;

    /** @var Requirements_Backend */
    private $backend;

    public function __construct()
    {
        parent::__construct();
        $this->critpathScript = realpath(__DIR__ . '/../../cli.js');
        $this->backend = Requirements::backend();
    }


    private function getPageHTML(SiteTree $page)
    {
        $session = Injector::inst()->create(Session::class, []);
        $ctrlClass = $page->getControllerName();
        $dummyRQ = new HTTPRequest('GET', '/');
        if (RootURLController::should_be_on_root($page)) {
            // dirty hack ?
            $dummyRQ->setUrl(RootURLController::get_homepage_link() . '/index');
            $dummyRQ->match('$URLSegment//$Action', true);
        }
        $dummyRQ->setSession($session);

        /** @var ContentController */
        $ctrl = new $ctrlClass($page);
        $rsp = $ctrl->handleRequest($dummyRQ);

        if ($rsp->getStatusCode() !== 200) {
            throw new Exception("BAD ERROR CODE: " . $rsp->getStatusCode());
        }

        $pageHTML = $rsp->getBody();

        if (!$pageHTML) {
            throw new Exception("EMPTY PAGE");
        }
        return $pageHTML;
    }

    public function run($request)
    {
        define('CRITPATH_SCANNER_RUNNING', true);
        /** @var SimpleResourceURLGenerator */
        $urlGenerator = Injector::inst()->get(ResourceURLGenerator::class);
        $urlGenerator->setNonceStyle(null);

        Versioned::set_stage(Versioned::LIVE);
        $pages = Page::get();

        foreach ($pages as $page) {
            /** @var Page $page */
            $localPageHTMLPath = null;
            Requirements::clear();
            echo $page->Link() . "\n";
            try {
                $pageHTML = $this->getPageHTML($page);
                echo "\tHTML Size: " . strlen($pageHTML) . "\n";

                $localPageHTMLPath = TEMP_PATH . 'critpath-' . $page->ID . '.html';
                if (!file_put_contents($localPageHTMLPath, $pageHTML)) {
                    throw new Exception('FAILED TO WRITE TMP FILE');
                }

                $allCSS = $this->backend->getCSS();

                $cssFiles = [];
                foreach ($allCSS as $cssTmpPath => $props) {
                    $cssUrl = $urlGenerator->urlForResource($cssTmpPath);
                    if ($cssUrl) {
                        $cssPath = PUBLIC_PATH . $cssUrl;
                        $cssFiles[] = $cssPath;
                    }
                }
                if (count($cssFiles)) {
                    $result = $this->generateCriticalPathCSS($localPageHTMLPath, $cssFiles);

                    CritpathHelper::setCriticalCSS($page, $result);
                    echo "\tCritical CSS size: " . strlen($result) . "\n";
                }
            } catch (\Throwable $th) {
                if ($localPageHTMLPath) {
                    @unlink($localPageHTMLPath);
                }
                echo "\t\e[0;31m" . "ERROR: " . $th->getMessage() . "\e[0m\n";
            }
        }
    }

    private function generateCriticalPathCSS($localPageHTMLPath, $cssFiles)
    {
        $cssArg = '';
        foreach ($cssFiles as $cssPath) {
            $cssArg .= " --css " . escapeshellarg($cssPath);
        }

        $pageFullURL = escapeshellarg('file:///' . $localPageHTMLPath);
        $cmd = "{$this->critpathScript} --url $pageFullURL $cssArg";

        @exec($cmd, $result, $errorCode);
        if ($errorCode) {
            throw new Exception("JS file exited with error code $errorCode", 1);
        }
        $result = implode($result);
        return $result;
    }
}
