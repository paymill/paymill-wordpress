-*- coding: utf-8, tab-width: 2 -*-

Changelog
=========

1.6.3
-----
* Common: Minor bugs fixed
* Common: Paymill logo now inside checkout form to give payment logos more space.

1.6.2
-----
* WooCommerce: order status for normal products (non-subscription) did not switched to processing after payment - fixed
* Common: Payment Type Buttons will appear more intelligent

1.6.1
-----
* WooCommerce: support for German Market added

1.6
-----
* Major Update!
* Common: payment channel SEPA added
* Common: update to new API PHP Wrapper
* Common: better theme support
* MagicMembers: released as final
* WooCommerce: many bugs fixed, support for webhooks added
* PayButton: completely rewritten

1.5.2
-----
* Common: "Fatal error: Call to a member function payment_complete() on a non-object" fixed

1.5.1
-----
* Common: Installation Manual updated
* Common: "Error Multiple Primary Key Defined" on Update fixed
* WooCommerce: Checkout form background color fixed
* WooCommerce: Error "notDigits: '-16045' must contain only digits" fixed
* Pay Button: Submit Button will be hidden on submit (and shown again by error) to avoid double orders.
* Magic Members: Beta available. The B in beta stands for bugs, so please don't use magic members in live environments.

1.5.0
-----
* Common: Payment processing totally rewritten making it more robust
* Common: Clicking on another area than submit button could submit form - fixed
* Common: Serbo-Croatic Translation added (thanks to Borisa Djuraskovic <borisad@webhostinghub.com>)
* WooCommerce: minor bugfixes
* WooCommerce: More control about visibility of payment icons in checkout form
* Shopplugin: Critical error fixed
* Shopplugin: reworked payment form
* Pay Button: New feature allows redirect to custom thank your URL
* Pay Button: New actions and hooks added for triggering custom functions or customizing order confirmation mail

1.4.4
-----
* WooCommerce: Rounding issue fixed

1.4.3
-----
* Common: Minor Fix

1.4.2
-----
* Common: Critical Fix when using 1.4.1, please update immediately to 1.4.2.

1.4.1
-----
* Common: Javascript-Handling on Checkout-Process optimized making it more robust
* Common: MASSIVELY improved Error Handling
* Common: Payment Form Design optimized
* Common: Changed Language Pack from en_GB to en_US as this is WordPress' default language
* Pay Button: Subscriptions-Select-Field can be hidden now, too
* Pay Button: Subscriptions Translation Issue fixed on payment form
* Pay Button: Action added: paymill_paybutton_order_complete, args: $order_id, $transaction, $_POST
* Pay Button: Now supports custom theme file on THEME_DIR/paymill/pay_button.php (replaces /paymill/lib/tpl/pay_button.php)
* Pay Button: Now allows hiding certain fields
* Pay Button: Now allows to prevent loading the default styles
* Magic Members: Pre Alpha version included (don't use it except you know what you do!)

1.4.0
-----
* Subscription support added for WooCommerce Subscriptions addon
* Allows hiding quantity field in pay button widget

1.3.2
-----
* Creditcard / ELV Switch Display issue fixed
* Translating issues fixed
* WooCommerce Bug on checkout page fixed
* Pay Button show/hide blocks links fixed

1.3.1
-----
* MasterCard Logo and Payment Bug fixed
* error reporting fixed (thanks to Jan R.)
* notifies with wrong payment data in Pay Button fixed
* credit card button visibility fixed

1.3
---
* several PHP notices fixed
* WooCommerce issue fixed (selection of other payment gateway didn't work on checkout page)
* Subscription Support for Pay Button

1.2.1
-----
* several PHP notices fixed
* incompatibility with Yootheme Cloud Theme (and maybe other themes) fixed
* unsaved Settings for Payment Gateway in WooCommerce fixed
* Payment Type Logo Selection added

1.2
---
* Shopplugin support added

1.1
---
* Pay Button added

1.0
---
* WooCommerce support added

== Upgrade Notice ==

1.5.2
-----
* Common: "Fatal error: Call to a member function payment_complete() on a non-object" fixed

1.5.1
-----
* Common: Installation Manual updated
* Common: "Error Multiple Primary Key Defined" on Update fixed
* WooCommerce: Checkout form background color fixed
* WooCommerce: Error "notDigits: '-16045' must contain only digits" fixed
* Pay Button: Submit Button will be hidden on submit (and shown again by error) to avoid double orders.
* Magic Members: Beta available. The B in beta stands for bugs, so please don't use magic members in live environments.

1.5.0
-----
* Common: Payment processing totally rewritten making it more robust
* Common: Clicking on another area than submit button could submit form - fixed
* Common: Serbo-Croatic Translation added (thanks to Borisa Djuraskovic <borisad@webhostinghub.com>)
* WooCommerce: minor bugfixes
* WooCommerce: More control about visibility of payment icons in checkout form
* Shopplugin: Critical error fixed
* Shopplugin: reworked payment form
* Pay Button: New feature allows redirect to custom thank your URL
* Pay Button: New actions and hooks added for triggering custom functions or customizing order confirmation mail

1.4.4
-----
* WooCommerce: Rounding issue fixed

1.4.3
-----
* Common: Minor Fix

1.4.2
-----
* Common: Critical Fix when using 1.4.1, please update immediately to 1.4.2.

1.4.1
-----
* Maintenance Update with a hugh load of minor improvements and bugfixes

1.4.0
-----
* WooCommerce Subscription Support (beta!), minor improvements

1.3.2
-----
* Several bugs fixed, shortcode support for Pay Button

1.3.1
-----
* Several bugs fixed

1.3
---
* Several bugs fixed, subscription support added

1.2.1
-----
* Several Bugs fixed and Payment Type Logo Selection added

1.2
---
* Shopplugin support added
