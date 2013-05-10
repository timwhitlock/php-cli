<?php
/**
 * php-cli
 * @author Tim Whitlock
 * @license MIT
 */
 
 
cli::init(); 


/**
 * Command Line Interface
 */ 
final class cli {
    
    /**
     * @var cli
     */    
    private static $singleton;     

    /**
     * Populated args
     * @var array
     */ 
    private static $args = array();
    
    /**
     * Registered args
     * @var array
     */
    private static $_args = array();
    
    /**
     * Registered flags
     * @var array
     */
    private static $_args_idx = array();
    
    /**
     * current colouring style
     * @var array [ foreground, background ] 
     */    
    private static $_style = '';    
    
    /**
     * Foreground colours
     */    
    const FG_BLACK        = '0;30';    
    const FG_DARK_GREY    = '1;30';
    const FG_BLUE         = '0;34';
    const FG_LIGHT_BLUE   = '1;34';
    const FG_GREEN        = '0;32';
    const FG_LIGHT_GREEN  = '1;32';
    const FG_CYAN         = '0;36';
    const FG_LIGHT_CYAN   = '1;36';
    const FG_RED          = '0;31';
    const FG_LIGHT_RED    = '1;31';
    const FG_PURPLE       = '0;35';
    const FG_LIGHT_PURPLE = '1;35';
    const FG_BROWN        = '0;33';
    const FG_YELLOW       = '1;33';
    const FG_LIGHT_GREY   = '0;37';
    const FG_WHITE        = '1;37';
    
    /**
     * Background colours
     */    
    const BG_BLACK   = '40';
    const BG_RED     = '41';
    const BG_GREEN   = '42';
    const BG_YELLOW  = '43';
    const BG_BLUE    = '44';
    const BG_MAGENTA = '45';
    const BG_CYAN    = '46';
    const BG_GREY    = '47';

    /**
     * Font styles
     */
    const TEXT_BOLD = '1';    
    const TEXT_UNDERLINE = '4';    

    /**
     * private constructor ensures only one instance of self
     */    
    private function __construct(){

        switch( PHP_SAPI ) {
        // Ideally we want to be runnning as CLI
        case 'cli':
            break;
        // Special conditions to ensure CGI runs as CLI
        case 'cgi':
            // Ensure resource constants are defined
            if( ! defined('STDERR') ){
                define( 'STDERR', fopen('php://stderr', 'w') );
            }
            if( ! defined('STDOUT') ){
                define( 'STDOUT', fopen('php://stdout', 'w') );
            }
            break;
        default:
            echo "Command line only\n";
            exit(1);
        }
        
        // handle PHP errors via our formatter
        set_error_handler( array( $this, 'on_trigger_error' ) );
        set_exception_handler( array( $this, 'on_uncaught_exception' ) );
                 
        
        // parse command line arguments from argv global
        global $argv, $argc;

        // first cli arg is always current script.
        // Each command line argument may take following forms:
        //  1. "Any single argument", no point parsing this unless it follows #2 below
        //  2. "-aBCD", one or more switches, parse into 'a'=>true, 'B'=>true, and so on
        //  3. "-a value", flag used with following value, parsed to 'a'=>'value'
        //  4. "--longoption", GNU style long option, parse into 'longoption'=>true
        //  5. "--longoption=value", as above, but parse into 'longoption'=>'value'
        //  6."any variable name = any value" 
        for( $i = 1; $i < $argc; $i++ ){
            $arg = $argv[$i];
            $pair = explode( '=', $arg, 2 );
            if( isset($pair[1]) ){
                $name = trim( $pair[0] );
                if( strpos($name,'--') === 0 ){
                    // #5. trimming "--" from option, tough luck if you only used one "-"
                    $name = trim( $name, '-' );
                }
                // else is #6, any pair
                $name and self::$args[$name] = trim( $pair[1] );
            }
            
            else if( strpos($arg,'--') === 0 ){
                // #4. long option, no value
                $name = trim( $arg, "-\n\r\t " );
                $name and self::$args[ $name ] = true;
            }
            
            else if( $arg && $arg{0} === '-' ){
                $flags = preg_split('//', trim($arg,"-\n\r\t "), -1, PREG_SPLIT_NO_EMPTY );
                foreach( $flags as $flag ){
                    self::$args[ $flag ] = true;
                }
                // leave $flag set incase a value follows.
                continue;
            }

            // else is a standard argument. use as value only if it follows a flag, e.g "-a apple"
            else if( isset($flag) ){
                self::$args[ $flag ] = trim( $arg );
            }

            // dispose of last flag
            unset( $flag );
            // next arg 
        }
        
    }
    
    
    /**
     * Initialize CLI environment
     * @return void
     */
    public static function init() {
        self::$singleton or self::$singleton = new cli;
    }   
    
    
    /**
     * Register a command line argument
     */
    public static function register_arg( $short, $long = '', $desc = '', $mandatory = false ){
        $i = count(self::$_args);
        self::$_args[$i] = func_get_args();
        $short and self::$_args_idx[$short] = $i;
        $long  and self::$_args_idx[$long]  = $i;
    }
    
    
    /**
     * Check arguments registered with register_args()
     */
    public static function validate_args(){
       // exit if -h or --help is non-empty
       if( self::arg('h', self::arg('help') ) ){
           self::exit_help();
       }
       // exit if a mandatory argument not found
       foreach( self::$_args as $r ){
           if( ! $r[3] ){
               continue; // <- not mandatory
           }
           list( $short, $long, $desc ) = $r;
           if( ( $short && is_null(self::arg($short)) ) || ( $long && is_null(self::arg($long)) ) ){
               $name = $long or $name = $short;
               self::err("Argument required '%s' (%s)", $name, $desc );
               self::exit_help();
           }
       }
       // exit if invalid argument found. This helps avoid mistakes
       foreach( self::$args as $flag => $value ){
           if( ! isset(self::$_args_idx[$flag]) ){
               self::err("Unexpected argument '%s'", $flag);
               self::exit_help();
           }
       }    
    }
    
    
    /**
     * exit with usage dump
     */
    public static function exit_help( $args = '' ){
       $usage = 'Usage: php -f '.basename(self::arg(0)).' --';
       if( $args ){
           $usage .= ' '.$args;
       }
       if( self::$_args ){
           $usage .= ' <arguments> ';
           $table = array();
           $widths = array( 0, 0, 0 );
           foreach( self::$_args as $r => $row ){
               $short = ($row[3]?'* ':'  ') . ( $row[0] ? ' -'.$row[0] : '' );
               $long  = $row[1] ? '  --'.$row[1] : '';
               $desc  = $row[2] ? '   '.$row[2] : '';
               $widths[0] = max( $widths[0], strlen($short) );
               $widths[1] = max( $widths[1], strlen($long) );
               $widths[2] = max( $widths[2], strlen($desc) );
               $table[] = array( $short, $long, $desc );
           }
           foreach( $table as $row ){
               $usage .= "\n";
               foreach( $row as $i => $val ){
                   $usage .= str_pad($val,$widths[$i]);
               }
           }
       }
       self::stderr( $usage."\n" );
       exit(0);
    }
    
    
    
    /**
     * Get command line argument
     * @param int|string argument name or index
     * @param string optional default argument to return if not present
     * @return string
     */ 
    public static function arg( $a, $default = null ){
        if( is_int($a) ){
            global $argv;
            // note: arg(0) will always be the script path
            return isset($argv[$a]) ? $argv[$a] : $default;
        }
        if( isset(self::$args[$a]) ){
            return self::$args[$a];
        }
        // not found. try aliases
        if( isset(self::$_args_idx[$a]) ) {
            $r = self::$_args[ self::$_args_idx[$a] ];
            // trying short when long not found
            if( $r[0] && $a !== $r[0] && isset(self::$args[$r[0]]) ){
                return self::$args[$r[0]];
            }
            // trying long when short not found
            else if( $r[1] && $a !== $r[1] && isset(self::$args[$r[1]]) ){
                return self::$args[$r[1]];
            }
        }
        // give up
        return $default;
    }
    
    
    
    /**
     * Export all named arguments
     */
    public static function export_args(){
        $args = array();
        foreach( self::$args as $key => $val ){
            $key and $val and $args[$key] = $val;
        }
        return $args;
    }    
    


    /**
     * Single output function
     */    
    private static function fwrite( $pipe, $data ){
        if( self::$_style ){
            $data = self::$_style.$data."\033[0m";
        }
        return fwrite( $pipe, $data );
    }    

    
    
    /**
     * Write anything to stdout
     * @param string printf style formatter
     * @param ... arguments to printf
     * @return void
     */
    public static function stdout( $data ){
        if( func_num_args() > 1 ){
            $args = func_get_args();
            $data = call_user_func_array( 'sprintf', $args );
        }
        return self::fwrite( STDOUT, $data );
    }



    /**
     * Print to stderr
     * @param string printf style formatter
     * @param ... arguments to printf
     * @return void
     */
    public static function stderr( $data ){
        if( func_num_args() > 1 ){
            $args = func_get_args();
            $data = call_user_func_array( 'sprintf', $args );
        }
        return self::fwrite( STDERR, $data );
    }
    
    
    /**
     * 
     */
    public static function death( $message = '' ){
       if( $message ){
           $args = func_get_args();
           $args[0] = '['.date('D M d H:i:s Y').'] '.basename(self::arg(0)).': Fatal: '.trim( $args[0], "\n" )."\n";
           self::style( self::FG_WHITE, self::BG_RED );
           call_user_func_array(array(__CLASS__,'stderr'), $args );
       }
       exit(1);
    }

    
    
    /**
     * Print single line to stdout
     */
    public static function log( $message ){
        $args = func_get_args();
        $args[0] = '['.date('D M d H:i:s Y').'] '.basename(self::arg(0)).': '.trim( $args[0], "\n" )."\n";
        static $stdout = array( __CLASS__, 'stdout' );
        return call_user_func_array( $stdout, $args );
    }    
    
    
    /**
     * Print single error line
     */        
    public static function err( $message ){
        $args = func_get_args();
        $args[0] = '['.date('D M d H:i:s Y').'] '.basename(self::arg(0)).': Error: '.trim( $args[0], "\n" )."\n";
        static $stdout = array( __CLASS__, 'stderr' );
        $style = self::$_style;
        self::style( self::FG_WHITE, self::BG_RED );
        call_user_func_array( $stdout, $args );
        self::$_style = $style;
    }    
    
    
    
    /**
     * 
     */
    public static function style( $fg = null, $bg = null ){
        self::$_style = '';
        foreach( func_get_args() as $num ){
            if( $num ){
                self::$_style .= "\033[".$num.'m';
            }
        }
    }
    
    
    
    
    /**
     * @internal
     */
    public function on_trigger_error( $type, $message, $file = '', $line = 0, array $args = array() ){
        static $types = array (
            E_WARNING            => 'Warning',
            E_NOTICE             => 'Notice',
            E_CORE_WARNING       => 'Core Warning',
            E_COMPILE_WARNING    => 'Compile Warning',
            E_USER_ERROR         => 'Error',
            E_USER_WARNING       => 'Warning',
            E_USER_NOTICE        => 'Notice',
            E_STRICT             => 'Runtime Notice',
            E_RECOVERABLE_ERROR  => 'Recoverable Error',
        );
        $message = sprintf (
            '[%s] %s: %s: %s in %s#%u',
            date('D M d H:i:s Y'),
            basename( self::arg(0) ), 
            $types[$type], 
            $message, 
            basename($file), 
            $line
        );
        self::err( $message );
        if( $type & ( E_USER_ERROR | E_RECOVERABLE_ERROR ) ){
            exit(1);
        }
    }
    
    
    
    /**
     * @internal
     */
    public function on_uncaught_exception( Exception $Ex ){
        $message = 'Uncaught '.get_class($Ex).': '.$Ex->getMessage();
        $this->on_trigger_error( E_RECOVERABLE_ERROR, $message, $Ex->getFile(), $Ex->getLine() );
    } 


    
} 



 
