=== Reviews for Tutor LMS ===
Contributors: vladwtz
Donate link: https://paypal.me/vladutilie
Tags: reviews, tutor lms, reviews addon
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The `Reviews for Tutor LMS` plugin is an addon for `Tutor LMS` that allows you to manage course reviews.

== Description ==

The Reviews for Tutor LMS plugin provides additional functionality for managing reviews received on online courses. It allows you to approve, disapprove, mark as spam, or delete reviews individually and in bulk.

== Installation ==

1. Make sure you have the `Tutor LMS` plugin installed and activated.
2. Upload the `reviews-tutor-lms` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= Why does this plugin exist? =
I use and appreciate the Tutor LMS plugin, but decent functionality should have included review management in the free version. Anyone who needs Tutor LMS and course reviews can use this module that implements their management simply and affordably.

= How can I contribute to this plugin? =
The plugin is hosted on [GitHub](https://github.com/vladutilie/reviews-tutor-lms), where it is developed and maintained.

== Screenshots ==

1. Reviews list table

== Changelog ==

= 1.0.3 =
This is a security release. Updating is recommended for all users.

* Security: Escape review content, reviewer names and course titles before they are rendered in the reviews table. A review submitted from the front end could previously inject HTML into the moderation screen.
* Security: Require the review management capability for every moderation action. Approving, spamming, trashing and deleting reviews were previously protected by a nonce alone, without checking that the user was allowed to moderate.
* Security: Restrict the status filter and the sorting column to a known list of values before they reach the database.
* Security: Bind each row action to the nonce for the action it performs, so an approval link can no longer be reused to delete a review.
* Fix: Stop execution after redirecting, instead of continuing to render the page.
* Fix: Ignore unknown or empty bulk actions, which previously produced a database error.
* Fix: Permanently deleting reviews now works from the Spam view, not only from Trash.
* Fix: The status filter links no longer accumulate the previously selected status.
* Fix: Ratings outside the 1 to 5 range no longer raise an error on PHP 8.
* Tested with WordPress 7.0 and PHP 7.4 through 8.5.

= 1.0.2 =
* Tested with WordPress 6.7, Tutor LMS 3.0.0 and PHP 8.3.13.
* Live preview fixed.

= 1.0.1 =
* Fix: Displays the reviewer's username, without a URL to their profile, when they do not have an account on the site.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.3 =
Security release. Fixes escaping of review content in the admin table and enforces capability checks on all moderation actions. Updating is recommended for all users.
