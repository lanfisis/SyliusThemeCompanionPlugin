[![Banner of Sylius Theme Companion plugin](docs/images/banner.jpg)](https://monsieurbiz.com/agence-web-experte-sylius)

<h1 align="center">Theme Companion for Sylius</h1>

[![Theme Companion  Plugin license](https://img.shields.io/github/license/monsieurbiz/SyliusThemeCompanionPlugin?public)](https://github.com/monsieurbiz/SyliusThemeCompanionPlugin/blob/master/LICENSE)
[![Tests](https://github.com/monsieurbiz/SyliusThemeCompanionPlugin/actions/workflows/tests.yaml/badge.svg)](https://github.com/monsieurbiz/SyliusThemeCompanionPlugin/actions/workflows/tests.yaml)
[![Security](https://github.com/monsieurbiz/SyliusThemeCompanionPlugin/actions/workflows/security.yaml/badge.svg)](https://github.com/monsieurbiz/SyliusThemeCompanionPlugin/actions/workflows/security.yaml)
[![Flex Recipe](https://github.com/monsieurbiz/SyliusThemeCompanionPlugin/actions/workflows/recipe.yaml/badge.svg)](https://github.com/monsieurbiz/SyliusThemeCompanionPlugin/actions/workflows/recipe.yaml)

## Compatibility

| Sylius Version | PHP Version |
|----------------|-------------|
| 2.0            | 8.2 - 8.3   |

ℹ️ For Sylius 1.x, see our [1.x branch](https://github.com/monsieurbiz/SyliusMediaManagerPlugin/tree/1.x) and all 1.x releases.

## Installation

If you want to use our recipes, you can configure your composer.json by running:

```bash
composer config --no-plugins --json extra.symfony.endpoint '["https://api.github.com/repos/monsieurbiz/symfony-recipes/contents/index.json?ref=flex/master","flex://defaults"]'
```

```bash
composer require monsieurbiz/sylius-theme-companion-plugin
```

<details><summary>For the installation without flex, follow these additional steps</summary>
<p>

Change your `config/bundles.php` file to add this line for the plugin declaration:
```php
<?php

return [
    //..
    MonsieurBiz\SyliusSettingsPlugin\MonsieurBizSyliusThemeCompanionPlugin::class => ['all' => true],
];  
```

Then import the routes in `config/routes/monsieurbiz_sylius_theme_companion_plugin.yaml` :

```yaml
when@dev:
    monsieurbiz_theme_companion:
        resource: '@MonsieurBizSyliusThemeCompanionPlugin/config/routing/theme.yaml'
        prefix: /_theme
```
</p>
</details>  

## How it's work?

This plugin is a companion for your Sylius themes. 
It works like a Swiss army knife with a battery of tools included.
     
All the "magic" comes from a new entry in the `composer.json` file of your theme:

```json
{
  "name": "monsieurbiz/sylius-foo-theme",
  "type": "sylius-theme",
  "extra": {
    "sylius-theme": {
      "title": "Sylius Foo Theme",
      "need_companion": true, // This is the magic line
    }
  }
}
```

Using at least this configuration entry, and your theme will appear in the list of the available themes
in the channel configuration even if this is a packaged theme, outside the `themes` folder.


### Naming convention

The name of your theme will be used in two main different places in the code:
* As an Asset Mapper prefix (e.g.: `<link rel="stylesheet" href="{{ asset('@Sylius2LocalNakedTheme/css/style.css') }}">`)
* As a parameter name (e.g.: `%mbiz_theme_companion.sylius2_local_original_theme.assets_path%/style/main.css`)

If the name of your theme is `monsieurbiz/sylius-foo-theme` in the `composer.json`file:

|                     | Rule                       | Without custom prefix        | With custom prefix `My-Foo Theme` |
|---------------------|----------------------------|------------------------------|-----------------------------------|
| Asset Mapper prefix | Camel case prefixed with @ | @MonsieurbizSyliusFooTheme   | @MyFooTheme                       |
| Parameter name      | Snake case without @       | monsieurbiz_sylius_foo_theme | my_foo_theme                      |
