<?php

/**
 * The Log class provides logging facilities.
 * Usually, a default logger is instantiated at initialization:
 *   <pre>Log::$usuallogger = new Logger('/var/log/php_log', Log::INFO, Log::NEVER);</pre>
which can then be used through the static methods (fail, warn, backtrace, info, message, debug):
 *   <pre>Log::message("Fasten seatbelts!");</pre>
 *
 * The logger can log to two places:
 *   - A log file
 *   - Print as HTML comment 
 * The three constructor parameters give (1) the logfile location, (2) the threshold for logging to the logfile and (3) the threshold for logging to the output.   
 *   
 * Instead of a string message, the log methods accept exception objects as well. In this case, the backtrace of the exception will be shown in the log. So you can do:
 *    <pre>
 *    try {
 *        // Something
 *    } catch (Exception $e) {
 *        Log::fail($e);
 *        die(":-(");
 *    }
 *    </pre>
 * To get the exception and its trace in the log.
*/
class Log {
    /** This never happens */
    const NEVER = 1000;
    /** Something's extremely wrong */
    const FAIL = 100;       
    /** Unusual state */
    const WARN = 90;
    /** Informational messages (Logging usually stops a this level) */        
    const INFO = 50;
    /** Messages shown to user */
    const MESSAGE = 40;     
    /** Output useful in debugging */
    const DEBUG = 20;
    /** SQL queries */
    const SQL = 15;
    /** Generate a backtrace */
    const BACKTRACE = 10;  
    /** Everything goes */
    const ALL = 0;         
    
    static $usuallogger = false; // Assign a logger here so that Log::info("Seatbelts fastened.") works too.

    static function fail($msg) { if (self::$usuallogger) self::$usuallogger->fail($msg); }
    static function warn($msg) { if (self::$usuallogger)self::$usuallogger->warn($msg); }
    static function backtrace($msg) { if (self::$usuallogger)self::$usuallogger->backtrace($msg); }
    static function info($msg) { if (self::$usuallogger)self::$usuallogger->info($msg); }
    static function message($msg) { if (self::$usuallogger)self::$usuallogger->message($msg); }
    static function debug($msg) { if (self::$usuallogger)self::$usuallogger->debug($msg); }
    static function sql($msg) {if (self::$usuallogger) self::$usuallogger->sql($msg); }
    
    static function disable() {self::$usuallogger->disable();}
    static function enable() {self::$usuallogger->enable();}
    
    // backtrace function shamelessly ripped off of a php.net comment
    static function prettybacktrace($backtrace = false) {
        // Get a backtrace from here if none was given
        if (!$backtrace) {
            $backtrace = debug_backtrace();
            // Ignore the call to this function in the backtrace
            $backtrace = array_slice($backtrace, 1);
        }

        $output = "";
        
        foreach ($backtrace as $bt) {
            $args = '';
            if (@is_array($bt['args'])) {
                foreach ($bt['args'] as $a) {
                    if (!empty($args)) {
                        $args .= ', ';
                    }
                    switch (gettype($a)) {
                    case 'integer':
                    case 'double':
                        $args .= $a;
                        break;
                    case 'string':
                        $a = htmlspecialchars(substr($a, 0, 64)).((strlen($a) > 64) ? '...' : '');
                        $args .= "\"$a\"";
                        break;
                    case 'array':
                        $args .= 'Array(#'.count($a).')';
                        break;
                    case 'object':
                        $args .= 'Object('.get_class($a).')';
                        break;
                    case 'resource':
                        $args .= 'Resource('.strstr($a, '#').')';
                        break;
                    case 'boolean':
                        $args .= $a ? 'True' : 'False';
                        break;
                    case 'NULL':
                        $args .= 'NULL';
                        break;
                    default:
                        $args .= 'Unknown($a)';
                    }
                }
            }
            @$output .= " === {$bt['class']}{$bt['type']}{$bt['function']}($args) in file '{$bt['file']}' on line {$bt['line']}\n";
        }
        
        $output .= " ### {$_SERVER['REMOTE_ADDR']} requested {$_SERVER['REQUEST_URI']}\n";
        
        return $output;
    }
}

class Logger {

    /** Write log messages to this file */
    var $file;

    /** Log messages to file only if they're at least this level of severity */
    var $loglevel;

    /** echo output in HTML comments from this level */
    var $echolevel;

    /** Log to FirePHP from this level */
    var $firelevel;

    var $enabled = true;

    function fail($msg) { $this->log(Log::FAIL, "FAIL", $msg); }
    function warn($msg) { $this->log(Log::WARN, "WARN", $msg); }
    function info($msg) { $this->log(Log::INFO, "INFO", $msg); }
    function message($msg) { $this->log(Log::MESSAGE, "MESSAGE", $msg); }
    function debug($msg) { $this->log(Log::DEBUG, "DEBUG", $msg); }
    function sql($msg) { $this->log(Log::SQL, "SQL", $msg); }
    function backtrace($msg) { $this->log(Log::BACKTRACE, "BACKTRACE", $msg); }

    function __construct($file = false, $loglevel = Log::INFO, $echolevel = Log::NEVER, $firelevel = Log::NEVER) {
        if (is_string($loglevel)) $loglevel = constant("Log::$loglevel");
        if (is_string($echolevel)) $echolevel = constant("Log::$echolevel");
        if (is_string($firelevel)) $firelevel = constant("Log::$firelevel");

        $this->loglevel = $loglevel;
        $this->echolevel = $echolevel;
        $this->firelevel = $firelevel;
        $this->file = $file;
        $this->debug("Initialized logging (file: $file, loglevel: $loglevel, echolevel: $echolevel)");
    }

    function disable() {
        $this->enabled = false;
    }

    function enable() {
        $this->enabled = true;
    }

    function log($level, $leveltext, $msg) {
        // Run only if output will be used
        if ($level >= min($this->loglevel, $this->echolevel, $this->firelevel) && $this->enabled) {
            $logmessage = "";
            $backtrace = "";
            $showrequest = false; // Maybe show _REQUEST array
            // Process exceptions
            if ($msg instanceof Exception || $msg instanceof Error) {
                $excmessage = method_exists($msg, 'getDetailMessage') ? $msg->getDetailMessage() : $msg->getMessage();
                $logmessage = $leveltext.": ".$excmessage."\n".Log::prettybacktrace($msg->getTrace());
            } else {
                $logmessage = $leveltext.": ". print_r($msg,true)."\n";
                // Generate a backtrace if it was requested or in severe cases
                if ($level == Log::BACKTRACE || $level >= Log::FAIL) {
                    $backtrace = "\n".Log::prettybacktrace();
                }
            }

            if (!headers_sent() && $level >= $this->firelevel) {
                $this->log_firephp($msg,$level);
            }
            
            // Append request variables to log message if so desired
            if ($showrequest && count($_REQUEST) > 0) $logmessage .= "REQUEST ".print_r($_REQUEST, true);

            // Write to logfile
            if ($this->file && $level >= $this->loglevel) {
                $logfile = fopen($this->file, 'a');
                if ($logfile) {
                    fwrite($logfile, date("d.m.Y-H:i:s").": $logmessage$backtrace");
                    fclose($logfile);
                }
            }

            // Print message as HTML comment
            if ($level >= $this->echolevel) {
                // Some messages contain double dashes which terminate HTML comments, insert zero width space
                $zeroed = '-​-'; // three chars, one you don't see
                $esc = str_replace('--', $zeroed, str_replace('--', $zeroed, "$logmessage$backtrace")); 
                echo "<!--\n".$esc." -->";
            }
        }
    }
    
    function log_firephp($msg, $level) {
        if (!isset($this->firephp)) {
            require_once "lib/FirePHP.class.php";
            $this->firephp = FirePHP::getInstance(true);
            $this->firephp->setOptions(array(
                'maxObjectDepth' => 5,
                'maxArrayDepth' => 5,
                'maxDepth' => 10,
                'useNativeJsonEncode' => false, // Using native json_encode() lead to recursion warnings when logging traces
                'includeLineNumbers' => true
            ));
        }

        $firebug_level = get(array(
            Log::FAIL      => FirePHP::ERROR,
            Log::WARN      => FirePHP::WARN,
            Log::INFO      => FirePHP::INFO,
            Log::MESSAGE   => FirePHP::INFO,
            Log::DEBUG     => FirePHP::LOG,
            Log::SQL       => FirePHP::DUMP,
            Log::BACKTRACE => FirePHP::TRACE,
        ), $level, FirePHP::LOG);
        $this->firephp->fb($msg, $firebug_level);
    }
}

