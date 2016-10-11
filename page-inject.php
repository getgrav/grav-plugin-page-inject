<?php
/**
 * PageInject
 *
 * This plugin embeds other Grav pages from markdown URLs
 *
 * Licensed under MIT, see LICENSE.
 */

namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Uri;
use RocketTheme\Toolbox\Event\Event;

class PageInjectPlugin extends Plugin
{
    /**
     * Return a list of subscribed events.
     *
     * @return array    The list of events of the plugin of the form
     *                      'name' => ['method_name', priority].
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    /**
     * Initialize configuration.
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        $this->enable([
            'onPageContentRaw' => ['onPageContentRaw', 0],
        ]);
    }

    /**
     * Add content after page content was read into the system.
     *
     * @param  Event  $event An event object, when `onPageContentRaw` is fired.
     */
    public function onPageContentRaw(Event $event)
    {
        /** @var Page $page */
        $page = $event['page'];

        $config = $this->mergeConfig($page);

        $twig = $this->grav['twig'];

        if ($config->get('enabled') && $config->get('active')) {
            // Get raw content and substitute all formulas by a unique token
            $raw = $page->getRawContent();

            // build an anonymous function to pass to `parseLinks()`
            $function = function ($matches) use (&$page, &$twig, &$config) {

                $search = $matches[0];
                $type = $matches[1];
                $page_path = $matches[3] ?: $matches[2];
                $template = $matches[4];

                // Process Link a little manually
                $grav = Grav::instance();
                $language = $grav['language'];
                $language_append = '';
                if ($type == 'link' && $language->enabled()) {
                    $language_append = $language->getLanguageURLPrefix();
                }
                $base              = $grav['base_url_relative'];
                $base_url          = rtrim($base . $grav['pages']->base(), '/') . $language_append;
                $page_path = str_replace($base_url, '', Uri::convertUrl($page, $page_path));
//                $page_path = Uri::convertUrl($page, $page_path, 'link', false, true);  // for next release

                $inject = $page->find($page_path);
                if ($inject) {
                    if ($type == 'page-inject') {
                        if ($template) {
                            $inject->template($template);
                        }
                        $inject->modularTwig(true);
                        $replace = $inject->content();

                    } else {
                        if ($config->get('processed_content')) {
                            $replace = $inject->content();
                        } else {
                            $replace = $inject->rawMarkdown();
                        }
                    }

                } else {
                    // replace with what you started with
                    $replace = $matches[0];
                }

                // do the replacement
                return str_replace($search, $replace, $search);
            };

            // set the parsed content back into as raw content
            $page->setRawContent($this->parseInjectLinks($raw, $function));
        }
    }

    protected function parseInjectLinks($content, $function)
    {
        $regex = '/\[plugin:(content-inject|page-inject)\]\(((.*)\?template=(.*)|(.*))\)/i';
        return preg_replace_callback($regex, $function, $content);
    }
}
