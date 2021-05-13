<?php

namespace Grav\Plugin\Shortcodes;

use Grav\Common\Data\Data;
use Grav\Common\Page\Page;
use Grav\Common\Twig\Twig;
use Grav\Common\Uri;
use Thunder\Shortcode\Shortcode\ProcessedShortcode;
use Twig\Error\LoaderError;

class InjectPageShortcode extends Shortcode
{
    public function init()
    {
        $this->shortcode->getHandlers()->add('page-inject', function (ProcessedShortcode $sc) {
            if ($sc->hasParameter('remote')) {
                return $this->injectRemotePage($sc);
            } else {
                return $this->injectPage($sc);
            }
        });

        /** @var Data */
        $config = $this->grav->offsetGet('piConfig');

        if ($config->get('processed_content')) {
            $this->shortcode->getHandlers()->add('content-inject', function (ProcessedShortcode $sc) {
                return $this->injectContent($sc);
            });
        } else {
            $this->shortcode->getRawHandlers()->add('content-inject', function (ProcessedShortcode $sc) {
                return $this->injectContent($sc);
            });
        }
    }

    /**
     * Handles shortcode '[page-inject] inserting a remote page'.
     * 
     * @param ProcessedShortcode $sc Containes parameters and content of shortcode.
     * @return string Generated string to replace shortcode with.
     */
    protected function injectRemotePage(ProcessedShortcode $sc): string
    {
        try {
            $content = file_get_contents($sc->getParameter('remote'));
            $template = $sc->getParameter('template');

            if ($template) {
                return $this->applyTemplate($template, $content);
            } else {
                return $content;
            }
        } catch (\Exception $ex) {
            return $sc->getShortcodeText();
        }
    }

    /**
     * Handles shortcode '[page-inject] inserting a local page'.
     * 
     * @param ProcessedShortcode $sc Containes parameters and content of shortcode.
     * @return string Generated string to replace shortcode with.
     */
    protected function injectPage(ProcessedShortcode $sc): string
    {
        $inject = $this->findPage($sc);

        // Return original shortcode if page not found
        if (!$inject) {
            return $sc->getShortcodeText();
        }

        // Force HTML to avoid issues with News Feeds
        $inject->templateFormat('html');

        $template = $sc->getParameter('template');

        if ($template) {
            $inject->template($template);
        }

        $inject->modularTwig(true);
        $content = $inject->content();

        if (preg_match("/<code>$template\.html\.twig<\/code>/", $content)) {
            return $sc->getShortcodeText();
        }

        return $content;
    }

    /**
     * Handles shortcode '[content-inject]'.
     * 
     * @param ProcessedShortcode $sc Containes parameters and content of shortcode.
     * @return string Generated string to replace shortcode with.
     */
    protected function injectContent(ProcessedShortcode $sc): string
    {
        /** @var Page|null */
        $inject = $this->findPage($sc);

        if (!$inject) {
            return $sc->getShortcodeText();
        }

        // Force HTML to avoid issues with News Feeds
        $inject->templateFormat('html');

        /** @var Data */
        $config = $this->grav->offsetGet('piConfig');

        if ($config->get('processed_content') === false) {
            return $inject->rawMarkdown();
        } else {
            return $inject->content();
        }
    }

    /**
     * Find Grav page.
     * 
     * @return Page|null Returns the page found, or null
     */
    private function findPage(ProcessedShortcode $sc): ?Page
    {
        /** @var Page */
        $page = $this->shortcode->getPage();
        $route = Uri::convertUrl($page, $sc->getParameter('page'), 'link', false, true);

        return $page->find($route);
    }

    /**
     * Process template on content of page.
     * 
     * @throws LoaderError When template does not exist.
     * @return string|null Processed content or null on error
     */
    private function applyTemplate(string $template, string $content): string
    {
        if (strpos($template, '.') === false) {
            $template = "$template.html.twig";
        }

        /** @var Twig */
        $twig = $this->grav['twig'];

        return $twig->twig()->render($template, [
            'content' => $content,
        ]);
    }
}
