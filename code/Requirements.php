<?php namespace Milkyway\SS\Assets;

/**
 * Milkyway Multimedia
 * Requirements.php
 *
 * @package milkyway-multimedia/ss-mwm-assets
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Requirements as Original;
use Config;
use Director;
use DBField;
use SS_Cache;
use Controller;

class Requirements extends Original implements \Flushable, \TemplateGlobalProvider
{
    /** @var bool Append the cache busting id as a file extension rather than as a query string */
    public static $use_cache_busted_file_extensions = false;

    /** @var array Disable cache busted file extensions for specific controllers */
    public static $disable_cache_busted_file_extensions_for = [
        'LeftAndMain',
    ];

    /** @var array Disable blocked files for specific controllers */
    public static $disable_blocked_files_for = [
        'LeftAndMain',
    ];

    /** @var array Disable replacement files for specific controllers */
    public static $disable_replaced_files_for = [];

    protected $files = [
        'first'       => [
            'css' => [],
            'js'  => [],
        ],
        'last'        => [
            'css' => [],
            'js'  => [],
        ],
        'defer'       => [
            'css' => [],
            'js'  => [],
        ],
        'inline'      => [
            'css' => [],
            'js'  => [],
        ],
        'inline-head' => [
            'css' => [],
            'js'  => [],
        ],
    ];

    protected $disabledFiles;

    protected $replace = [];
    protected $blockAjax = [];

    protected $cache;

    public static function config()
    {
        return Config::inst()->forClass('Milkyway_Assets');
    }

    public static function flush()
    {
        singleton('require')->cache()->clean();
    }

    public static function get_template_global_variables()
    {
        return [
            'inlineFile',
            'placeIMG',
            'loremIpsum',
        ];
    }

    public function clearFiles($fileOrID = null) {
        if($fileOrID) {
            foreach($this->files as $where => $types) {
                foreach($types as $type) {
                    if(isset($this->files[$where][$type][$fileOrID])) {
                        $this->disabledFiles[$where][$type][$fileOrID] = $this->files[$where][$type][$fileOrID];
                        unset($this->files[$where][$type][$fileOrID]);
                    }
                }
            }
        }
        else {
            $this->disabledFiles = $this->files;

            $this->files = [
                'first'       => [
                    'css' => [],
                    'js'  => [],
                ],
                'last'        => [
                    'css' => [],
                    'js'  => [],
                ],
                'defer'       => [
                    'css' => [],
                    'js'  => [],
                ],
                'inline'      => [
                    'css' => [],
                    'js'  => [],
                ],
                'inline-head' => [
                    'css' => [],
                    'js'  => [],
                ],
            ];
        }
    }

    public function restoreFiles() {
        $this->files = $this->disabledFiles;
    }

    public function getFilesByType($type, $where = 'first')
    {
        if (isset($this->files[$where]) && isset($this->files[$where][$type])) {
            return $this->files[$where][$type];
        }

        return [];
    }

    public function replacements()
    {
        return $this->replace;
    }

    public function getBlockAjax()
    {
        return $this->blockAjax;
    }

    public function add($files, $where = 'first', $before = '', $override = '')
    {
        if (is_string($files)) {
            $files = [$files];
        }

        if (!isset($this->files[$where])) {
            return;
        }

        foreach ($files as $file) {
            $type = $override ?: strtok(strtok(pathinfo($file, PATHINFO_EXTENSION), '#'), '?');

            if ($type == 'css' || $type == 'js') {
                if ($before && isset($this->files[$where][$before])) {
                    $i = 0;
                    foreach ($this->files[$where][$type] as $key => $ret) {
                        if ($key == $before) {
                            array_splice($this->files[$where][$type], $i, 0, [$file => $ret]);
                            break;
                        }

                        $i++;
                    }
                } else {
                    if ($type == 'css') {
                        $this->files[$where][$type][$file] = ['media' => ''];
                    } else {
                        $this->files[$where][$type][$file] = true;
                    }
                }
            }
        }
    }

    public function before($files, $before = '', $where = '')
    {
        if($where) {
            return $this->add($files, $where, $before);
        }

       $this->backend()->before($files, $before);
    }

    public function after($files, $after = '', $where = '')
    {
        $this->backend()->after($files, $after, $where);
    }

    public function first($files, $before = '')
    {
        $this->add($files, 'first', $before);
    }

    public function last($files, $before = '')
    {
        $this->add($files, 'last', $before);
    }

    public function remove($files, $where = '')
    {
        if (is_string($files)) {
            $files = [$files];
        }

        if ($where && !isset($this->files[$where])) {
            return;
        }

        foreach ($files as $file) {
            if ($where) {
                if (isset($this->files[$where][$file])) {
                    unset($this->files[$where][$file]);
                }
            } else {
                foreach ($this->files as $where => $files) {
                    if (isset($files[$file])) {
                        unset($files[$file]);
                    }
                }
            }
        }
    }

    // Load a requirement as a deferred file (loaded using Google Async)
    public function defer($file, $before = '', $override = '')
    {
        self::add($file, 'defer', $before, $override);
    }

    public function undefer($file)
    {
        self::remove($file, 'defer');
    }

    public function inline($file, $top = false, $before = '', $override = '')
    {
        if ($top) {
            self::add($file, 'inline-head', $before, $override);
        } else {
            self::add($file, 'inline', $before, $override);
        }
    }

    public function outline($file)
    {
        self::remove($file, 'inline-head');
        self::remove($file, 'inline');
    }

    // Replace a requirement file with another
    public function replace($old, $new)
    {
        $this->replace[$old] = $new;
    }

    public function unreplace($file)
    {
        if (isset($this->replace[$file])) {
            unset($this->replace[$file]);
        } elseif (($key = array_search($file, $this->replace)) && $key !== false) {
            unset($this->replace[$key]);
        }
    }

    public function blockAjax($file)
    {
        $this->blockAjax[$file] = true;
    }

    public function unblockAjax($file)
    {
        if (isset($this->blockAjax[$file])) {
            unset($this->blockAjax[$file]);
        } elseif (($key = array_search($file, $this->blockAjax)) && $key !== false) {
            unset($this->blockAjax[$key]);
        }
    }

    public function head($file)
    {
        if ($file && (Director::is_absolute_url($file) || Director::fileExists($file)) && ($ext = pathinfo($file,
                PATHINFO_EXTENSION)) && ($ext == 'js' || $ext == 'css')
        ) {
            $file = Director::is_absolute_url($file) || singleton('require')->isDevelopmentServer() ? $file : Controller::join_links(Director::baseURL(), $this->getCacheBustedFileUrl($file));

            if ($ext == 'js') {
                $this->insertHeadTags('<script src="' . $file . '"></script>', $file);
            } else {
                $this->insertHeadTags('<link href="' . $file . '" rel="stylesheet" />', $file);
            }
        }
    }

    public function blockDefault()
    {
        $blocked = (array)self::config()->block;

        if (empty($blocked)) {
            return;
        }

        foreach ($blocked as $block) {
            preg_match_all('/{{([^}]*)}}/', $block, $matches);

            if (!empty($matches[1])) {
                foreach ($matches[1] as $match) {
                    if (strpos($match, '|') !== false) {
                        list($const, $default) = explode('|', $match);
                    } else {
                        $const = $default = $match;
                    }

                    if (defined(trim($const))) {
                        $block = str_replace('{{' . $match . '}}', constant(trim($const)), $block);
                    } elseif (trim($default)) {
                        $block = str_replace('{{' . $match . '}}', trim($default), $block);
                    }
                }
            }

            preg_match_all('/\[\[([^}]*)\]\]/', $block, $matches);

            if (!empty($matches[1])) {
                foreach ($matches[1] as $match) {
                    if (strpos($match, '|') !== false) {
                        list($const, $default) = explode('|', $match);
                    } else {
                        $const = $match;
                        $default = null;
                    }

                    $block = str_replace('[[' . $match . ']]', trim(singleton('env')->get($const, $default)), $block);
                }
            }

            Requirements::block($block);
        }
    }

    public function getCacheBustedFileUrl($file)
    {
        if ($ext = pathinfo($file, PATHINFO_EXTENSION)) {
            if ($ext == 'js' || $ext == 'css') {
                $myExt = strstr($file, 'combined.' . $ext) ? 'combined.' . $ext : $ext;
                $filePath = preg_replace('/\?.*/', '', Director::baseFolder() . '/' . $file);

                $mTime = $this->get_suffix_requirements() ? "." . filemtime($filePath) : '';

                $suffix = '';
                if (strpos($file, '?') !== false) {
                    $suffix = substr($file, strpos($file, '?'));
                }

                return str_replace('.' . $myExt, '', $file) . "{$mTime}.{$myExt}{$suffix}";
            }
        }

        return false;
    }

    public function attachToEventJs()
    {
        $this->utilitiesJs();
    }

    public function deferCss(array $css, $function = 'css')
    {
        $this->utilitiesJs();
        $script = $this->cache()->load('JS__DeferCSS');

        if (!$script) {
            require_once(THIRDPARTY_PATH . DIRECTORY_SEPARATOR . 'jsmin' . DIRECTORY_SEPARATOR . 'jsmin.php');
            $script = \JSMin::minify('
				function {$FUNCTION}() {
					if(window.mwm && window.mwm.hasOwnProperty("utilities") && window.mwm.utilities.hasOwnProperty("deferCssFiles")) {
						window.mwm.utilities.deferCssFiles({$FILES});
					}
				};
		    ');
            $this->cache()->save($script, 'JS__DeferCSS');
        }

        return str_replace(['function{$FUNCTION}', '{$FUNCTION}', '{$FILES}'], [
            'function ' . $function,
            $function,
            json_encode($css, JSON_UNESCAPED_SLASHES),
        ], $script);
    }

    public function deferScripts(array $scripts, $function = 'js')
    {
        $this->utilitiesJs();
        $script = $this->cache()->load('JS__DeferJS');

        if (!$script) {
            require_once(THIRDPARTY_PATH . DIRECTORY_SEPARATOR . 'jsmin' . DIRECTORY_SEPARATOR . 'jsmin.php');
            $script = \JSMin::minify('
			    function {$FUNCTION}() {
					if(window.mwm && window.mwm.hasOwnProperty("utilities") && window.mwm.utilities.hasOwnProperty("deferJsFiles")) {
						window.mwm.utilities.deferJsFiles({$FILES});
					}
				};
			');
            $this->cache()->save($script, 'JS__DeferJS');
        }

        return str_replace(['function{$FUNCTION}', '{$FUNCTION}', '{$FILES}'], [
            'function ' . $function,
            $function,
            json_encode(array_keys($scripts), JSON_UNESCAPED_SLASHES),
        ], $script);
    }

    public function utilitiesJs()
    {
        $dir = basename(rtrim(dirname(dirname(__FILE__)), DIRECTORY_SEPARATOR));

        if (Director::isDev()) {
            $script = @file_get_contents(Director::getAbsFile($dir . '/js/mwm.utilities.js'));
        } else {
            $script = $this->cache()->load('JS__utilities');

            if (!$script) {
                require_once(THIRDPARTY_PATH . DIRECTORY_SEPARATOR . 'jsmin' . DIRECTORY_SEPARATOR . 'jsmin.php');
                $script = \JSMin::minify(@file_get_contents(\Director::getAbsFile($dir . '/js/mwm.utilities.js')));
                $this->cache()->save($script, 'JS__utilities');
            }
        }

        $this->insertHeadTags('<script>' . $script . '</script>', 'JS-MWM-Utilities');
    }

    public function includeFontCss()
    {
        if ($fonts = $this->config()->font_css) {
            if (!is_array($fonts)) {
                $fonts = [$fonts];
            }

            $this->add($fonts);
        } else {
            $this->add('https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css');
        }
    }

    public function minifyContentsAccordingToType($contents, $file)
    {
        $type = strtok(strtok(pathinfo($file, PATHINFO_EXTENSION), '#'), '?');

        if ($type == 'js') {
            require_once(THIRDPARTY_PATH . DIRECTORY_SEPARATOR . 'jsmin' . DIRECTORY_SEPARATOR . 'jsmin.php');
            return \JSMin::minify($contents);
        } elseif ($type == 'css') {
            return str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '',
                preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $contents));
        }

        return $contents;
    }

    public function inlineFile($files, $theme = false)
    {
        if ($theme) {
            $theme = ($theme === true || $theme == 1) ? Config::inst()->get('SSViewer', 'theme') : $theme;

            if ($theme) {
                $theme = THEMES_DIR . '/' . $theme;
            } else {
                $theme = project();
            }
        }

        $key = preg_replace('/[^a-zA-Z0-9_]/', '', $files . '_' . urldecode(http_build_query(['theme' => $theme], '', '_')));
        $contents = singleton('require')->cache()->load($key);
        $isDev = Director::isDev();

        if ($contents === false) {
            $files = explode(',', $files);

            foreach ($files as $file) {
                $filesToCheck = array_unique(($theme ? [$theme . '/' . $file, project() . '/' . $file, $file] : [project() . '/' . $file, $file]));

                foreach($filesToCheck as $checking) {
                    $file = Director::is_absolute_url($checking) ? $checking : Director::getAbsFile($checking);

                    if ((Director::is_absolute_url($file) || @file_exists($file))) {
                        $contents = @file_get_contents($file);

                        if (!$isDev && $contents) {
                            $contents = singleton('require')->minifyContentsAccordingToType($contents, $file);
                        }

                        if (!$isDev) {
                            singleton('require')->cache()->save($contents, $key);
                        }

                        break;
                    }
                }
            }
        }

        return DBField::create_field('HTMLText', $contents);
    }

    public function placeIMG($width = 400, $height = 300, $categories = 'any', $filters = '')
    {
        return Controller::join_links(Director::protocol() . 'placeimg.com', $width, $height, $categories,
            $filters);
    }

    public function loremIpsum($paragraphs = 1, $length = 'short', $opts = ['plaintext'])
    {
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', $paragraphs . '_' . $length . '_' . implode('_', $opts));

        if (!($text = singleton('require')->cache()->load($key))) {
            $text = @file_get_contents(Controller::join_links('http://loripsum.net/api', $paragraphs, $length,
                implode('/', $opts)));
            singleton('require')->cache()->save($text, $key);
        }

        return $text;
    }

    // Check if using in-built php server
    protected $developmentServer;

    public function isDevelopmentServer() {
        if($this->developmentServer === null) {
            $this->developmentServer = (php_sapi_name() === 'cli-server');
        }

        return $this->developmentServer;
    }

    public function cache()
    {
        if (!$this->cache) {
            $this->cache = SS_Cache::factory('Milkyway_SS_Assets', 'Output', ['lifetime' => 20000 * 60 * 60]);
        }

        return $this->cache;
    }
}
