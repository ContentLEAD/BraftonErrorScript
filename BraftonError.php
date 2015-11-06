<?php 
/**
 * Brafton Error Class for reporting to Brafton Servers
 *
 * This class provides an interface between both the client CMS and Brafton Servers to report Errors as a result of the
 * Importer Plugin/module.  Critical Errors (E_ERROR) should be reported to Brafton Servers while all other Errors are logged site side.
 *
 * PHP version 5
 *
 *
 * @package    Brafton API Library
 * @author     Deryk King <deryk.king@brafton.com>
 * @version    2.3.1
 * @since      File available since Release 3.0.0
 */

/**
 *
 */
class BraftonErrorReport {
    
    /**
     * Current url location
     *
     * @access private
     * @var string
     */
    private $url;
    /**
     * Encryption key for verification for the error logging api
     *
     * @access private 
     * @var string
     */
    private $e_key;
    /**
     * Url location for error reporting with $e_key as [GET] Parameter
     *
     * @access private 
     * @var string
     */
    private $post_url;
    /**
     * Current section reporting the error
     * 
     * @access private 
     * @var string
     */
    private $section;
    /**
     * current brafton level of severity
     *
     * @access public 
     * @var int
     */
    public $level;
    /**
     * stores value of Mode to be in
     *
     * @access public 
     * @var boolean
     */    
    public $debug;
    /**
     * domain script is running on
     *
     * @access public 
     * @var string
     */
    private $domain;
    
    /**
     * Constructor method 
     *
     * @access public 
     * @param string $api 
     * @param string $brand 
     * @param boolean $debug 
     * @return void
     */
    public function __construct($api, $brand, $debug){
        $this->debug = $debug;
        $this->url = $_SERVER['REQUEST_URI'];
        $this->domain = $_SERVER['HTTP_HOST'];
        $this->api = $api;
        $this->brand = $brand;
        $this->e_key = 'ucocfukkuineaxf2lzl3x6h9';
        $this->post_url = 'http://updater.brafton.com/errorlog/wordpress/error/'.$this->e_key;
        $this->level = 1;
        $this->section = 'error initialize';
        register_shutdown_function(array($this,  'check_for_fatal'));
        set_error_handler(array($this, 'log_error') );
        set_exception_handler(array($this, 'log_exception'));
        ini_set( "display_errors", 0 );
        error_reporting( E_ALL );
    }
     /**
     * Sets the current section reporting the error
     *
     * @access public 
     * @param string $sec 
     */
    public function set_section($sec){
        $this->section = $sec;   
    }
     /**
     * Gets the current Section reporting an error 
     *
     * @access public 
     * @return string
     */
    public function get_section(){
        return $this->section;   
    }
     /**
     * Sets the Brafton level of Severity
     *
     * @access public 
     * @param int $level
     * @return void
     */
    public function set_level($level){
        $this->level = $level;
    }
    //upon error being thrown log_error fires off to throw an exception erro
     /**
     * Exception handler for errors
     *
     * Sets up to create the Exception Object with the appropriate information for handling an Exception as an Error
     *
     * @access public 
     * @param int $num
     * @param string $str 
     * @param string $file
     * @param int $line
     * @param string $content (default: null)
     * @return void
     */
    public function log_error( $num, $str, $file, $line, $context = null )
    {   
        if($str == 'Call to a member function getAttribute() on a non-object'){
            $str = $str. " Couldn't retrieve either (news, comments, categoryDefinition)";
        }
        $this->log_exception( new ErrorException( $str, 0, $num, $file, $line ) );
    }
     /**
     * retrieves the current error log
     *
     * @access private 
     * @return array
     */
    private function b_e_log(){
        //Use this method to get current errorlog from client CMS
        
        return $brafton; 
    }
     /**
     * Check for known minor errors
     *
     * @access public 
     * @param obj $e
     * @return boolean
     */
    public function check_known_errors($e){
        switch(basename($e->getFile())){
            case 'link-template.php':
            return false;
            break;
            case 'post.php':
            return false;
            break;
            case 'class-wp-image-editor-imagick.php':
            return false;
            break;
            case 'translation-management.class.php':
            return false;
            break;
            default:
            return true;
        }
    }
     /**
     * Handles Errors and Exceptions
     *
     * This method must accomplish the following.
     * check if Exeption object severity is set and if it is not set a default level 2.  Prevents Non Vital Errors from being
     * Reported.
     * Check Message for the cron did not run and sets level 1 so it is reported as Vital Error.
     * Checks for known unsolvable however non essential errors so as not to log them or report them.
     * Make a local report via the make_local_report method to log the error for future debugging.
     * Make a Remote report to Brafton (if not on a localhost machine) and severity was level 1
     * Set redirect to previous page if running manually.
     *
     * @access public 
     * @param obj Exception $e
     * @return void
     */
    public function log_exception( Exception $e ){
        $errorLevel = method_exists($e,'getseverity')? $e->getseverity(): 2;
        $errorLevel = $e->getMessage() == 'Article Importer has failed to run.  The cron was scheduled but did not trigger at the appropriate time'? 1 : $errorLevel;
        if ( ($errorLevel == 1) || ($this->debug) && ($this->check_known_errors($e)) ){

            $errorlog = $this->make_local_report($e, $errorLevel);
            
            if($errorLevel == 1){ 
                /**
                 * CMS Specific: Handle how to make a report to Brafton Servers Here.  Be sure to Turn Debug Mode "ON" 
                 * automatically.
                 */
                $append = '&b_error=vital';
                if($this->domain != 'localhost'){
                    $this->send_remote_report($errorlog);
                }
                if(isset($_GET['b_error']) && $_GET['b_error'] == 'vital'){ $append = ''; }
                header("LOCATION:$this->url{$append}");
            }else {
                return;
            }
        }
        return;
    }

    //function for checking if fatal error has occured and trigger the error flow
     /**
     * Checks form fatal Errors E_ERROR/severity=1
     *
     * @access public 
     * @return void
     */
    public function check_for_fatal(){
        $error = error_get_last();
        if ( $error["type"] == E_ERROR )
            $this->log_error( $error["type"], $error["message"], $error["file"], $error["line"] );
    }
     /**
     * Sets Debug Tracing Message
     *
     * Sets up a more robust Message for tracing the progress and stack of the scripts when logging errors to the system.
     *
     * @access public 
     * @param string $msg 
     * @return void
     */
    public function debug_trace($msg){    
        if(!$this->debug){
            return;
        }
        if($this->level > 2){ return; }
        $brafton_error = $this->b_e_log();
        $debug_trace = array(
                'client_sys_time'  => date(get_option('date_format')) . " " . date("H:i:s"),
                'error' => 'Debug Tace : '.$msg['message'].' in '. $msg['file'] . ' on line '. $msg['line'] . ' in section ' . $this->section
                );
        $brafton_error[] = $debug_trace;
        update_option('brafton_e_log', $brafton_error);        
    }
     /**
     * Make a local report of errors
     *
     * This method converts the error Object into an array for storage into the local cms in this method.  It must return the
     * array for later consumption by the remote report method.
     *
     * @access public 
     * @param obj $e
     * @param int $errorLevel 
     * @return array
     */
    public function make_local_report($e, $errorLevel){
        $brafton_error = $this->b_e_log();
            $errorlog = array(
                'Domain'    => $this->domain,
                'API'       => $this->api,
                'Brand'     => $this->brand,
                'client_sys_time'  => date(get_option('date_format')) . " " . date("H:i:s"),
                'error'     => get_class($e).' : '.$errorLevel.' | '.$e->getMessage().' in '.$e->getFile().' on line '.$e->getLine().' brafton_level '.$this->level.' in section '.$this->section
            );
            $brafton_error[] = $errorlog;
            /**
             * wordpress example of storing local report 
             * update_option('brafton_e_log', $brafton_error);
             */
            return $errorlog;
    }
     /**
     * Make a remote report of errors 
     *
     * Remote data must be a json object stored in $_POST['error'].
     * 
     * @access public 
     * @param array $errorlog
     * @return void
     */
    public function send_remote_report($errorlog){
        /**
         * Wordpress example
         * $post_args = array(
            'body' => array(
                'error' => json_encode($errorlog)
            )
        );
        wp_remote_post($this->post_url, $post_args);
        */
    }

}
?>