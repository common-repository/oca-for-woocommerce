=== OCA para WooCommerce ===
Contributors: CRPlugins
Tags: oca, envios, oca argentina, envios con oca, oca woocommerce, oca para woocommerce, etiquetas oca, cotizaciones oca, oca envios, envios argentina, medios de envio, medios de envio argentina
Requires at least: 4.8
Tested up to: 6.6.2
Requires PHP: 7.1
Stable tag: 3.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 
Conectá tu tienda con OCA y cotizá tus pedidos en tiempo real, procesá pedidos, imprimí etiquetas y mucho mas!

== Description ==

Con este plugin podrás conectar tu tienda con los servicios de OCA.

Podrás cotizar, procesar pedidos, imprimir etiquetas, rastrear los pedidos, cancelar pedidos, y mucho más.

Este plugin es pago y se maneja bajo una modalidad de subscripción mensual, conectandose a un servicio externo (3rd party) de crplugins.com.ar, no tomamos ni almacenamos ninguna información privada de nuestros usuarios. Mas información en nuestro sitio https://crplugins.com.ar/

== Installation ==

1. Instala el plugin desde el repositorio de plugins de WordPress
2. Activa el plugin en la pantalla 'Plugins' de tu sitio WordPress

== Frequently Asked Questions ==

= ¿Que modalidades u operativas de envío puedo usar con este plugin? =

Todas! Del tipo que sea, con o sin seguro y la cantidad que necesites.

= ¿Que tipo de etiquetas soporta el plugin? =

Podés ver e imprimir etiquetas PDF A4 y 10x15, asi como tambien etiquetas ZPL.

= Necesito imprimir varias etiquetas al mismo tiempo, ¿puedo hacerlo? =

Por supuesto, tenemos esa posibilidad incluida

= ¿Por qué usaría este plugin en lugar de otro? =

Ofrecemos seguridad, confiabilidad, un desarrollo constante y sobre todo, un soporte que escucha las necesidades de los vendedores como vos.

= ¿Donde puedo ver los ToS? =

Acá https://crplugins.com.ar/terms-of-use/

== Screenshots ==

1. Cotizaciones en tiempo real
2. Cotizaciones en tiempo real
3. Detalles de ordenes
4. Etiquetas en PDF
5. Mail personalizable con número de rastreo
6. Configuración

== Changelog ==

= 3.2.1 =
* Fixed error in php <8

= 3.2.0 =
* Modified UI for orders metabox
* Added option to hide shipping cost from customer when setting OCA as the shipping method for an order 

= 3.1.1 =
* Fixed error with empty settings

= 3.1.0 =
* Added extra options for oca's pickup
* Added extra description to some settings

= 3.0.8 =
* Fix emails not being sent in some instances

= 3.0.7 =
* Fixed error on status page with certain settings
* Fixed error when the shipping selector was disabled

= 3.0.6 =
* Improved settings UX
* Improved block checkout UX
* Fixed error with complex domain sites

= 3.0.5 =
* Improved settings UX
* Fixed error when saving settings

= 3.0.4 =
* Fixed error when saving settings
* Improved license status message

= 3.0.3 =
* Fixed error when saving settings
* Fixed error with status page

= 3.0.2 =
* Fixed php error when processing orders

= 3.0.1 =
* Fixed php error on PHP <8

= 3.0.0 =
* New settings form
* New documentation available
* Improved loading speed and UX on checkout and cart
* Added option for customizing tracking mail template
* Added option for displaying branch on shipping method's name
* Added option for allow resending mails
* Added compatibility with checkout and cart blocks

= 2.9.0 =
* Added possibility to use OCA for orders that were not placed with it.
* Fix error in settings page

= 2.8.3 =
* Optimized loading speed of the settings page

= 2.8.2 =
* Fixed reports stopped working after WooCommerce update
* Added filter wc_oca_customer_note_message

= 2.8.1 =
* Fix link not working in plugin settings

= 2.8.0 =
* Added api health status check
* Fixed bug in health page not counting invalid products correctly
* Fixed filters names
* Improved plugin security

= 2.7.4 =
* Improved speed of health page
* Added filters before quoting

= 2.7.3 =
* Improved speed of health page

= 2.7.2 =
* Added health status page
* Fixed wrong filter name for shipping branch rates
* Fixed translation

= 2.7.1 =
* Added ability to show price in the checkout

= 2.7.0 =
* Added delivery days
* Added option for extra delivery days
* Fixed some translations

= 2.6.4 =
* Fix for viewing multiple pdf lables

= 2.6.3 =
* Fix for cached zip downloads

= 2.6.2 =
* Bumped WooCommerce tested version
* Dev changes

= 2.6.1 =
* Improved address recognition to prevent trimming later on shipping label
* Added option to change interval of label delete cron

= 2.6.0 =
* Translated plugin title and Description
* Added support for WooCommerce High-Performance Order Storage
* Fixed bug when shipping labels are not available

= 2.5.0 =
* Added possibility to set custom price for shipping methods

= 2.4.2 =
* Fix process order now button not appearing in some scenarios

= 2.4.1 =
* Improved address parsing detection

= 2.4.0 =
* Added option to make shipping free when a free shipping coupon is added to cart
* Added more translations

= 2.3.1 =
* Fixed missing translation

= 2.3.0 =
* Fixed weight calculation when grouping products in a single package
* Fixed products name when grouping products in a single package
* Added extra verification for correct format of fields in settings
* Removed unnecesary alert when reprocessing order

= 2.2.0 =
* Added option to round shipping costs

= 2.1.1 =
* Improved branches selector compatibility
* Small fix in translation

= 2.1.0 =
* Added support for shipping labels in 10x15cm

= 2.0.3 =
* Fixed small warning with php error
* Added user agent to requests

= 2.0.2 =
* Fixed compatibility with older PHP versions

= 2.0.1 =
* Fixed column order translation
* Fixed error in orders with no shipping method 
* Added possibility to change order packages number before re-processing order
* Improved branches selector compatibility in checkout
* Improved branches selector compatibility in cart

= 2.0.0 =
* Added feature for bulk pdf and zpl labels
* Added warning when re processing order and the shipping cost changes
* Fixed translations
* Improved settings UX
* Dev cleanup

= 1.4.1 =
* Fixed bug in logs reporting

= 1.4.0 =
* Added shipping label ZPL support

= 1.3.6 =
* Added option for extra shipping label when free shipping is enabled
* Removed key cache status from settings

= 1.3.5 =
* Bumped WP version support
* Fix bug with postcodes with letters in them

= 1.3.4 =
* Fix incorrect price flow when final price was modified and free shipping was also enabled
* Improved reports logging

= 1.3.3 =
* Added option to alter the final price

= 1.3.2 =
* Fixed auto branch selection on checkout and product page
* Bumped WC version tested

= 1.3.1 =
* Fixed missing file

= 1.3.0 =
* Now the plugin auto selects the first available shipping branch in the checkout to avoid price 0 when displayed in product page
* Added the ability to send woocommerce logs with just a button
* Bumped WC version tested

= 1.2.6 =
* Fix an issue with some themes where customers could not select a shipping branch

= 1.2.5 =
* Fix small syntax error
* Reduced memory usage
* Added support for WooCommerce Products Add-ons and other plugins

= 1.2.4 =
* Small security changes
* Development changes
* Now changing the order packages adds an order note

= 1.2.3 =
* Add customer's company name as fallback
* Prevent data malformation when special characters are used

= 1.2.2 =
* Fix bug with virtual products in cart

= 1.2.1 =
* Removed credentials validator button due to API being unstable
* Added a couple of actions and filters

= 1.2.0 =
* User is now able to process an order after it was canceled

= 1.1.1 =
* Fix error when placing an order with virtual items

= 1.1.0 =
* Remove COD
* Add option to disable insurance

= 1.0.4 =
* Fix translation
* Added option to process only one package

= 1.0.3 =
* Fix in code error when activating plugin

= 1.0.2 =
* Fix in code error when activating plugin

= 1.0.1 =
* Small fix when settings are not set
* Translation typo

= 1.0 =
First release

== Upgrade Notice ==

= 1.0 =
First release