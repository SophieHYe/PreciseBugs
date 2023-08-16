=== Job board ===
Contributors: bestwebsoft
Donate link: https://www.2checkout.com/checkout/purchase?sid=1430388&quantity=1&product_id=94
Tags: plugin, wordpress, jobs, vacancy, job-manager, job-board, job, job offer, job bord, djob board, dgob bord, CV, upload CV, add job offer, apply for a job, vacancy application, job candidate role, manage vacancies, job offer list, save search conditions, job offer categories, search by job category, search by salary, search by organization, post job offer, vacancy archive.  
Requires at least: 3.5
Tested up to: 3.9.2
Stable tag: 1.0.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

The plugin allows to create a job-board page on your site.

== Description ==

The plugin allows to create and post job-board posts as well as to change, sort and archive them. After inserting the short-code into the page, you will see all vacancies from a search form by categories, organizations, salary etc. The search form is available only after the user has been authorized as a candidate. The candidate is allowed to upload and submit a CV, select a category by which the list of vacant positions is formed for the last 24 hours and save the current search conditions to be used as a search template in future.

<a href="http://wordpress.org/plugins/job-board/faq/" target="_blank">FAQ</a>
<a href="http://support.bestwebsoft.com" target="_blank">Support</a>

= Features =

* Actions: Allows to add, edit, delete and categorize the vacancies.
* Actions: Sorts and filters vacant positions - both in the admin panel and in the front-end.
* Actions: Allows to apply for the vacancy (after registration).
* Actions: Allows to save the search as a template for future use.
* Actions: Allow the applicant to select a category and browse the vacancies in this category using in the admin panel.
* Actions: Sends letters when you get an application for the vacancy.
* Actions: Automatically places the vacancy to the archive after the expiration of the period which was set either by the administrator or by the author.
* Actions: Allows the administrator to manage of how the vacancies page as well as vacancies in the front-end are displayed. 
* Actions: Allows to place the login/registration form to add users with Employer and Job candidate roles. 

= Translation =

* Russian (ru_RU)
* Ukrainian (uk)

If you would like to create your own language pack or update the existing one, you can send <a href="http://codex.wordpress.org/Translating_WordPress" target="_blank">the text of PO and MO files</a> for <a href="http://support.bestwebsoft.com" target="_blank">BestWebSoft</a> and we'll add it to the plugin. You can download the latest version of the program for work with PO and MO files <a href="http://www.poedit.net/download.php" target="_blank">Poedit</a>.

= Technical support =
Dear users, our plugins are available for free download. If you have any questions or recommendations regarding the functionality of our plugins (existing options, new options, current issues), please feel free to contact us. Please note that we accept requests in English only. All messages in another languages won't be accepted.
If you notice any bugs in the plugin's work, you can notify us about it and we'll investigate and fix the issue then. Your request should contain URL of the website, issues description and WordPress admin panel credentials.
Moreover we can customize the plugin according to your requirements. It's a paid service (as a rule it costs $40, but the price can vary depending on the amount of the necessary changes and their complexity). Please note that we could also include this or that feature (developed for you) in the next release and share with the other users then.
We can fix some things for free for the users who provide translation of our plugin into their native language (this should be a new translation of a certain plugin, you can check available translations on the official plugin page).

== Installation == 

1. Upload the `job-board` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin using the 'Plugins' menu in your WordPress admin panel.
3. You can adjust the necessary settings using your WordPress admin panel in "BWS Plugins" > "Job board".
4. Create a page or a post and insert the short-code [jbbrd_vacancy] into the text.

== Frequently Asked Questions ==

= How can I create a vacancies page on my site? =

Just insert the short-code [jbbrd_vacancy] into your page or post and save the settings on the settings page of the plugin.
Attention: In order to avoid incorrect formatting of the text, the short-code should be placed in text mode.

= How can I place the login / registration form to add users with Employer and Job candidate roles =

Insert the short-code [jbbrd_registration] into your page or post.
You can also add the login / registration form into widget. To do it, just create a text widget and put the short-code into the widget field.
To prevent the login / registration errors, do not place more than one registration form short-code on your website.

= How can I add a category of vacancies? =

To create or edit an existing category of vacancies, click on "Vacancies" on the admin panel, then click on the "Vacancy categories". Fill in the fields "Name", "Slug", "Description". Then add a new category by clicking on the "Add new vacancy category".

= How can I add a job offer on the vacancy page? =

Only administrator and the users with the role "employer" can manage job offers. Users with the role "employer" can view and sort all vacancies, as well as to create and edit their own. Roles are assigned by the administrator. The administrator can create, browse, sort, edit and delete any vacancies, CV, saved searches and search categories.
Log in to your profile. Click "Vacancies" on the Panel, then click "Add vacancies". Fill in the fields, select the job category if it already exists, or create a new one. Fields marked with "*" are mandatory. Publish this vacancy by clicking on "Publish".

= How can I add my company`s logo to a job offer? =

Create a new vacancy, or press the "Edit" in existing one. Select "Featured Image / Set featured image". Upload an image and save the post. Recommended logo images for download are 150px by 100px. 

= Why I cannot see the CV sort and send form? =

Make sure that you have Sender plugin installed and activated.
Register as a Job candidate to get the possibility to use a filter of vacancies and send CV.

= How can I send CV? =

Log in to your profile. In the user profile file, add CV (Doc, Docx, Pdf, Txt only).  Click "Send CV" under the vacancy. The employer will receive an email with your details and a link to your CV attached file.

= Why I cannot find a vacancy using a standard search form? =

Unlike standard posts, vacancies are custom post type and they are not included in the standard search form. To add a vacancy custom post type into your search, please install the plugin <a href="/wp-admin/plugin-install.php?tab=search&type=term&s=Custom+Search+plugin+bestwebsoft&plugin-search-input=Search+Plugins">"Custom search"</a>

= What is the "archive of vacancies"? How to extract a vacancy from the archive of vacancies?? =

Vacancies` validity is specified when entering a date in the "Expiry date" field. After reaching the specified date, vacancies are been automatically placed in the archive. Archive vacancies are not displayed in the front-end of the vacancies page, but they are preserved and always available for editing. You can remove the job from the archive. To do this, enter a new date in the "Expiry date" and click "Restore from archive". If you specify wrong (already past) date in the "Expiry date", the vacancy will be available till the next archivation. You can set the time of daily archivation using the settings page of the plugin. If the field "Expiry date" is empty, the vacancy is considered to be constant.

= I have some problems with the plugin's work. What Information should I provide to receive proper support? =

Please make sure that the problem hasn't been discussed yet on our forum (<a href="http://support.bestwebsoft.com" target="_blank">http://support.bestwebsoft.com</a>). If no, please provide the following data along with your problem's description:
1. the link to the page where the problem occurs
2. the name of the plugin and its version. If you are using a pro version - your order number.
3. the version of your WordPress installation
4. copy and paste into the message your system status report. Please read more here: <a href="https://docs.google.com/document//1Wi2X8RdRGXk9kMszQy1xItJrpN0ncXgioH935MaBKtc/edit" target="_blank">Instruction on System Status</a>

== Screenshots ==

1. Job board display.
2. Adding new vacancy display with additional fields.
3. Adding new vacancies category display with additional fields.
4. Adding new employment type display.
5. Plugin settings in WordPress admin panel with additional fields.
6. Vacancies page in front-end display with sorting form fields.
7. Single job offer page view.
8. Job candidate settings on profile page.
9. Job candidate chosen category offers by last day on profile page screen.
10. Registration form in widget area.

== Changelog ==

= V1.0.1 - 08.08.2014 =
* Bugfix : Security Exploit was fixed.

= V1.0.0 - 18.07.2014 = 
* Bugfix : Login/registration form bugs were fixed.
* Bugfix : Session bugs were fixed.

== Upgrade Notice ==

= V1.0.1 =
Security Exploit was fixed.

= V1.0.0 =
Login/registration form bugs were fixed. Session bugs were fixed.
