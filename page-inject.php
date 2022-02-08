<?php
/**
 * PageInject
 *
 * This plugin embeds other Grav pages from markdown URLs
 *
 * Licensed under MIT, see LICENSE.
 */

namespace Grav\Plugin;

use Grav\Common\Config\Config;
use Grav\Common\Data\Data;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Plugin\Admin\Admin;
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
            'registerNextGenEditorPlugin' => ['registerNextGenEditorPlugin', 0],
        ];
    }

    /**
     * Initialize configuration.
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->enable([
                'onAdminTaskExecute' => ['onAdminTaskExecute', 0],
            ]);
            return;
        }

        $this->enable([
            'onPageContentRaw' => ['onPageContentRaw', 0],
            'onPagesInitialized' => ['onPagesInitialized', 0],
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
        ]);
    }

    /**
     *
     * @param Event $e
     */
    public function onAdminTaskExecute(Event $e): void
    {
        if ($e['method'] === 'taskPageInjectData') {
            header('Content-type: application/json');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            $controller = $e['controller'];

            if (!$controller->authorizeTask('pageInject', ['admin.pages.read', 'admin.super'])) {
                http_response_code(401);
                $json_response = [
                    'status' => 'error',
                    'message' => '<i class="fa fa-warning"></i> Unable to get PageInject data',
                    'details' => 'Insufficient permissions for this user.'
                ];
                echo json_encode($json_response);
                exit;
            }

            error_reporting(1);
            set_time_limit(0);

            $json_response = $this->getPageInjectData();

            echo json_encode($json_response);
            exit;
        }
    }

    public function onShortcodeHandlers()
    {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__ . '/classes/shortcodes');
    }

    /**
     * Add content after page content was read into the system.
     *
     * @param Event $event An event object, when `onPageContentRaw` is fired.
     */
    public function onPageContentRaw(Event $event)
    {
        /** @var Page $page */
        $page = $event['page'];

        /** @var Config $config */
        $config = $this->mergeConfig($page);

        if ($config->get('enabled') && $config->get('active')) {
            // Get raw content and substitute all formulas by a unique token
            $raw = $page->getRawContent();

            // build an anonymous function to pass to `parseLinks()`
            $function = function ($matches) use (&$page, &$config) {

                $search = $matches[0];
                $type = $matches[1];
                $page_path = $matches[2];

                preg_match('#remote://(.*?)/(.*)#', $page_path, $remote_matches);

                if (isset($remote_matches[1]) && $remote_matches[2]) {
                    $remote_injections = $config->get('remote_injections', []);
                    $remote_url = $remote_injections[$remote_matches[1]] ?? null;
                    if ($remote_url) {
                        $url = $remote_url . '/?action=' . $type . '&path=/' . urlencode($remote_matches[2]);
                        return \Grav\Common\HTTP\Response::get($url);
                    }
                } else {
                    $replace = $this->getInjectedPageContent($type, $page_path, $page, $config->get('processed_content'));
                    if ($replace) {
                        return str_replace($search, $replace, $search);
                    }
                }
                return $search;
            };

            // set the parsed content back into as raw content
            $processed_content = $this->parseInjectLinks($raw, $function);
            $page->setRawContent($processed_content);
        }
    }

    public function onPagesInitialized()
    {
        $uri = $this->grav['uri'];
        $type = $uri->query('action');
        $path = $uri->query('path');
        // Handle remote calls
        if (in_array($type, ['page-inject','content-inject']) && isset($path)) {
           echo $this->getInjectedPageContent($type, $path);
           exit;

        }
    }

    public function registerNextGenEditorPlugin($event) {
        $plugins = $event['plugins'];

        // page-inject
        $plugins['css'][] = 'plugin://page-inject/nextgen-editor/plugins/page-inject/page-inject.css';
        $plugins['js'][] = 'plugin://page-inject/nextgen-editor/plugins/page-inject/page-inject.js';

        $event['plugins']  = $plugins;
        return $event;
    }

    public static function getInjectedPageContent($type, $path, $page = null, $processed_content = null): ?string
    {
        $pages = Grav::instance()['pages'];
        $page = $page ?? Grav::instance()['page'];

        if (is_null($processed_content)) {
            $header = new Data((array) $page->header());
            $processed_content = $header->get('page-inject.processed_content') ?? Grav::instance()['config']->get('plugins.page-inject.processed_content', true);
        }
        preg_match('/(.*)\?template=(.*)|(.*)/i', $path, $template_matches);

        $path = $template_matches[1] && $template_matches[2] ? $template_matches[1] : $path;
        $template = $template_matches[2];
        $replace = null;
        $page_path = Uri::convertUrl($page, $path, 'link', false, true);
        // Cleanup any current path (`./`) references
        $page_path = str_replace('/./', '/', $page_path);

        $inject = $pages->find($page_path);
        if ($inject instanceof PageInterface && $inject->published()) {
            // Force HTML to avoid issues with News Feeds
            $inject->templateFormat('html');
            if ($type == 'page-inject') {
                if ($template) {
                    $inject->template($template);
                }
                $inject->modularTwig(true);
                $replace = $inject->content();

            } else {
                if ($processed_content) {
                    $replace = $inject->content();
                } else {
                    $replace = $inject->rawMarkdown();
                }
            }
        }

        return $replace;
    }

    protected function getPageInjectData()
    {
        $request = $this->grav['request'];
        $data = $request->getParsedBody();
        $page_routes = $data['routes'] ?? [];
        $json = [];

        /** @var Pages $pages */
        $pages = Admin::enablePages();

        foreach ($page_routes as $route) {
            /** @var PageInterface */
            $page = $pages->find($route);

            if (!$page) {
                $data = [
                    'status' => 'Error',
                    'message' => 'Page not found',
                    'data' => []
                ];
            } else {
                $data = [
                    'status' => 'success',
                    'message' => 'Page found',
                    'data' => [
                        'title' => $page->title(),
                        'route' => $page->route(),
                        'modified' => $page->modified(),
                        'template' => $page->template(),
                    ]
                ];
            }

            $json['data'][] = $data;
            $json['available_templates'] = $pages->pageTypes();
        }

        return $json;
    }

    protected function parseInjectLinks($content, $function)
    {
        $regex = '/\[plugin:(content-inject|page-inject)\]\((.*)\)/i';
        return preg_replace_callback($regex, $function, $content);
    }

}
