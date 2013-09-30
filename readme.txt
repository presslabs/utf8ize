=== Utf8ize ===
Contributors: PressLabs
Donate link: http://www.presslabs.com/
Tags: utf8ize, presslabs, database, convert, sql, alter, table
Requires at least: 3.5.1
Tested up to: 3.5
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Convert all your database character sets to utf8, trying to follow Codex guides. 

== Description ==
Convert all your database character sets to utf8, trying to follow <strong><a href="http://codex.wordpress.org/Converting_Database_Character_Sets">Codex guides</a></strong>. You should use this if you are experiencing double utf8 encoding. You can check this by setting DB_CHARSET in your wp-config.php file to latin1 or commenting the line; if your characters look good now on your site than you are probably suffering from this issue.

It works by scanning all you tables and columns and generating a list of SQL statements which allow you to convert to convert your content to uft8.

!!! CAUTION !!!
The execution time of the next SQL statements may take a lot of time(even days), related to dimensions of your database and the amount of the content.

== Installation ==

= Installation =
1. Upload `utf8ize.zip` to the `/wp-content/plugins/` directory;
2. Extract the `utf8ize.zip` archive into the `/wp-content/plugins/` directory;
3. Activate the plugin through the 'Plugins' menu in WordPress.

= Usage =
Use your plugin from the `Tools->Utf8ize` page;

== Frequently Asked Questions ==

= Why should I use this plugin? =
You should use this if you are experiencing double utf8 encoding.

== Changelog ==

= 1.0 =
Start version on WP.

