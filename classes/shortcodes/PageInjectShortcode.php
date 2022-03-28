<?php

namespace Grav\Plugin\Shortcodes;

use Grav\Plugin\PageInjectPlugin;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

class PageInjectShortcode extends Shortcode
{
    public function init()
    {
        $this->shortcode->getRawHandlers()->add('page-inject', function(ShortcodeInterface $sc) {
            $path = $sc->getParameter('path') ?? $sc->getBbCode();
            return PageInjectPlugin::getInjectedPageContent('page-inject', $path, $this->shortcode->getPage());
        });

        $this->shortcode->getRawHandlers()->add('content-inject', function(ShortcodeInterface $sc) {
            $path = $sc->getParameter('path') ?? $sc->getBbCode();
            return PageInjectPlugin::getInjectedPageContent('content-inject', $path, $this->shortcode->getPage());
        });
    }
}