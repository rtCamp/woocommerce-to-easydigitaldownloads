Woocommerce to EasyDigitalDownloads Migration/Exporter
=========================================================

**Note: This script is not finished yet.**


A script to migrate products, orders, payments and other stuff from WooCommerce to EasyDigitalDownloads

### Install

You MUST take backups. Also, run this in demo environment.

Put the files under `woocommerce-to-easydigitaldownloads` folder in WP Plugins directory (usually `/var/www/sitename/htdocs/wp-content/plugins/`)

Ususally, 

```
git clone https://github.com/rtCamp/woocommerce-to-easydigitaldownloads/ /path/to/wordpress/wp-content/plugins/
```

### Migrate/Export

From command-line interface (shell)

```
cd /path/to/wordpress/wp-content/plugins/
php migrate.php
```

### Reset EDD data

It might happen you migration doesn't produce output you are expecting.

You can reset EDD database in case of trouble: 

```
php reset.php
```
