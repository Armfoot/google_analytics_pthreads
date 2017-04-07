# Google Analytics Pthreads

This is essentially a mini-library I created that used [Pthreads](php.net/manual/en/book.pthreads.php) in a CodeIgniter project in order to perform a great amount of queries to a Google Analytics (GA) account in a short time.

The amount of queries performed should be within the acceptable [Google's "General Quota Limits"](https://developers.google.com/analytics/devguides/config/mgmt/v3/limits-quotas#general_quota_limits) in order to avoid an overload of requests to the respective Google account (which may lead to subsequent requests refusals).

## Getting Started

The `libraries\GA_thread.php` file is responsible for:
 - initiating a Google_AnalyticsService;
 - limiting the GA queries to certain 'filters' and 'dimensions' (query definition) through an array sent to Google;
 - returning the results with the data obtained from Google.

The `config\googleanalytics.php` file contains the details that allow access to the Google account.

`controllers\GA_query.php` contains the methods that invoke the GA_thread with a date interval and an ID to be used as a filter in the queries' labels. For simplicity the generated reports were directly configured in this file.

## Prerequisites and Usage

1. [Pthreads' extension installed and active](http://php.net/manual/en/pthreads.installation.php) in a WAMP/LAMP/MAMP environment running on a local computer (a [web server environment is not recommended](https://www.sitepoint.com/parallel-programming-pthreads-php-fundamentals#whennottousepthreads) since the execution may take several minutes, leading to any website being hosted in the server to be inaccessible during that time);
2. Access the desired public method of `controllers\GA_query.php` in a web browser to execute the queries, e.g.: `http://mydomain.mine/GA_query/ga_date_report/3`;
3. Upon completion, a log shows up on the web page and the CSV file is generated containing the queries results.
