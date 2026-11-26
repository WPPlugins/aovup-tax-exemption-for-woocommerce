=== AovUp Tax Exemption for WooCommerce ===
Contributors: themealien
Tags: tax exempt, tax exemption, vat exempt, b2b, wholesale
Requires at least: 6.5
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stop charging tax to exempt customers: tax-exempt roles, tax-exempt customer accounts, and an "I am tax exempt" claim with a number at checkout.

== Description ==

Schools, charities, resellers and many B2B buyers don't pay sales tax or VAT. AovUp Tax Exemption for WooCommerce removes tax for them automatically, using WooCommerce's own tax-exempt handling, so cart, checkout and orders stay consistent.

= Free features =

* **Tax-exempt user roles.** Pick the roles that never pay tax. The plugin adds a "Tax exempt customer" role you can give to customers, and you can also use roles from other plugins (for example a wholesale role).
* **Tax-exempt customer accounts.** Mark a single customer as tax exempt on their user profile, with their exemption number and an optional expiry date. After that date tax is charged again.
* **"I am tax exempt" at checkout.** Customers tick a box and enter their tax exemption number (required, optional or not asked). Tax disappears from the order total straight away. Works in the classic checkout and in the Checkout block.
* **Limit the claim to logged-in customers** if guests shouldn't be able to claim.
* **Exemption details on every order.** The order screen, order emails and the customer's order view show why no tax was charged and the exemption number.
* **Prices without tax for exempt customers.** Exempt customers see the prices they will actually pay.
* **Orders created in the admin** for an exempt customer are calculated without tax.
* **Remember the number.** Logged-in customers get their last exemption number filled in next time.
* A "Tax exempt" column in the Users list shows who is exempt and why.

Everything above is free, without limits or time restrictions, and the plugin doesn't contact any outside service.

= The paid AovUp Tax Exemption =

Need finer control? The paid [AovUp Tax Exemption](https://aovup.com/plugins/tax-exempt/) adds:

* Exemption rules by country, product, category, user or role
* Tax exemption through coupon codes
* Prices with or without tax, and custom price suffixes, per customer group
* Tax certificate uploads and a form builder for exemption fields

When the paid plugin is active it takes over and this free plugin pauses itself, so you can switch without conflicts.

== Installation ==

1. Install and activate WooCommerce, and turn on taxes (WooCommerce > Settings > General > Enable tax rates and calculations).
2. Install AovUp Tax Exemption for WooCommerce from Plugins > Add New, or upload the zip, then activate it.
3. Go to WooCommerce > Settings > Tax exemption to pick tax-exempt roles and switch on the checkout claim.
4. To exempt one customer, open Users > Edit user and use the "Tax exemption" section.

== Frequently Asked Questions ==

= Does it work with the Checkout block? =

Yes. The checkout claim works in the classic checkout and in the Checkout block, and role and account exemptions work everywhere.

= How is tax removed? =

The plugin switches on WooCommerce's own "VAT exempt" flag for the customer. WooCommerce then calculates the cart and order without tax, including shipping tax, and stores the exemption on the order.

= My prices include tax. What does an exempt customer pay? =

WooCommerce removes the tax from the price, so an exempt customer pays the price without tax. The "Price display" setting shows them that price in the shop too.

= Can customers abuse the checkout claim? =

You decide who can claim (everyone or logged-in customers only) and whether a tax exemption number is required. Every claimed order shows the number on the order screen so you can check it.

= Does it send data anywhere? =

No. The plugin doesn't call any external service.

= What happens if I install the paid AovUp Tax Exemption? =

The paid plugin takes over and this plugin pauses itself, with a notice that you can deactivate it.

== Screenshots ==

1. Settings: tax-exempt roles, price display and the checkout claim.
2. "I am tax exempt" at checkout: the tax line disappears from the order total.
3. The exemption on the order screen.
4. Marking a single customer as tax exempt, with an optional expiry date.

== Changelog ==

= 1.0.0 =
* First release: tax-exempt roles, tax-exempt customer accounts with expiry, checkout claim with exemption number (classic checkout and Checkout block), exemption details on orders and emails, prices without tax for exempt customers.

== Upgrade Notice ==

= 1.0.0 =
First release.
