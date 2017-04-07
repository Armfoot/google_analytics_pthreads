<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Creates pthreads for Google Analytics (GA) requests.
 *
 * Returns result rows from GA queries.
 *
 * @package     CodeIgniter
 * @subpackage  Libraries
 * @category    Libraries
 * @author      Armfoot
 */
class Ga_thread extends Thread {
    public $rid;				    // record id
    private $from_date;			// oldest date to query
    private $to_date;			  // most recent date to query
    private $type;				  // type of query to perform
    private $app_name;			// application name associated to Google
    private $app_email;     // ID@developer.gserviceaccount.com
    private $app_key_path;  // location of the private key file
    private $app_path;      // CodeIgniter's project root directory
    private $app_profileID; // ga:ID
    public $data;           // results of GA queries

    public function __construct($ga_params) {
        $this->rid = $ga_params['record_id'];
        $this->from_date = $ga_params['from_date'];
        $this->to_date = $ga_params['to_date'];
        $this->type = empty($ga_params['type'])?"general":$ga_params['type'];

        // used for getting the GA service
        $this->app_path = 'C:/Projects/my_project/webapp/';//'/var/www/my_project/webapp/';

        // "$this->CI is an assigned reference, and since PHP 5.2, it is not possible
        // assigning something else by reference without first erasing it"
        // http://forum.codeigniter.com/archive/index.php?thread-39047.html
        // In here there is no need the reference ("&") for the assignment
        $CI=get_instance();

        // Google Account details
        $CI->config->load('googleanalytics');
        $this->app_name= $CI->config->item('appName');
        $this->app_email= $CI->config->item('appEmail');
        $this->app_key_path= $CI->config->item('keyPath');
        $this->app_profileID = $CI->config->item('profileID'); //'ga:12345678'
    }

    public function run() {
    	switch ($this->type) {
    		case 'date':
            	$this->data = $this->_ga_by_date(
            					$this->rid,
            					$this->from_date,
            					$this->to_date
            				);
    			break;
    		default:
            	$this->data = $this->_ga_general(
            					$this->rid,
            					$this->from_date,
            					$this->to_date
            				);
    			break;
    	}
    }

    /**
     * Retrieves the GA service directly from Google's source code
     */
    public function _get_GAS()
    {
      	// full paths are required since pthreads are relative to the Apache directory
        include_once $this->app_path . 'application/libraries/GoogleApi/Google_Client.php';
        include_once $this->app_path . 'application/libraries/GoogleApi/contrib/Google_AnalyticsService.php';
        
        $gcli = new Google_Client();
        $gcli->setApplicationName($this->app_name);

        // Set assertion credentials
        $gcli->setAssertionCredentials(
            new Google_AssertionCredentials(
                $this->app_email
                , array('https://www.googleapis.com/auth/analytics.readonly')
                , file_get_contents($this->app_path.$this->app_key_path)
            )
        );
        $gcli->setClientId($this->clientID);
        $gcli->setAccessType('offline');
        $gcli->setScopes(array('https://www.googleapis.com/auth/analytics.readonly'));

        // Returns objects from the Analytics Service instead of associative arrays.
        return new Google_AnalyticsService($gcli);
    }

    /**
     * Performs queries to Google Analytics with no particular dimension
     *
     * @param string $rid record_id
     * @param string $start_date oldest date to query
     * @param string $end_date most recent date to query
     * @return array each element represents the results of one GA query
     */
    private function _ga_general($rid, $start_date, $end_date){
        $gas = $this->_get_GAS();
        
        // Visits to certain pages
        $page_visits = $gas->data_ga->get(
        	$this->app_profileID
            , $start_date
            , $end_date
            , 'ga:totalEvents'
            , array(
              'filters' =>	'ga:eventCategory==records_pages;'.
              				'ga:eventAction==visit;'.
              				'ga:eventLabel=='.$rid.';'
            )
        );

        // Forms submits
        $form_submits = $gas->data_ga->get(
        	$this->app_profileID
            , $start_date
            , $end_date
            , 'ga:totalEvents'
            , array(
              'filters' =>	'ga:eventCategory==records_forms;'.
              				'ga:eventAction==submit;'.
              				'ga:eventLabel=~^'.$rid.'\;'
			                // Another regular expression example:
			                // '^('.$rid.'\;|ID:'.$rid.'-|'.$rid.'$)\;';
            )
        );

        // When no dimensions are specified in the query,
        // GA data is returned in the first element of the subarray's subarray
        return array('visits' => array_key_exists('rows', $page_visits) ?
        							 $page_visits['rows'][0][0] : 0,
        			 'submits' => array_key_exists('rows', $form_submits) ?
        							 $form_submits['rows'][0][0] : 0);
    }
    /**
     * Performs queries to Google Analytics with DATE as dimension.
     * Using this dimension will return multiple rows referencing specific days.
     *
     * @param string $rid record_id
     * @param string $start_date oldest date to query
     * @param string $end_date most recent date to query
     * @return array each element represents the results of one GA query
     */
    private function _ga_by_date($rid, $start_date, $end_date){
        $gas = $this->_get_GAS();

        // Visits to certain pages
        $page_visits = $gas->data_ga->get(
        	$this->app_profileID
            , $start_date
            , $end_date
            , 'ga:totalEvents'
            , array(
              'dimensions' => 'ga:date',
              'filters'	=>	'ga:eventCategory==records_pages;'.
              				'ga:eventAction==visit;'.
              				'ga:eventLabel=='.$rid.';'
            )
        );

        // Forms submits
        $form_submits = $gas->data_ga->get(
        	$this->app_profileID
            , $start_date
            , $end_date
            , 'ga:totalEvents'
            , array(
              'dimensions' => 'ga:date',
              'filters' =>	'ga:eventCategory==records_forms;'.
              				'ga:eventAction==submit;'.
              				'ga:eventLabel=~^'.$rid.'\;'
			                // Another regular expression example:
			                // '^('.$rid.'\;|ID:'.$rid.'-|'.$rid.'$)\;';
            )
        );

        // If for some reason 'rows' are not in the result, return an empty array
        return array('visits' => array_key_exists('rows', $page_visits) ?
        							 $page_visits['rows'] : array(),
        			 'submits' => array_key_exists('rows', $form_submits) ?
        							 $form_submits['rows'] : array());

    }
}