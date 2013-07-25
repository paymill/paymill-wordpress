=== Paymill for WordPress ===
Contributors: Matthias Reuter
Donate link: 
Tags: paymill, creditcard, elv, payment, woocommerce, paybutton, ecommerce
Requires at least: 3.5
Tested up to: 3.5.2
Stable tag: 1.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

With Paymill you are able to provide credit card based payments for your customers. German users can use ELV payment, too.

== Description ==

This plugin currently allows:

* Payment Gateway for WooCommerce
* Payment Gateway for ShopPlugin
* Pay Button

Features in Development:

* Payment Gateway for Magic Members

== Installation ==

There is a manual included in English and German as PDF. But in short:

1. Upload `paymill`-directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Register a free account on https://www.paymill.com/
4. Insert TEST Api Keys for testing purposes in plugin settings
5. If you are happy how the plugin works, enable your live account on https://www.paymill.com/ - this could take a couple of days.
6. After your paymill account has been activated for LIVE mode, switch your account to live and replace your TEST API Keys with your LIVE API Keys in plugin settings.

== Frequently asked questions ==

= Is this plugin for free? =

This plugin is for free and licensed to GPL. It's open source following the GPL policy.

= Does this plugin calls to another server? =
Yes. As Pamill is a payment service provider, it is absolutely required to call home to make sure that the payments are valid. We are talking about three different reasons for calling home:
* 1. Paymill Javascript Bridge makes sure that payment data is correct and creates a payment token delivered to your server after checkout. This avoids delivering payment data to your server, what is -in most cases- absolutely prohibited by all common credit card providers.
* 2. Paymill PHP Bridge finishes the order and delivers the generated token to the Paymill server.
* 3. (planned) For security purposes we will implement a feature which delivers WordPress version number and Paymill Plugin version number upon payment process. This will give us the ability to warn paymill merchants who are using a very outdated WordPress version or about known security holes in specific version when still using them.

= Are there any fees for payments? =

Merchants must create an account on https://www.paymill.com/ to use the payment service. The TEST mode is for free, but there are "per payment" fees in LIVE mode, see https://www.paymill.com/en-gb/pricing/

= Do customers need to create an account for payment? =

No. Paymill allows payments without annoying your customers creating an account. They'll just fill out the payment fields on your checkout-page - that's all.

= Does this plugin redirects the users to Paymill for payment? =

No. Paymill allows payment directly through your website without any extra redirects etc.

= Does this plugin supports 3D secure? =

Yes. Please note that you can test 3D secure feature on LIVE mode only. The TEST mode always gives a positive feedback on 3D secure.

= Which Credit Cards are supported? =

Depending on your country and account status, the following credit card provider are currently supported: VISA, MasterCard, American Express, Diners Club, UnionPay and JCB

= What is ELV and why it's supported? =

ELV is a German banking service and stands for "Elektronisches Lastschriftverfahren". This is a very convenience payment solution for German users, as credit cards are not very common in Germany compared to e.g. USA.

== Screenshots ==

1. Common Settings
2. Payment Form

== Changelog ==

= 1.3 =

* several PHP notices fixed
* WooCommerce issue fixed (selection of other payment gateway didn't work on checkout page)
* Subscription Support for Pay Button

= 1.2.1 =

* several PHP notices fixed
* incompatibility with Yootheme Cloud Theme (and maybe other themes) fixed
* unsaved Settings for Payment Gateway in WooCommerce fixed
* Payment Type Logo Selection added

= 1.2 =
Shopplugin support added

= 1.1 =
Pay Button added

= 1.0 =
WooCommerce support added

== Upgrade Notice ==

= 1.3 =
Several bugs fixed, subscription support added

= 1.2.1 =
Several Bugs fixed and Payment Type Logo Selection added

= 1.2 =
Shopplugin support added

== Missing a feature? ==

Please use the plugin support forum here on WordPress.org. We will add your wished - if realizable - on our todo list. Please note that we can not give any time estimate for that list or any feature request.

= Paid Services =
Nevertheless, feel free to hire the plugin author Matthias Reuter <info@straightvisions.com> if you need to:

* get a customization
* get a feature rapidly / on time
* get a custom WordPress plugin developed to exactly fit your needs.