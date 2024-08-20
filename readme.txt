=== UptimeMonster Site Monitor ===
Contributors: uptimemonster, niamul, mhamudul_hk, shuvo586
Tags: activity monitor, health check, issue tracker, uptime monitoring, error logging
Requires at least: 5.2
Tested up to: 6.6
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html#license-text

Monitor all activities and error logs of your WordPress site with UptimeMonster. Effortlessly simplify website management.

== Description ==
Track and analyze all activities on your WordPress website with the powerful and flexible UptimeMonster Site Monitor plugin.
This plugin integrates seamlessly with UptimeMonster, an external web application that monitors your website's uptime, downtime, security, and other essential functions.

UptimeMonster Site Monitor operates 24/7, allowing you to identify and address issues before they impact users globally.
Activate the plugin with and connect with the dashboard and monitor every aspect of your WordPress website.
Choose from different monitoring interval mode, and receive a detailed report in a centralized dashboard for all of your websites.

https://www.youtube.com/watch?v=UTT14RCx84k&ab_channel=UptimeMonster&sub_confirmation=1

### Monitor WordPress Website Activities

Explore detailed information about any action by anyone on your WordPress site through the activity log.
Monitor post changes, user actions, plugin and theme activation/deactivation, WordPress cron jobs, etc.

- WordPress core updates, cron job logs.
- Pages, Posts, CPT (Custom Post Types): add, edit, delete.
- Categories, Tags, Taxonomies: add, edit, delete.
- Plugins: install, activate, deactivate, update, delete.
- Themes: install, activate, change (switch), update, delete.
- Errors Logs: show error type, message, stack-trace and time.
- Users: register/add, edit, delete.
- User Activity: login, logout, login fails, etc.

### Manage WordPress Plugin, Themes And Core Updates

Manage your WordPress website's plugin and theme from a single dashboard, check installed versions, available updates, etc.
including `mu-plugins` and `drop-ins`.

- Manage Plugins: Install new plugins from WordPress repository, update, activate/deactivate, uninstall/delete plugins.
- Manage Themes: Install new themes from WordPress repository, update, switch and delete themes including child-themes.
- Manage Core Updates: Upgrade WordPress core.

### WordPress error monitoring

This plugin will log and report php errors for your WordPress site. You will be able to view the error easily without having
to log into your server via ssh/ftp. This plugin will try to capture as much data as possible for the error, including error
message, error severity, file and line number, timestamp, WordPress version, user details (if any user loggedin) etc.

### WordPress Health Check

Receive a detailed report on your WordPress site's health and performance, including security reports.
UptimeMonster performs examinations to detect errors, issues, and custom checks by plugins and themes.

The Site Health Status feature evaluates performance and security aspects, categorizing issues and recommendations into three layers:

- Critical: Number of critical issues, categorized as security or performance, with suggested solutions.
- Recommended: List of recommendations for enhancing site health with step-by-step instructions.
- Passed Tests: Number of items with no issues, providing detailed information.

The plugin also reports website activity date and time, user details, and source IP addresses.
No setup is required; simply add the API key to connect the plugin.

### Comprehensive Monitoring with UptimeMonster

Extend your monitoring capabilities beyond website and WordPress health – UptimeMonster offers a comprehensive suite of services
to ensure the robustness of your entire online presence.
In addition to website and WordPress metrics, monitor the following services seamlessly from the same dashboard:

#### Server Monitoring

- Load Average
- CPU Utilization
- Disk Usage & Stats, iNode Usage
- RAM & Swap Usage
- Network Stats
- Active SSH Connections
- Running Processes

#### Service Monitoring

- IP blacklist
- DNS, FTP, sFTP, SSH
- SMTP, POP3, iMAP
- ICMP (ping), DNS lookup
- Custom TCP/IP Ports

Gain a holistic view of your digital infrastructure, ensuring optimal performance and preemptively addressing potential issues.
UptimeMonster simplifies the monitoring of your website, server, and additional services, providing a centralized solution for a
robust online presence.

Explore the full array of [features](https://uptimemonster.com/features) & [services](https://uptimemonster.com/management-services) available at your fingertips with UptimeMonster.

Check out the [UptimeMonster promo video](https://www.youtube.com/watch?v=UTT14RCx84k&ab_channel=UptimeMonster&sub_confirmation=1).

Please subscribe to our [YouTube Channel](https://www.youtube.com/@uptimemonster?sub_confirmation=1) for tips & tricks.

Start your journey by signing up for a [free starter account](https://uptimemonster.com/product/uptimemonster-yearly-pricing?attribute_pa_packages=starter&variation_id=1171&add-to-cart=1170) with [UptimeMonster](https://uptimemonster.com/product/uptimemonster-yearly-pricing?attribute_pa_packages=starter&variation_id=1171&add-to-cart=1170) today!

### Manage WordPress Themes and Plugins with UptimeMonster
The Uptime Monster Monitor plugin is based on UptimeMonster app service. Our monitoring service provides features such as installing, activating, deactivating, and uninstalling any themes or plugins for specific WordPress site. Therefore, users will perform these actions from the app instead of the WordPress dashboard.

== Installation ==

= Automatic installation =

Automatic installation is the easiest option -- WordPress handles the file transfer, and you won’t need to leave your web browser.

1. Log in to your WordPress dashboard
2. Navigate to the Plugins menu, and click “Add New.”
3. In the search field type “UptimeMonster Site Monitor” then click “Search Plugins.” Once you’ve found us, you can view details about it such as the point release, rating, and description. Most importantly of course, you can install it by! Click “Install Now,” and WordPress will take it from there.
5. Activate the plugin.
6. Go to UptimeMonster “Dashboard » Websites » YourWordPressSite » WordPress Tab”
7. On your WordPress admin dashboard goto “Settings » UptimeMonster Settings”
8. Copy and paste API key & secret from UptimeMonster WordPress tab to plugin settings fields.
9. Save settings.

Please follow this detailed instruction on [How to connect the plugin UptimeMonster](https://uptimemonster.com/docs/website-monitoring/wordpress-monitoring/)

= Manual installation =

1. Download this plugin's .zip file and extract it.
2. Upload the extracted directory (`uptimemonster-site-monitor`) to the `/wp-content/plugins/` directory on your web server with your favourite ftp/sftp client.
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to UptimeMonster “Dashboard » Websites » YourWordPressSite » WordPress Tab”
5. On your WordPress admin dashboard goto “Settings » UptimeMonster Settings”
6. Copy and paste API key & secret from UptimeMonster WordPress tab to plugin settings fields.
7. Save settings.

The WordPress codex contains more [instructions on how to do this here](https://wordpress.org/support/article/managing-plugins/#:~:text=Manual%20Plugin%20Installation,-%23).
Please follow this detailed instruction on [How to connect the plugin UptimeMonster](https://uptimemonster.com/docs/website-monitoring/wordpress-monitoring/)

== Frequently Asked Questions ==

= Is UptimeMonster free? =

UptimeMonster adopts a freemium model, offering a generous selection of [essential features at no cost](https://uptimemonster.com/product/uptimemonster-yearly-pricing?attribute_pa_packages=starter&variation_id=1171&add-to-cart=1170) for a restricted number of websites.
For users seeking greater capabilities or managing additional web entities, UptimeMonster provides a suite of premium features tailored to meet these advanced requirements.

= Where can I view my website log? =

You can view log using the UptimeMonster dashboard.

= Can I export logs? =

We're working on this feature. If you need logs for analysis before we roll out this feature, please reach-out our customer support and support staff will help.

= Do I need a subscription to use the plugin? =

Yes, you need an active subscription package for using this plugin.
The [starter package is free](https://uptimemonster.com/product/uptimemonster-yearly-pricing?attribute_pa_packages=starter&variation_id=1171&add-to-cart=1170) you can get basic features from the starter package.
For advanced features and functionalities please check out our [premium packages](https://uptimemonster.com/pricing/).

= Where can I suggest a new feature or report a bug? =

You can use the WordPress support forum to suggest new features and report any bugs or errors.

= How will I be alerted if my site has a security problem? =

You will get alert notification through email, push notifications (WhatsApp and SMS coming soon) based on your subscription package.

= Will UptimeMonster Site Monitor slow down my website? =

The plugin doesn't affect your website speed and performance.

== Screenshots ==

1. Site Overview
2. WordPress Activity Log
3. WordPress Health Check
4. WordPress Plugin Management
5. WordPress Theme Management
6. WordPress Error Log
7. Plugin Settings

== Changelog ==

= 1.0.0 =
* Initial Release.

== Upgrade Notice ==
