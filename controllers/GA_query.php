<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Methods for combining database and Google Analytics (GA) data into a CSV file.
 * Invoking the public methods below as such:
 * http://mydomain.mine/GA_query/ga_date_report/3
 * will result in the generation of a CSV file
 * and the browser showing a simple text report with the times that each query took to perform
 * 
 * @package     CodeIgniter
 * @subpackage  Controllers
 * @category    Controllers
 * @author      Armfoot
 */
class GA_query extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // Set the appropriate timezone (in sync with GA)
        date_default_timezone_set('UTC');
        // GA library
        $this->load->library('ga_thread');
        // Model with methods that query the Database
        $this->load->model('report_model');
    }

    /**
     * Query the Database first and then query GA in order to generate a report.
     * The report returns accumulated events by day.
     * Each row represents a day (dimension date).
     *
     * @param int $days_to_query the number of days to query before the past day (yesterday)
     */
    public function ga_date_report($days_to_query=7){
        if($days_to_query<1)
            // At least one day is going to be queried
            $days_to_query=1;

        $from_date = date('Y-m-d', // Analytics date format
        	mktime(0, 0, 0, // hours, minutes and seconds
    			date("m"),
    			date("d")-$days_to_query, // subtract from today a number of days
    			date("Y")
        	)
        );
        $to_date = date('Y-m-d',
        	mktime(0, 0, 0,
        		date("m"),
        		date("d")-1, // yesterday
        		date("Y")
        	)
        );

        // Get Database data in order to query GA 
        $db_records = $this->report_model->get_records($to_date);
        
        // Printing a query log directly to the page (no view involved)
        echo "<pre style='background: #EEE;width:90vw'><b>".count($db_records). " properties</b> found".PHP_EOL;

        // CSV that will contain the data returned by GA queries (first line has the headers)
        $csv_data = "Id;Title;Description;Date;Visits;Submits\r\n";

        // Initial time for determining the duration of GA queries
        $total_time = microtime(true);

        // Cicle the records and query them respectively
        foreach ($db_records as $record) {
        	// If publish_date of this record is later than the one given by the parameter,
        	// then use publish_date, else use the parameter's date ($from_date)
            $record_from_date = strtotime($record['publish_date'])> strtotime($from_date) ?
                            	date('Y-m-d', strtotime($record['publish_date'])):
                                $from_date;

            // Determine the number of days to be queried
            $from_datetime = new DateTime($record_from_date);
            $to_datetime = new DateTime($to_date);
            // Add one day by default (the above datetimes can be the same)
            $days_to_query = intval($from_datetime->diff($to_datetime)->days) + 1;

            $gaT = new Ga_thread(
                array(
                    'record_id'=>$record['record_id'],
                    'from_date'=>$record_from_date,
                    'to_date'=>$to_date,
                    'type'=>"date"
                )
            );

        	// Get the current time before performing a GA query
            $t = microtime(true);
            // Initialize the query
            if($gaT->start()){
                printf("Request took %f seconds to start",
                	   microtime(true) - $t);

                // Add a dot each 1/10 of a second
                while ($gaT->isRunning()) {
                    echo ".";
                    usleep(100000);
                }

                // `join()` block will only be executed after the query is completed
                if ($gaT->join()) {
                    printf(" and %f seconds to finish receiving".PHP_EOL,
                    	   microtime(true) - $t);
                    echo $record['record_id']." - $days_to_query days:";

                    // Data returned by the GA query
                    $gaData = $gaT->data;

                    if(!empty($gaData['visits']) && !empty($gaData['submits'])) {
                        // $i represents a day
                        for ($i=0; $i < $days_to_query; $i++) {
                            echo date('Y-m-d', strtotime($record_from_date." + $i days")).
                            	 PHP_EOL;

                            // Writing a CSV file with database and GA data
                            $csv_data.=
                            	$record['record_id'].';"'.
                    			trim($record['title']).'";"'.
                            	trim($record['description']).'";'.
                            	// The first element of the returned GA subarray has the day's date
                            	date_create($gaData['visits'][$i][0])->format('Y-m-d').';'.
                            	// The second element has the total count of events of that day
                    			$gaData['visits'][$i][1].';'.$gaData['submits'][$i][1]."\r\n";
                        }
                    }
                    echo PHP_EOL;
                } else
                    printf(" and %f seconds to finish, request failed".PHP_EOL,
                    	   microtime(true) - $t);
            }
            // Await half a second for each record, avoiding overload
            // https://developers.google.com/analytics/devguides/config/mgmt/v3/limits-quotas#general_quota_limits
            usleep(500000);
        }
        printf(PHP_EOL."Requests total time: <b>%f seconds</b></pre>".PHP_EOL,
        	   microtime(true) - $total_time);

        // Write the CSV file into the "data" directory
        $csvFilePath = "./data/records_stats_by_date--".$from_date."_".$to_date.".csv";
        if ( ! file_put_contents($csvFilePath, $csv_data)){
            echo "No data wrote to the CSV file.";
        }
    }

    /**
     * Query the DB first and then GA in order to generate a report.
     * Queries without dimensions provide a simple count of events .
     *
     * @param string $to_date last day for querying (date format 'YYYY-MM-DD')
     */
    public function ga_accumulated_report($to_date = ''){
        $from_date = '2017-04-06'; // a fixed date in this case avoids another parameter
        $to_date = empty($to_date)?
                    date('Y-m-d', mktime(0, 0, 0, date("m"), date("d")-1, date("Y"))) :
                    date('Y-m-d',strtotime($to_date));

        // Get Database data in order to query GA 
        $db_records = $this->report_model->get_records($to_date);
        
        // Printing a query log directly to the page (no view involved)
        echo "<pre style='background: #EEE;width:90vw'><b>".count($db_records). " properties</b> found".PHP_EOL;

        // CSV that will contain the data returned by GA queries (first line has the headers)
        $csv_data = "Id;Title;Description;Visits;Submits\r\n";

        // Initial time for determining the duration of GA queries
        $total_time = microtime(true);

        // Cicle the records and query them respectively
        foreach ($db_records as $record) {
            
            // Determine the number of days to be queried
            $from_datetime = new DateTime($from_date);
            $to_datetime = new DateTime($to_date);
            // Add one day by default (the above datetimes can be the same)
            $days_to_query = intval($from_datetime->diff($to_datetime)->days) + 1;

            $gaT = new Ga_thread(
                array(
                    'record_id'=>$prop['record_id'],
                    'from_date'=>$from_date,
                    'to_date'=>$to_date
                )
            );

            // Get the current time before performing a GA query
            $t = microtime(true);
            // Initialize the query
            if($gaT->start()){
                printf($prop['record_id'].": Request took %f seconds to start",
                    microtime(true) - $t);

                // Add a dot each 1/10 of a second
                while ( $gaT->isRunning() ) {
                    echo ".";
                    usleep(100000);
                }

                // `join()` block will only be executed after the query is completed
                if ($gaT->join()) {
                    printf(" and %f seconds to finish receiving".PHP_EOL,
                            microtime(true) - $t);

                    // Data returned by the GA query
                    $gaData = $gaT->data;
                    
                    // Writing a CSV file with database and GA data
                    $csv_data.=
                            $record['record_id'].';"'.
                            trim($record['title']).'";"'.
                            trim($record['description']).'";'.
                            // The accumulated value is returned directly by the GA_thread
                            $gaData['visits'].';'.$gaData['submits']."\r\n";
                } else
                    printf(" and %f seconds to finish, request failed".PHP_EOL,
                            microtime(true) - $t);
            }
            // Await half a second for each record, avoiding overload
            // https://developers.google.com/analytics/devguides/config/mgmt/v3/limits-quotas#general_quota_limits
            usleep(500000);
        }
        printf(PHP_EOL."Requests total time: <b>%f seconds</b></pre>".PHP_EOL,
               microtime(true) - $total_time);

        // Write the CSV file into the "data" directory
        $csvFilePath = "./data/records_stats_accumulated--".$from_date."_".$to_date.".csv";
        if ( ! file_put_contents($csvFilePath, $csv_data)){
            echo "No data wrote to the CSV file.";
        }
    }

}
