# ShipStation Connect for Craft CMS 3.x and Commerce 2.x
A plugin for Craft Commerce that integrates with a ShipStation Custom Store.

_TODO: Update README_

## Requirements

This plugin requires Craft CMS 3.x and Commerce 2.x or later

## Installation

Install ShipStation Connect from the Plugin Store or with Composer

### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “ShipStation Connect.” Click on the “Install” button in its modal window.

### With Composer

Open your terminal (command line) and run the following commands:

```
# go to the project directory
cd /path/to/my-project

# tell Composer to load the plugin
composer require fostercommerce/shipstationconnect

# tell Craft to install the plugin
./craft install/plugin shipstationconnect
```

After installing with composer, go to the Craft control panel plugin settings page to install and configure the settings for the plugin.

## Configuration

### Order Statuses

Ensure your shipping statuses in Craft Commerce and ShipStation match. You edit each platform to use custom statuses and ShipStation can match multiple Craft statuses to a single ShipStation status, when needed.

### Connect Your Craft Store to ShipStation

Configure your connection in ShipStation following these instructions: [ShipStation "Custom Store" integration](https://help.shipstation.com/hc/en-us/articles/205928478-ShipStation-Custom-Store-Development-Guide#3a).

The "URL to Custom XML Page" is shown in the ShipStation Connect settings view in Craft.

## Template Examples

Coming soon

