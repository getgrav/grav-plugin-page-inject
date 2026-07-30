# Grav Page Inject Plugin

`Page Inject` is a powerful [Grav][grav] Plugin that lets you inject entire pages or page content into other pages using simple markdown-style syntax or alternatively a shortcode syntax (Shortcode Core plugin required).

# Installation

Installing the Page Inject plugin can be done in one of two ways. Our GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

## GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's Terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install page-inject

This will install the Page Inject plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/page-inject`.

## Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `page-inject`. You can find these files either on [GitHub](https://github.com/getgrav/grav-plugin-page-inject) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/page-inject

# Config Defaults

```
enabled: true
active: true
processed_content: false
remote_injections:
```

If you need to change any value, then the best process is to copy the [page-inject.yaml](page-inject.yaml) file into your `users/config/plugins/` folder (create it if it doesn't exist), and then modify there.  This will override the default settings.

The `active` option allows you to enable/disable the plugin site-wide, and then enable it on page via Page Config overrides. This is useful to optimize performance.

the `processed_content` option means the page is pre-rendered before being injected, so that relative image links and other path-sensitive content works correctly.  It defaults to `false`, which injects the raw markdown of the target page and processes it along with the rest of the current page. This is relevant for `content-inject` links **only**.

### Page Config

You can override the plugin options by adding overrides in the page header frontmatter:

```
page-inject:
    active: true
    processed_content: true
```

## Markdown-Style Usage (Legacy)

There are two ways to use this plugin in your markdown content:

1. **Page Injection**

    ```
    [plugin:page-inject](/route/to/page)
    ```

    This approach includes an entire page rendered with the associated template.  This works best for modular page content or content that uses a specific template that provides appropriate styling that is intended to be part of other pages.  You can also pass an optional template name and use that template to render the page (as long as you also provide the template in your theme):

    ```
    [plugin:page-inject](/route/to/page?template=custom-template)
    ```

2. **Content Injection**

    ```
    [plugin:content-inject](/route/to/page)
    ```

    Sometimes you just want the content of another page injected directly into your current page.  Use `content-inject` for this purpose.  The content is not rendered with the associated twig template, merely injected into the current page.

## Shortcode Usage (New)

The shortcode syntax follows the markdown-style syntax, and supports both `page-inject` and `content-inject` approaches. 

> NOTE: Shortcode functionality requires the `shortcode-core` plugin to be installed and enabled.

For example the Page Injection example from above would look like:

```markdown
[page-inject=/route/to/page /]
```

and the content inject version:

```markdown
[content-inject=/route/to/page /]
```

Alternatively, you can supply the path explicitly:

```markdown
[content-inject path="/route/to/page" /]
```

And for page-injection, you can specify a custom Twig template to render with:

```markdown
[page-inject path="/route/to/page" template="foo" /]
```

## Modular Pages

One of the most useful scenarios for using Page Inject plugin is for pulling modular pages into your content.  This is because it allows you to sprinkle structure, pre-rendered HTML into the middle of your content.  This allows you to create complex content layouts with a combination of straight markdown content, with blocks of more structured output.  For example imagine being able to display a "Contact Us" form in the middle of a case study.  Or perhaps a customer quotes module in the middle of a long article about your customer success stories.  A quick example of this might be:

```markdown
[plugin:page-inject](/modular/_callout)
```

The path will be the **Page route** for the page, and modular pages are typically distinguished by the `_` prefix. You would typically want to use a **page-inject** for this as you want the modular page pre-rendered with the associated Twig template.  You could still just display the content with **content-inject**. 

## Grav 2.0 Notes

Grav 2.0 changed two defaults that affect what injected content is allowed to do. Neither is a page-inject setting, but both show up first when you inject content, so they are worth knowing about. If content that worked in Grav 1.7 now renders as literal text after upgrading, it is almost always one of these two.

### Twig in page content is gated

Grav 2.0 added a site-wide master gate for Twig inside page content, and it is **off** on new installs. While it is off, `process: twig: true` in page frontmatter is ignored and any `{{ }}` / `{% %}` in your content renders as literal text. The Twig sandbox is a separate, later layer, so disabling the sandbox does not open this gate.

Enable it in **Configuration → Security → "Process Twig in Content"**, or in `user/config/security.yaml`:

```yaml
twig_content:
  process_enabled: true
```

Then make sure the page itself asks for Twig processing, either site-wide in `user/config/system.yaml` or per page in frontmatter:

```yaml
process:
  markdown: true
  twig: true
```

An explicit `pages.process.twig: false` in `user/config/system.yaml` (a common Grav 1.7 leftover) overrides the gate, so check there too.

Whenever the gate blocks a page, Grav writes a line to `logs/security.log`:

```
[TwigContentGate] blocked source=content route=/your/page
```

### Raw `<style>`, `<script>` and friends are escaped

Grav 2.0 enables the GFM "tagfilter" extension, which escapes a fixed denylist of raw HTML tags in markdown output: `script`, `style`, `iframe`, `title`, `textarea`, `xmp`, `noembed`, `noframes`, `plaintext`. A `<style>` block inside injected content therefore renders as visible text, while ordinary tags like `<ul>` and `<div>` are untouched.

Turn it off in **Configuration → System** ("GFM Tag Filter"), or in `user/config/system.yaml`:

```yaml
pages:
  markdown:
    gfm:
      tagfilter: false
```

It can also be set per page in frontmatter, using the same `markdown:` block.

Before reaching for this, consider moving the CSS into your theme's stylesheet instead. Style rules in page content are re-parsed on every render and the tagfilter exists to keep injected markup from introducing active tags.

### Where each setting has to go

Injected content is handed back to the page doing the injecting and parsed again as part of that page, which means the two settings live in different places:

| Setting | Applies on |
| --- | --- |
| `markdown.gfm.tagfilter` | the **injecting** page (or site-wide). Setting it only on the injected page is not enough, the injecting page re-escapes the tags |
| `process.twig` | the page that **owns the content**: the injected page when `processed_content` is `true`, the injecting page when it is `false` (the default) |

### Injecting a regular page with `page-inject`

`[plugin:page-inject]` renders the target page with its Twig template. If that target is a regular page using a full template such as `default.html.twig`, the template extends your theme's base and you will get an entire HTML document injected into the middle of your content. This is why page-inject is best paired with modular pages, or with an explicit template:

```markdown
[plugin:page-inject](/route/to/page?template=modular/text)
```

## Remote Injects

It is now possible to retrieve remote content from another Grav instance as long as both of the sites are running the latest version of the `page-inject` plugin.  First in the **client** Grav instance you need to define a remote connection to another Grav **server** in the plugin configuration.  For example:

```yaml
remote_injections:
  dev: https://dev.somehost.com/
  foo: https://foo.com/bar
```

This will then allow you to inject page content from one Grav instance to another using this syntax:

```markdown
[plugin:page-inject](remote://dev/home/modular/_callout)
```

and for the shortcode version:

```markdown
[page-inject path="remote://dev/home/modular/_callout" /]
```

Where the `remote://dev` protocol tells the plugin to retrieve the requested page from the `dev` injection configuration via the path `/home/modular/_callout`.

This is particularly useful for modular content that is already a snippet of content that is being reused on the **server**. This will retrieve the content, and because a modular page's content is pre-rendered with the appropriate Twig template, it will include all the HTML of the modular page.  If you request a regular page (non-modular), there will be no Twig and just plain HTML content will be sent.

[grav]: http://github.com/getgrav/grav
