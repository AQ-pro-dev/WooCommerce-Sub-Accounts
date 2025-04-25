##  WooCommerce Sub-Accounts
Contributors: theprodeveloper789
Tags: woocommerce, subaccounts, sub-account, child account, pet account, family, checkout, qr code
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.2

A WooCommerce plugin that allows users to create sub-accounts (for pets, children, or family) during checkout and attach products to those subaccounts. Includes QR code generation and admin visibility.

## Description

**WooCommerce Sub-Accounts** lets your customers assign purchases to sub-accounts like "My Pet", "My Child", or "Other Family Member" during checkout. It supports:

- Selecting existing sub-accounts or creating new ones on the fly
- Assigning sub-accounts by type (pet, child, family)
- Unique user generation for new sub-accounts (with unique email/username)
- Automatic QR code generation per sub-account and product
- Sub-account visibility on:
  - Order details (admin + thank you page + email)
  - Customer "My Account" dashboard
  - Admin user profile

This plugin is fully localized in Spanish (frontend labels and notices).

## Features

* Create and manage sub-accounts linked to the main WooCommerce user
* Subaccount types: Pet, Child, Family
* Create new sub-accounts during checkout via modal form
* Auto-generated QR codes for sub-account/product combination
* View QR codes in order meta and admin
* See sub-account data in:
  * Admin Order Page
  * Thank You Page
  * Order Emails
  * My Account Page
* Admin section shows sub-accounts under main user profile

## Installation

1. Upload the plugin folder to `/wp-content/plugins/woocommerce-subaccounts`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce checkout to see subaccount options

## Frequently Asked Questions

= Are sub-accounts actual WordPress users? =
Yes. When created, they are stored as separate users with restricted metadata and linked to their main account.

= Can customers create sub-accounts later? =
Currently, creation is only supported at checkout. You can extend it using the plugin hooks.

= What happens if the sub-account email already exists? =
The plugin automatically generates unique emails using the main email + suffix pattern.

## Changelog ==

= 1.2.0 =
* Added QR code SVG export + AJAX saving
* Admin: Sub-account visibility on user profile
* Thank You page and My Account page improvements
* Spanish labels for all frontend texts
* QR post type association logic


## Author

Developed by [The Pro Developer](mailto:theprodeveloper789@gmail.com)