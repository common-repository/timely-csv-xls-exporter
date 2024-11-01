=== Plugin Name ===
Contributors: (Virgial)
Tags: export,csv,xls,excell,email export, attachment,cron script,cron,crontab,scedule,scheduled export
Requires at least: 4.5.0
Donate link:https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=TYG56SLWNG42N
Tested up to: 4.8
Stable tag: 1.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Export standard and custom post type to csv or excell format. You can export right away or send an scheduled e-mail with attachment.

== Description ==

With this plugin you can export posts,pages and custom post types to csv or excell with the standard fields like post_content, post_title, post_name, but also the corresponding metadata fields. You can export timebased (cron script) e-mails with attachment. The attachment can contain all the data or the data between the selected period. So send your customer a weekly, monthly or even a yearly export is as simple as that.
You can make use of hooks to reformat your data. This is handy when you want to export fields for other plugins like contact form 7.



Want regular updates? Feel free to support me with a small donation :-)


== Installation ==

1. Upload `timely-cdv-xls-exporter.zip` via the plugins menu
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the settingspage to enable the timely based exports


== Frequently Asked Questions ==

= Is it active right away? =

Yes, after activating you can go to the settings page and setup your data.
= Can I use this plugin for multisite? =
This plugin is not tested on multisite environments yet.
= Which filter hooks are available? =
add_filter('fatxce_mail_html','your_function') -> hook for customizing the html in the mail.
add_filter('fatxce_filename','your_function') -> hook for changing the filename of the attachment.
add_filter('fa_timely_fields','your_function') -> hook for adding your own custom fields or reformat them.
add_filter(''fatxce_before_format'','your_function') -> hook for customize the data before it is formatted for the export.
add_filter(''fatxce_after_format'','your_function') -> hook for customizing the data after the data is reformatted for export.


== Screenshots ==

1. This is your view of the settingspage.

== Changelog ==

= 1.1.1 =
* Added post type attachment

= 1.1 =
* Fixed some bugs
* Added PHPExcell library


= 1.0 =
* It all starts here.

== Upgrade Notice ==
None.