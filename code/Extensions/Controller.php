<?php namespace Milkyway\SS\Assets\Extensions;

/**
 * Milkyway Multimedia
 * Controller.php
 *
 * @package milkyway-multimedia/ss-mwm-assets
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\Assets\Requirements;
use Extension;

class Controller extends Extension
{
    /**
     * Disable cache busted file extensions for some classes (usually @LeftAndMain)
     */
    function onBeforeInit()
    {
        foreach (Requirements::$disable_cache_busted_file_extensions_for as $class) {
            if (is_a($this->owner, $class)) {
                Requirements::$use_cache_busted_file_extensions = false;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function onAfterInit()
    {
        $this->blockDefaults();
        $this->additionalLeftAndMainRequirements();
    }

    /**
     * Block files using the @Injector
     */
    protected function blockDefaults() {
        foreach (Requirements::$disable_blocked_files_for as $class) {
            if (is_a($this->owner, $class)) {
                return;
            }
        }

        singleton('require')->blockDefault();
    }

    /**
     * Block some items from ajax
     */
    protected function additionalLeftAndMainRequirements() {
        if($this->owner instanceof \KickAssets) {
            return;
        }

        singleton('require')->blockAjax('htmlEditorConfig');
        singleton('require')->blockAjax('googlesuggestfield-script');
    }
} 