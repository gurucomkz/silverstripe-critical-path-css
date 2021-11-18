<?php
namespace Gurucomkz\Critpath\Tasks;

use Gurucomkz\Critpath\Exceptions\CriticalException;
use Gurucomkz\Critpath\Exceptions\StandardException;
use Gurucomkz\Critpath\Helpers\CritpathHelper;
use Page;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Control\SimpleResourceURLGenerator;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ResourceURLGenerator;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;
use SilverStripe\View\Requirements_Backend;

class GenerateCriticalPathCSS extends BuildTask
{
    private static $segment = 'GenerateCriticalPathCSS';

    private $currentPageID;

    private $critpathScript;

    /** @var Requirements_Backend */
    private $backend;

    /** @var SimpleResourceURLGenerator */
    private $urlGenerator;

    public function __construct()
    {
        parent::__construct();
        $this->critpathScript = realpath(__DIR__ . '/../../cli.js');
        $this->backend = Requirements::backend();

        $this->urlGenerator = Injector::inst()->get(ResourceURLGenerator::class);
        $this->urlGenerator->setNonceStyle(null);
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
            throw new StandardException("BAD ERROR CODE: " . $rsp->getStatusCode());
        }

        $pageHTML = $rsp->getBody();

        if (!$pageHTML) {
            throw new StandardException("EMPTY PAGE");
        }
        return $pageHTML;
    }

    public function buildCSSList()
    {
        $allCSS = $this->backend->getCSS();
        $cssFiles = [];
        foreach ($allCSS as $cssTmpPath => $props) {
            if (CritpathHelper::doIncludeExternal() && Director::is_absolute_url($cssTmpPath)) {
                $localCSSVersionPath = TEMP_PATH . '/critpath-' . md5($cssTmpPath) . '.css';
                if (!file_exists($localCSSVersionPath)) {
                    $remoteContents = file_get_contents($cssTmpPath);
                    if (!file_put_contents($localCSSVersionPath, $remoteContents)) {
                        throw new StandardException('FAILED TO WRITE TMP CSS FILE');
                    }
                }
                $cssFiles[] = $localCSSVersionPath;
            } else {
                $cssUrl = $this->urlGenerator->urlForResource($cssTmpPath);
                if ($cssUrl) {
                    $cssPath = PUBLIC_PATH . $cssUrl;
                    $cssFiles[] = $cssPath;
                }
            }
        }
        return $cssFiles;
    }

    /**
     * @param HTTPRequest $request
     * @return void
     */
    public function run($request)
    {
        define('CRITPATH_SCANNER_RUNNING', true);

        Versioned::set_stage(Versioned::LIVE);
        $pages = Page::get()->sort('ID ASC');
        if ($resumeFromID = (int)$request->requestVar('resume')) {
            $pages = $pages->filter('ID:GreaterThanOrEqual', $resumeFromID);
        }

        foreach ($pages as $page) {
            /** @var Page $page */
            $this->currentPageID = $page->ID;

            $localPageHTMLPath = null;
            Requirements::clear();
            echo $page->Link() . "\n";
            try {
                $pageHTML = $this->getPageHTML($page);
                echo "\tHTML Size: " . strlen($pageHTML) . "\n";

                $localPageHTMLPath = TEMP_PATH . '/critpath-' . $page->ID . '.html';
                if (!file_put_contents($localPageHTMLPath, $pageHTML)) {
                    throw new StandardException('FAILED TO WRITE TMP FILE');
                }

                $cssFiles = $this->buildCSSList();

                if (count($cssFiles)) {
                    $result = $this->generateCriticalPathCSS($localPageHTMLPath, $cssFiles);

                    CritpathHelper::setCriticalCSS($page, $result);
                    echo "\tCritical CSS size: " . strlen($result) . "\n";
                }
            } catch (StandardException $th) {
                echo "\t\e[0;31m" . "ERROR: " . $th->getMessage() . "\e[0m\n";
            } catch (CriticalException $th) {
                echo "\t\e[0;31m" . "ERROR: " . $th->getMessage() . "\e[0m\n";
                return;
            } finally {
                if ($localPageHTMLPath) {
                    @unlink($localPageHTMLPath);
                }
            }
        }
    }

    private function generateCriticalPathCSS($localPageHTMLPath, $cssFiles)
    {
        $args = '';
        foreach ($cssFiles as $cssPath) {
            $args .= " --css " . escapeshellarg($cssPath);
        }

        $forceInclude = CritpathHelper::config()->get('force_css_selectors');
        if (is_array($forceInclude)) {
            foreach ($forceInclude as $cssSelector) {
                $args .= " --forceInclude " . escapeshellarg($cssSelector);
            }
        }

        $pageFullURL = escapeshellarg('file:///' . $localPageHTMLPath);
        $cmd = "{$this->critpathScript} --url $pageFullURL $args";
        @exec($cmd, $result, $errorCode);
        if ($errorCode) {
            if ($errorCode == 1) {
                throw new CriticalException(
                    "ERROR: JS Libraries not configured. \n" .
                    "Please, run 'yarn' or 'npm install' in " . dirname(dirname(__DIR__)) . "\n"
                );
            }
            if ($errorCode == -1) {
                throw new CriticalException(
                    "ERROR: nodejs failed. Not enough memory, maybe? \n" .
                    "You can try to resume with 'sake dev/tasks/" . self::$segment . " resume={$this->currentPageID}\n"
                );
            }
            throw new StandardException("JS file exited with error code $errorCode", 1);
        }
        $result = implode($result);
        return $result;
    }
}
