WooCommerce To EDD Migration - Existing License Support
=======================================================

Upgrade Redirection Script for Existing WooCommerce API Keys to EDD Software License Keys.

## Nginx Redirection to prevent WooCommerce Request

Put a Redirection for WooCommerce Activation/Update Request

E.g.,

##### Activation Request

```
/?wc-api=am-software-api&email=udit.desai%40rtcamp.com&licence_key=wc_order_543298911059e_am_UuW9EajmR2DK&request=activation&product_id=rtMedia+PRO&instance=sxgnK4bkSzaN&platform=crm.me&software_version=2.5.7
```

should redirect to

```
/wp-content/plugins/woocommerce-to-easydigitaldownloads/index.php?wc-api=am-software-api&email=udit.desai%40rtcamp.com&licence_key=wc_order_543298911059e_am_UuW9EajmR2DK&request=activation&product_id=rtMedia+PRO&instance=sxgnK4bkSzaN&platform=crm.me&software_version=2.5.7
```

##### Status Check

```
/?wc-api=am-software-api&email=udit.desai%40rtcamp.com&licence_key=wc_order_543298911059e_am_UuW9EajmR2DK&request=status&product_id=rtMedia+PRO&instance=sxgnK4bkSzaN&platform=crm.me
```

should redirect to

```
/wp-content/plugins/woocommerce-to-easydigitaldownloads/index.php?wc-api=am-software-api&email=udit.desai%40rtcamp.com&licence_key=wc_order_543298911059e_am_UuW9EajmR2DK&request=status&product_id=rtMedia+PRO&instance=sxgnK4bkSzaN&platform=crm.me
```

##### Upgrade Check

```
/?wc-api=upgrade-api&request=pluginupdatecheck&plugin_name=rtmedia-pro%2Findex.php&version=2.5.7&product_id=rtMedia+PRO&api_key=wc_order_543298911059e_am_UuW9EajmR2DK&activation_email=udit.desai%40rtcamp.com&instance=sxgnK4bkSzaN&domain=crm.me&software_version=2.5.7&extra=
```

should redirect to

```
/wp-content/plugins/woocommerce-to-easydigitaldownloads/index.php?wc-api=upgrade-api&request=pluginupdatecheck&plugin_name=rtmedia-pro%2Findex.php&version=2.5.7&product_id=rtMedia+PRO&api_key=wc_order_543298911059e_am_UuW9EajmR2DK&activation_email=udit.desai%40rtcamp.com&instance=sxgnK4bkSzaN&domain=crm.me&software_version=2.5.7&extra=
```

##### Variables to take into consideration

`wc-api=am-software-api` or `wc-api=am-software-api` or `wc-api=upgrade-api`

`email=udit.desai@rtcamp.com` or `activation_email=udit.desai@rtcamp.com`

`licence_key=wc_order_543298911059e_am_UuW9EajmR2DK` or `api_key=wc_order_543298911059e_am_UuW9EajmR2DK`

`request=activation` or `request=status` or `request=pluginupdatecheck`

`plugin_name=rtmedia-pro/index.php`

`version=2.5.7` or `software_version=2.5.7`

`product_id=rtMedia PRO`

`instance=sxgnK4bkSzaN`

`domain=crm.me` or `platform=crm.me`


##### Nginx Confi

For This you may add following snippet in your site's nginx config file.

```
if ($arg_wc-api != "" ) {
    return 301 "http://YOURSITE.com/wp-content/plugins/woocommerce-to-easydigitaldownloads/index.php?$args";
}
```

What the above snippet does is that whenever nginx finds wc-api key in query string and if it's not empty then it will redirect the request to our script.

And our script should take over from there.