<?php namespace Milkyway\SS\Assets;

/**
 * Milkyway Multimedia
 * RequirementsBackend.php
 *
 * @package milkyway-multimedia/ss-mwm-assets
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Requirements_Backend as Original;
use Milkyway\SS\Utilities;

use Config;
use Controller;
use Exception;
use Director;

class RequirementsBackend extends Original
{
    protected $types = [
        'javascript',
        'css',
        'customScript',
        'customCSS',
        'customHeadTags',
    ];

    public function clear($fileOrID = null) {
        parent::clear($fileOrID);
        singleton('require')->clearFiles($fileOrID);
    }

    public function restore() {
        parent::restore();
        singleton('require')->restoreFiles();
    }

    public function before($files = [], $before = '', $where = '')
    {
        $done = [];
        $insertFilesAt = function($position, $files, &$assets) {
            foreach ($files as $file => $fileAtts) {
                if(is_int($file)) {
                    $file = $fileAtts;
                    $fileAtts = true;
                }

                $assets = array_merge(array_slice($assets, 0, $position), [$file => $fileAtts],
                    array_slice($assets, $position));
                $done[] = $file;
            }
        };

        if ($where) {
            $currentFiles = array_keys($this->$where);
            $position = array_search($before, $currentFiles);

            if ($position !== -1) {
                $insertFilesAt($position, $files, $this->$where);
            }
        } else {
            foreach ($this->types as $type) {
                $currentFiles = array_keys($this->$type);
                $position = array_search($before, $currentFiles);

                if ($position === -1) {
                    continue;
                }

                $insertFilesAt($position, $files, $this->$type);
            }
        }

        return $done;
    }

    public function after($files = [], $after = '', $where = '')
    {
        $done = [];
        $insertFilesAt = function($position, $files, &$assets) {
            foreach ($files as $file => $fileAtts) {
                if(is_int($file)) {
                    $file = $fileAtts;
                    $fileAtts = true;
                }

                $assets = array_merge(array_slice($assets, 0, ($position + 1)), [$file => $fileAtts],
                    array_slice($assets, ($position + 1)));
                $done[] = $file;
            }
        };

        if ($where) {
            $currentFiles = array_keys($this->$where);
            $position = array_search($after, $currentFiles);

            if ($position !== -1) {
                $insertFilesAt($position, $files, $this->$where);
            }
        } else {
            foreach ($this->types as $type) {
                $currentFiles = array_keys($this->$type);
                $position = array_search($after, $currentFiles);

                if ($position === -1) {
                    continue;
                }

                $insertFilesAt($position, $files, $this->$type);
            }
        }

        return $done;
    }

    public function javascript($file)
    {
        if (strpos($file, THIRDPARTY_DIR . '/tinymce/tiny_mce_gzip.php') === 0) {
            return $this->after([
                $file,
            ], THIRDPARTY_DIR . '/jquery/jquery.js', 'javascript');
        }

        return parent::javascript($file);
    }

    public function javascriptTemplate($file, $vars, $uniquenessID = null)
    {
        if (defined('INFOBOXES_DIR') && $file == INFOBOXES_DIR . '/javascript/InfoBoxes.js') {
            $uniquenessID = INFOBOXES_DIR . '/javascript/InfoBoxes.js';
            singleton('require')->blockAjax($uniquenessID);

            if (isset($vars['Data']) && $vars['Data'] === ']') {
                $vars['Data'] = '[]';
            }
        }

        return parent::javascriptTemplate($file, $vars, $uniquenessID);
    }

    public function add_i18n_javascript($langDir, $return = false, $langOnly = false)
    {
        if (!in_array($langDir, $this->blocked) && !isset($this->blocked[$langDir])) {
            return parent::add_i18n_javascript($langDir, $return, $langOnly);
        }

        return $return ? [] : null;
    }

    protected function path_for_file($fileOrUrl)
    {
        if (!Requirements::$use_cache_busted_file_extensions || singleton('require')->isDevelopmentServer()) {
            return parent::path_for_file($fileOrUrl);
        }

        if (preg_match('{^//|http[s]?}', $fileOrUrl)) {
            return $fileOrUrl;
        } elseif (Director::fileExists($fileOrUrl)) {
            return Controller::join_links(Director::baseURL(), singleton('require')->getCacheBustedFileUrl($fileOrUrl));
        } else {
            return false;
        }
    }

    public function customScript($script, $uniquenessID = null)
    {
        if (strpos($script, 'MemberLoginForm')) {
            return '';
        }
        if (strpos($script, 'http://suggestqueries.google.com/complete/search') !== -1 && !$uniquenessID) {
            $uniquenessID = 'googlesuggestfield-script';
            singleton('require')->blockAjax($uniquenessID);
        }

        if ($uniquenessID) {
            $this->customScript[$uniquenessID] = $script;
        } else {
            $this->customScript[] = $script;
        }

        $script .= "\n";

        return $script;
    }

    private $_response;

    public function includeInHTML($templateFile, $content)
    {
        if ($this->Eventful()) {
            $this->Eventful()->fire('assets:beforeProcessHtml', $templateFile, $content);
        }

        $this->assets();
        $body = parent::includeInHTML($templateFile, $content);
        $this->attachCustomScriptsToResponse();

        if ($this->Eventful()) {
            $this->Eventful()->fire('assets:afterProcessHtml', $body, $templateFile, $content);
        }

        return $body;
    }

    public function include_in_response(\SS_HTTPResponse $response)
    {
        if ($this->Eventful()) {
            $this->Eventful()->fire('assets:beforeProcessResponse', $response);
        }

        $this->assets();

        parent::include_in_response($response);
        if (Director::is_ajax()) {
            $this->_response = $response;
        }
        $this->attachCustomScriptsToResponse();

        if ($this->Eventful()) {
            $this->Eventful()->fire('assets:afterProcessResponse', $response);
        }
    }

    /*
     * Allow JS and CSS to be deferred even when called via ajax
     * @todo Does not work in CMS, which uses jquery ondemand anyway
     */
    protected function attachCustomScriptsToResponse()
    {
        if (!$this->_response) {
            return;
        }

        if (!$this->customScript || empty($this->customScript)) {
            $this->_response = null;

            return;
        }

        $scripts = '';

        foreach (array_diff_key($this->customScript, $this->blocked,
            singleton('require')->getBlockAjax()) as $name => $script) {
            $scripts .= "<script type=\"text/javascript\">\n";
            $scripts .= "$script\n";
            $scripts .= "</script>\n";
        }

        $body = $this->_response->getBody();

        $end = stripos($body, '</body>');

        if ($end !== false) {
            $body = preg_replace("/(<\/body[^>]*>)/i", $scripts . "\\1", $body);
        } elseif (!$this->_response->getHeader('X-Pjax') && !$this->_response->getHeader('X-DisableDeferred') && strpos($this->_response->getHeader('Content-Type'),
                'text/html') !== false
        ) {
            $body .= $scripts;
        }

        $this->_response->setBody($body);

        $this->_response = null;
    }

    protected function assets()
    {
        $firstCss = singleton('require')->getFilesByType('css');
        $firstJs = singleton('require')->getFilesByType('js');
        $lastCss = singleton('require')->getFilesByType('css', 'last');
        $lastJs = singleton('require')->getFilesByType('js', 'last');

        $this->css = array_merge(($firstCss + array_diff_key($this->css, $firstCss, $lastCss)), $lastCss);
        $this->javascript = array_merge(($firstJs + array_diff_key($this->javascript, $firstJs, $lastJs)), $lastJs);

        $this->issueReplacements();

        $inline = array_diff_key(array_merge(singleton('require')->getFilesByType('css', 'inline'), singleton('require')->getFilesByType('css', 'inline-head')), $this->blocked);
        $this->inlineFiles($inline, 'customCSS', 'css', '%s', 'Inline-CSS');

        $this->inlineFiles(array_diff_key(singleton('require')->getFilesByType('js', 'inline-head'), $this->blocked), 'customHeadTags', 'javascript',
            '<script type="text/javascript">%s</script>', 'Inline-JS-Head');
        $this->inlineFiles(array_diff_key(singleton('require')->getFilesByType('js', 'inline'), $this->blocked), 'customScript', 'javascript', '%s',
            'Inline-JS');

        $deferred = array_diff_key(singleton('require')->getFilesByType('css', 'defer'), $this->blocked);
        $time = time();

        if (!empty($deferred)) {
            foreach ($deferred as $file => $data) {
                $this->removeIfFound($file, 'css');

                $this->removeIfFound('Deferred-CSS', 'customScript');

                $function = 'js' . $time;
                $script = singleton('require')->deferCss($deferred, $function);

                if (Director::is_ajax()) {
                    $script .= '
	' . $function . '();
					';
                } else {
                    singleton('require')->attachToEventJs();
                    $script .= '
	mwm.utilities.attachToEvent(window, "load", ' . $function . ');
					';
                }

                $this->customScript($script, 'Deferred-CSS');
            }
        }

        $deferred = array_diff_key(singleton('require')->getFilesByType('js', 'defer'), $this->blocked);

        if (!empty($deferred)) {
            foreach ($deferred as $file => $data) {
                $this->removeIfFound($file, 'javascript');
                $this->removeIfFound('Deferred-JS', 'customScript');

                $function = 'js' . $time;

                $script = singleton('require')->deferScripts($deferred, $function);

                if (Director::is_ajax()) {
                    $script .= '
	' . $function . '();
					';
                } else {
                    singleton('require')->attachToEventJs();
                    $script .= '
	mwm.utilities.attachToEvent(window, "load", ' . $function . ');
					';
                }

                $this->customScript($script, 'Deferred-JS');
            }
        }
    }

    protected function inlineFiles(
        $inlines,
        $setVar = 'customCSS',
        $unsetVar = 'css',
        $replaceString = '%s',
        $id = 'Inline-CSS'
    ) {
        if (!empty($inlines)) {
            $this->removeIfFound($id, $setVar);

            $items = [];
            $isDev = Director::isDev();

            foreach ($inlines as $file => $data) {
                if (!Director::is_absolute_url($file)) {
                    $file = Director::getAbsFile($file);
                }

                $key = Utilities::clean_cache_key($file);
                $content = singleton('require')->cache()->load($key);

                if ($content === false) {
                    $content = @file_get_contents($file);

                    if ($content && !$isDev) {
                        $content = singleton('require')->minifyContentsAccordingToType($content, $file);
                    }

                    if (!$isDev) {
                        singleton('require')->cache()->save($content, $key);
                    }
                }

                if ($content) {
                    $items[$file] = $content;
                    $this->removeIfFound($id, $unsetVar);
                }
            }

            if (!empty($items)) {
                if ($setVar == 'customHeadTags') {
                    $this->insertHeadTags(
                        sprintf($replaceString, implode("\n\n", $items)),
                        $id
                    );
                } elseif ($setVar == 'customScript') {
                    $this->customScript(
                        sprintf($replaceString, implode("\n\n", $items)),
                        $id
                    );
                } elseif ($setVar == 'customCSS') {
                    $this->customCSS(
                        sprintf($replaceString, implode("\n\n", $items)),
                        $id
                    );
                }
            }
        }
    }

    protected function removeIfFound($file, $var = 'css')
    {
        if (isset($this->{$var}[$file])) {
            unset($this->{$var}[$file]);
        }
    }

    protected function issueReplacements()
    {
        foreach (Requirements::$disable_replaced_files_for as $class) {
            if (Controller::curr() && is_a(Controller::curr(), $class)) {
                return;
            }
        }

        $replaced = singleton('require')->replacements();

        if (!empty($replaced)) {
            foreach ($replaced as $old => $new) {
                foreach($this->types as $type) {
                    if (isset($this->$type[$old])) {
                        $old = $this->$type[$old];
                        unset($this->$type[$old]);
                        $this->$type[$new] = $old;
                    }
                }
            }
        }
    }

    private $_eventful;

    private function Eventful()
    {
        if ($this->_eventful !== null) {
            return $this->_eventful;
        }

        try {
            if (Config::inst()->get('Injector', 'Eventful') !== null) {
                $this->_eventful = singleton('Eventful');
            } else {
                $this->_eventful = false;
            }
        } catch (Exception $e) {
            $this->_eventful = false;
        }

        return $this->_eventful;
    }
}
