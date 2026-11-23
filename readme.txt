=== Reviews for Tutor LMS ===
Contributors: vladwtz
Donate link: https://paypal.me/vladutilie
Tags: reviews, tutor lms, course reviews, moderation, lms
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The `Reviews for Tutor LMS` plugin is an addon for `Tutor LMS` that allows you to manage course reviews.

== Description ==

The Reviews for Tutor LMS plugin provides additional functionality for managing reviews received on online courses. It allows you to approve, disapprove, mark as spam, or delete reviews individually and in bulk.

It adds a **Reviews** screen to the Tutor LMS admin menu, built like the WordPress Comments screen, where you can moderate every review left on your courses.

= Features =

* A **Reviews** submenu under Tutor LMS, with a bubble showing how many reviews are waiting for approval.
* Filter reviews by status: All, Pending, Approved, Spam and Trash, each with its own count.
* Approve, unapprove, mark as spam, restore, move to Trash or delete reviews permanently, one at a time or in bulk.
* See the reviewer, the review text, the star rating, the course and the date for every review.
* Sort reviews by author, rating or date.
* Jump straight to the reviewer's profile or to the course editor from the list.
* Pending reviews are highlighted, so they are easy to spot.

= How it works with Tutor LMS =

Reviews are still written by students through the standard Tutor LMS review form. This plugin only adds the moderation screen in the admin and does not change anything on the front end of your site.

To have new reviews wait for your approval before they are published, enable **Publish Course Review on Admin's Approval** in Tutor LMS > Settings > Course. New reviews will then show up as Pending on the Reviews screen.

= Requirements =

* [Tutor LMS](https://wordpress.org/plugins/tutor/) installed and activated. Tested with Tutor LMS 4.2.1.
* WordPress 6.4 or later.
* PHP 7.4 or later.

== Installation ==

1. Make sure you have the `Tutor LMS` plugin installed and activated.
2. Upload the `reviews-tutor-lms` folder to the `/wp-content/plugins/` directory, or install the plugin from the Plugins > Add New screen.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Go to Tutor LMS > Reviews to start moderating course reviews.

== Frequently Asked Questions ==

= Who can moderate reviews? =
Any user with the `manage_tutor_instructor` capability. By default, that means administrators and Tutor LMS instructors.

= How do I hold new reviews for approval? =
This is a Tutor LMS setting. Go to Tutor LMS > Settings > Course and enable **Publish Course Review on Admin's Approval**. New reviews will then be saved as Pending and you can approve them from Tutor LMS > Reviews.

= Does this plugin change the review form or the review pop-up? =
No. The review form, the review pop-up shown after a course is completed and the way reviews are saved all come from Tutor LMS. This plugin only adds the moderation screen in the admin.

= The review pop-up keeps coming back after a student submits a review. What can I do? =
This usually happens when logged-in users see cached pages. The review is saved and Tutor LMS stops showing the pop-up, but the student is still served a cached copy of the page that contains it.

If you use LiteSpeed Cache with **Cache Logged-in Users** enabled, you can either turn that setting off, or purge the student's private cache when they submit or dismiss a review. Add this snippet to your theme's `functions.php` file or to a code snippets plugin:

    /**
     * Purge the current student's LiteSpeed private cache after they
     * submit or dismiss a Tutor LMS course review.
     */
    function rtl_purge_litespeed_private_cache() {
        do_action( 'litespeed_purge_private_all' );
    }

    // After a new review is saved.
    add_action( 'tutor_after_rating_placed', 'rtl_purge_litespeed_private_cache' );

    // When the review pop-up is closed without leaving a review.
    add_action( 'wp_ajax_tutor_clear_review_popup_data', 'rtl_purge_litespeed_private_cache', 1 );

Other caching plugins have similar settings. Make sure course and lesson pages are not cached for logged-in users, or that their cache is cleared after a review is submitted.

Thanks to a user of the plugin for finding the cause and sharing the original snippet.

= Why does this plugin exist? =
I use and appreciate the Tutor LMS plugin, but decent functionality should have included review management in the free version. Anyone who needs Tutor LMS and course reviews can use this module that implements their management simply and affordably.

= How can I contribute to this plugin? =
The plugin is hosted on [GitHub](https://github.com/vladutilie/reviews-tutor-lms), where it is developed and maintained. Bug reports and pull requests are welcome.

= Where can I get help? =
Open a topic on the [support forum](https://wordpress.org/support/plugin/reviews-tutor-lms/). If the plugin is useful to you, a [review](https://wordpress.org/support/plugin/reviews-tutor-lms/reviews/#new-post) is much appreciated.

== Screenshots ==

1. Reviews list table

== Changelog ==

= 1.0.4 =
* Fix: Searching reviews now works. The search box filters reviews by their text, the reviewer's name and the course title, together with the selected status.
* Fix: The search term is kept when moving between pages, sorting the table or applying bulk actions.
* Fix: The search box stays visible when a search has no results, so it can be changed.
* Fix: The Live Preview on WordPress.org works again. Tutor LMS is now activated before this plugin, as its dependency requires.
* Docs: New FAQ entries, including how to stop the Tutor LMS review pop-up from reappearing when logged-in users get cached pages.
* Tested with WordPress 7.1 and Tutor LMS 4.2.1.

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

= 1.0.4 =
Fixes searching reviews and the Live Preview on WordPress.org.

= 1.0.3 =
Security release. Fixes escaping of review content in the admin table and enforces capability checks on all moderation actions. Updating is recommended for all users.
