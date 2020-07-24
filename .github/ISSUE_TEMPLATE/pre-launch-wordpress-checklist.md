---
name: Pre-Launch WordPress Checklist
about: Pre-Launch WordPress Checklist
title: Test-Refine-Optimize
labels: Test-Refine-Optimize
assignees: ''

---

CONTENT
- [ ] **Replace Dummy Content** - *Check your site for placeholder text (such as Lorem ipsum) and make sure the actual (client-approved) content is in place.*
- [ ] **Proofread** - *Double-check all text on the site for mistakes and then check again.*
- [ ] **Format Written Content** - *Avoid big blocks of text, use paragraphs, and don’t forget about headings!*
- [ ] **Check for Placeholder Images** - *Any stand-in visuals should be removed and replaced with actual images.*
- [ ] **Go through Videos and Audio Files** - *See that the right files are in the right place and that they are working properly.*
- [ ] **Examine Page Links** - *Make sure no link is broken, internal links point where they are supposed to, and external links open in a new tab.*
- [ ] **Check Downloadable Files** - *If you have downloadables, make sure they are proofread, linked to correctly and can in fact be downloaded.*
- [ ] **Set Up a 404 Error Page** - *Though most themes have a default page for content that can’t be found, you should also consider setting up a custom [404 error page](https://torquemag.io/2015/01/custom-404-error-page-wordpress/).*
- [ ] **Implement a Contact Page** - *Use plugins like [Jetpack](https://wordpress.org/plugins/jetpack/) or [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) to set up an easy way for users to communicate with you.*
- [ ] **Set Up Redirections** - *If there is a need for any redirections (such as to preserve link juice from an old site), make sure they are set up correctly. [Redirection](https://wordpress.org/plugins/redirection/) is an excellent tool for this.*

DESIGN
- [ ] **Validate HTML Markup** - *To avoid incompatibilities, use tools like [W3C’s Markup Validation Service](https://validator.w3.org/) to check all pages of your site adhere to web standards.*
- [ ] **Validate CSS** - *What goes for HTML is also valid for your styles, and you can [use this tool](https://jigsaw.w3.org/css-validator/) for it.*
- [ ] **Preview Site in Major Browsers** - *To spot cross-browser compatibility problems, view your site in Firefox, Chrome, Safari, Internet Explorer/Microsoft Edge, and any legacy browser your client wants to support. Look especially at images and videos. An alternative is using a service like [Browsershots](http://browsershots.org/).*
- [ ] **Check Responsive Design** - *To ensure optimal user experience, test your design on different gadgets (both phones and tablets) and platforms (Android, iOS) and/or use something like [MobileTest.me](http://mobiletest.me/).*
- [ ] **Use Google's Mobile Testing Tool** - *In addition to manual testing, Google also offers a [mobile testing tool](https://search.google.com/test/mobile-friendly?utm_source=mft&utm_medium=redirect&utm_campaign=mft-redirect) with lots of helpful feedback. Just input your URL and it will run a full analysis of your site.*
- [ ] **Optimize Images** - *Reducing image size as much as possible. [TinyPNG](https://tinypng.com/) and [RIOT](https://riot-optimizer.com/) can do this for you.*
- [ ] **Add a Favicon** - *Probably one of the most frequently forgotten parts of website design, a favicon appears in browser tabs and makes you look extra professional. [Favicomatic](https://favicomatic.com/) will easily create one for you.*
- [ ] **Link Header Logo Back to Homepage** - *Make sure your company logo is present in the header. Check that it links back to the starting page so users can return there quickly.*
- [ ] **Set Up a Print Stylesheet** - *For users who want to get your content down onto paper, you can make the task much easier with a print stylesheet. [The WordPress Codex](https://codex.wordpress.org/Styling_for_Print) can tell you how to create one.*

FUNCTIONALITY
- [ ] **Test Drive Web Forms** - *Whatever forms you have on your site (contact, order, survey, etc.) ensure that they are submitting data correctly and that form emails arrive in the right place.*
- [ ] **Confirm Form Messages and Redirects** - *Ensure users get relevant help messages and are they moved on to the right place after submitting data.*
- [ ] **Review Autoresponders** - *If you have any sort of automatic emails in place, make sure they are working correctly.*
- [ ] **Check Your Speed Score** - *Use a tool like [Google Pagespeed Insights](https://developers.google.com/speed/pagespeed/insights/), [GTMetrix](https://gtmetrix.com/), and [Pingdom](https://www.pingdom.com/) to see how quickly your site loads and what you can do to make it even faster. If at all possible, shoot for a loading time below two seconds.*
- [ ] **Test Social Sharing Functionality** - *Check whether social sharing is in place, working properly, and includes the right platforms and profiles.*
- [ ] **Try Out Site Feeds** - *If you have RSS, news, social, or other feeds on your WordPress site, ensure they are working as they are supposed to before site launch.*
- [ ] **Implement Accessibilty Guidelines** - *Read [this article](https://torquemag.io/2016/01/web-accessibility-guidelines-wordpress/) on how to implement it in WordPress, especially if you are required to by law.*
- [ ] **Test 3rd Party Tools** - *If you are using external tools for CRM, eCommerce, marketing, etc, double-check that they are present on-site and doing what they are meant to do.*

SEO
- [ ] **Install and Configure an SEO Plugin** - *While WordPress is well configured for SEO as it is, an SEO plugin like [Yoast SEO](https://wordpress.org/plugins/wordpress-seo/) or [All-in-One SEO Pack](https://wordpress.org/plugins/all-in-one-seo-pack/) will take it to the next level.*
- [ ] **Set Site Title and Tagline** - *If your SEO plugin doesn’t already take care of your site’s title and tagline, make sure to set it under Settings > General.*
- [ ] **Configure SEO Page and Post Titles** - *All pages and posts on your site should have unique titles with less than 70 characters that include the keywords each of them are optimized for.*
- [ ] **Implement Meta Descriptions** - *Create unique and expressive meta descriptions for all posts and pages that include keywords and are less than 156 characters long.*
- [ ] **Set Up Permalinks** - *Make sure the permalink structure is set to your liking (Settings > Permalinks) and that each page URL contains its main keyword.*
- [ ] **Optimize Images (Again)** - *Check image file names, descriptions, and ALT tags for keyword inclusion and make sure each image is compressed for quick loading times.*
- [ ] **Set Focus Keywords** - *If you are using an SEO plugin, each post and page should also have a defined focus keyword. This will give you important information on how to further optimize them.*
- [ ] **Work Through Content Analysis** - *When you have set the focus keyword for a site, be sure to go through the tips inside the content analysis to achieve the best results.*
- [ ] **Create Sitemap** - *[Yoast SEO](https://wordpress.org/plugins/wordpress-seo/) and [Google XML Sitemaps](https://wordpress.org/plugins/google-sitemap-generator/) can help you set up a sitemap to share with search engines. If you are still working in a development environment, skip this step until you have moved the site to its final destination.*
- [ ] **Set Up Metadata** - *Check meta tags for social and RSS feeds and whether they are set up correctly, appropriate, and proofread. Don’t forget to implement optional tags like rel=”nofollow”.*

MARKETING
- [ ] **Implement Newsletter Signups** - *Have you included a signup form in all important places? Think not only pages but also popups and other prompts.*
- [ ] **Connect with Email Marketing Provider** - *When you include a form, make sure it also goes somewhere and that email collection works properly.*
- [ ] **Set Up Social Icons** - *Examine your social icons for completeness, proper functionality, and whether they are linking to the correct addresses. Plus, if you haven’t done so already, look into whether social sharing is working as it should.*
- [ ] **Look Over Social Profiles** - *Check any social profiles belonging to the site for design consistency and whether all info found on there is correct and up to date.*

LEGAL
- [ ] **Show Company Details** - *Making your contact details easy to reach from anywhere on the site is a sign of trustworthiness and often mandatory. Include important info like tax registration, etc. if necessary.*
- [ ] **Acquire Required Licenses** - *In case you are using images, fonts, code, plugins, etc. that need licensing, take care that everything is in place and stated as needed.*
- [ ] **Include Copyright Note** - *On a related topic, include a copyright statement if necessary.*
- [ ] **Provide Privacy Policy** - *Required in some places by law, an official privacy policy is always a good idea to create trust when collecting any sort of data.*
- [ ] **Include Terms and Conditions** - *If you are an e-commerce shop or otherwise dealing with transactions and money, this is an absolute must-have.*
- [ ] **Implement Cookie Warning** - *Especially in the EU, cookie warnings have become the law of the land. While a lot of people don’t like cookies because they can mess with their design, you can save yourself a lot of legal hassle by including them on your site as needed.*
- [ ] **Look Into Local Requirements** - *Your area might have specific legal demands for anti-spam measures, credit card processing, and more. Make sure you get informed and that your site adheres to them.*

MOVE SITE TO NEW SERVER
- [ ] **Acquire and Set Up Domain** - *If you haven’t secured and configured the domain for your new website yet, now is the time to do so.*
- [ ] **Prepare Database** - *Create a database and a user for the new site location*
- [ ] **Copy Site Data and Database** - *Move both the site’s data and MySQL database to the new location. Plugins for automatic site migration like [Duplicator](https://wordpress.org/plugins/duplicator/), [UpdraftPlus](https://wordpress.org/plugins/updraftplus/), and WP Migrate DB Pro make this much easier.*
- [ ] **Point Domain to New Directory** - *Once there is a site in place, you can point the new domain to its location.*
- [ ] **Check Site Display** - *Have a quick look to see if the site is displaying properly in the new location. Especially look for missing images and icons. Should the design be completely messed up, something went wrong in the earlier steps.*
- [ ] **Test Links and Pages** - *Dive deeper into the site and check if links work correctly, are pointing to the right addresses (the live site, not the development environment), and whether secondary pages are reachable and displayed correctly.*

PREPARE FOR LAUNCH
- [ ] **Relicense Products** - *If your site is running commercial, third-party plugins or themes, it might be necessary to renew their licenses for the new domain.*
- [ ] **Activate Site Caching** - *Popular solutions include [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/), [W3 Total Cache](https://wordpress.org/plugins/w3-total-cache/), and [WP Rocket](https://wp-rocket.me/).*
- [ ] **Connect to CDN** - *For an even faster website and if you are using such a service, don’t forget to set up your content delivery network. In case you are looking for a free solution, [Photon](https://jetpack.com/support/site-accelerator/) might be worth a look. Most managed hosting providers offer CDN service.*
- [ ] **Set Up Google Analytics** - *[Google Analytics by Yoast](https://wordpress.org/plugins/google-analytics-for-wordpress/) makes setup easy as pie.*
- [ ] **Connect Google Webmaster Tools** - *Integrate the site with Google’s webmaster suite.*
- [ ] **Clean Up** - *Be sure to delete old and unneeded files, databases, subdomains, and any other remnants of the development site.*

BACKUP & SECURITY
- [ ] **Install and Anti-Spam Solution** - *Using a solution like [Akismet](https://wordpress.org/plugins/akismet/) or [Antispam Bee](https://wordpress.org/plugins/antispam-bee/) will help you weather the worst.*
- [ ] **Implement Login Protection** - *Besides proper usernames and passwords, plugins like [Login Lockdown](https://wordpress.org/plugins/login-lockdown/) will help you in keeping it safe.*
- [ ] **Set Up a Backup Solution** - *Setting up a backup solution for both your site data and database is a mandatory pre-launch step.*
- [ ] **Verfiy Backups** - *Create your first backup and verify that it is saved successfully in the specified location.*
- [ ] **Update Admin Password** - *Set up a safe password -> [Password Gen](https://my.norton.com/extspa/passwordmanager?path=pwd-gen)*

LAUNCH
- [ ] **Disable "Discourage Search Engines"** - *In the WordPress dashboard, under Settings > Reading uncheck the box that says “Discourage search engines from indexing this site.”*
- [ ] **Inspect Robots.txt** - *If you have [set up a robots.txt file](https://torquemag.io/2015/04/wordpress-robots-txt-seo/), make sure to delete or reconfigure it so that search engines aren’t kept out of the live site.*
- [ ] **Build Sitemap** - *If you haven’t done so while checking your SEO settings, now is the time to create a sitemap. Afterwards, be sure to submit it to any search engine you care about ranking on.*
- [ ] **Compile Documentation** - *For a successful handover to the client, put together all necessary documentation, in particular:*
          *Login names and passwords*
          *Hosting information*
          *Database information*
          *FTP credentials*
          *Login information for other accounts (email marketing provider, social media, etc)*
          *Expiration date of paid plugins*
          *How-to guides for using the site*
          *Debrief on next steps*
