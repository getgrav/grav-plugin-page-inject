# v2.1.6
## 03/22/2023

1. [](#bugfix)
   * Fixed NextGen integration where cog icon settings would not work properly
   
# v2.1.5
## 03/28/2022

2. [](#bugfix)
   * Fixed an issue with relative paths for page-inject when processing

# v2.1.4
## 02/08/2022

2. [](#bugfix)
   * Fixed bad configuration reference for `processed_content`
   * Set `processed_content` to false by default - so `content-inject` doesn't try to render with twig

# v2.1.3
## 02/08/2022

1. [](#improved)
   * Shortcodes should use `rawHanders()` to support shortcodes/markdown

# v2.1.2
## 02/08/2022

1. [](#improved)
   * PHPStan fixes
   * Support `./` in page paths

# v2.1.1
## 12/09/2021

1. [](#improved)
   * Added a modular example in the README.md
2. [](#bugfix)
   * Allow null returned by `getInjectedPageContent()`

# v2.1.0
## 12/06/2021

1. [](#new)
   * Added `[page-inject]` and `[content-inject]` shortcodes as an alternative syntax
   * Added support for remote `page-inject` and `content-inject` variations
2. [](#improved)
   * Refactored code to work with both markdown and shortcode syntax
   * Use composer-based autoloader

# v2.0.0
## 12/03/2021

1. [](#new)
   * Added support for new remote injects.

# v1.4.5
## 04/27/2021

1. [](#improved)
   * NextGen Editor: Added toolbar icon
   * NextGen Editor: Added support for multiple editor instances
1. [](#bugfix)
   * Fixed permissions to only require `pages.read` for `taskPageInject` [premium-issues#43](https://github.com/getgrav/grav-premium-issues/issues/43)

# v1.4.4
## 01/29/2021

1. [](#bugfix)
   * NextGen Editor: Fixed Page Inject UI links missing the base_root [getgrav/grav-premium-issues#30](https://github.com/getgrav/grav-premium-issues/issues/30)
   * NextGen Editor: Moved list of available templates to input text to support partials and any twig template [getgrav/grav-premium-issues#24](https://github.com/getgrav/grav-premium-issues/issues/24)

# v1.4.3
## 01/15/2021

1. [](#improved)
   * NextGen Editor: Updated upcast/downcast syntax to support latest version

# v1.4.2
## 12/20/2020

1. [](#bugfix)
    * Fixed `undefined` value when inserting a new Page-Inject shortcode, preventing Page picker to load

# v1.4.1
## 12/18/2020

1. [](#improved)
    * NextGen Editor: Properly restore the initial stored path when loading the Page Picker

# v1.4.0
## 12/02/2020

1. [](#new)
    * NEW support for NextGen Editor
    * Added a new `taskPageInjectData` to be used by NextGen Editor integration
1. [](#bugfix)
    * Added missing admin nonce

# v1.3.1
## 04/15/2019

1. [](#bugfix)
    * Fixed issue with Feed plugin and Page-Inject by forcing template to `html` [feed#42](https://github.com/getgrav/grav-plugin-feed/issues/42)

# v1.3.0
## 12/08/2017

1. [](#new)
    * Added multi-lang support to Page Inject plugin [#10](https://github.com/getgrav/grav-plugin-page-inject/issues/10)

# v1.2.0
## 10/11/2016

1. [](#improved)
    * Support Grav-style link route resolution (e.g. `../your-route`) [#5](https://github.com/getgrav/grav-plugin-page-inject/issues/5)
1. [](#bugfix)
    * Fixed issue with `page-inject` processing Twig twice [#7](https://github.com/getgrav/grav-plugin-page-inject/issues/7)

# v1.1.1
## 10/21/2015

1. [](#new)
    * Added `active` config option to enable/disable site-wide
1. [](#bugfix)
    * Fixed issue with plugin not processing reliably with cache-enabled

# v1.1.0
## 08/25/2015

1. [](#improved)
    * Added blueprints for Grav Admin plugin

# v1.0.0
## 06/18/2015

1. [](#new)
    * ChangeLog started...
