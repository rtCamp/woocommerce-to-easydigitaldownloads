Woocommerce to EasyDigitalDownloads Migration/Exporter
=========================================================

**Note: This script is not a standard WordPress plugin. Running via PHP CLI is recommended**


A script to migrate products, orders, payments and other stuff from WooCommerce to EasyDigitalDownloads

### Install

You MUST take backups. Also, run this in demo environment.

Put the files under `woocommerce-to-easydigitaldownloads` folder in WP Plugins directory (usually `/var/www/sitename/htdocs/wp-content/plugins/`)

Usually, 

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

**NOTE :**
If you are using WooCommerce API Manager and wish to migrate to EDD Software Add-on, check [software.md](https://github.com/rtCamp/woocommerce-to-easydigitaldownloads/blob/master/software.md) for further instructions


### Does this interest you?

<a href="https://rtcamp.com/"><img src="https://rtcamp.com/wp-content/uploads/2019/04/github-banner@2x.png" alt="Join us at rtCamp, we specialize in providing high performance enterprise WordPress solutions"></a>
