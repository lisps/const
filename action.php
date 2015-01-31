<?php
/**
 * DokuWiki Plugin const (Action Component) 
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps    
 */

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

require_once(DOKU_PLUGIN . 'action.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class action_plugin_const extends DokuWiki_Action_Plugin {
    /**
     * return variable 
     */
    private $autoindexer = 0;

    
    /**
     * offsets-at-character-position. recorded as tuples page:{charpos,offset}, where charpos is the
     * position in the MODIFIED data, not the position in the original data, and offset is the
     * CUMULATIVE offset at that position, not the offset relative to the previous location.
     */
    private $offsets = array();
    
    //hook before rendering starts
    function register( Doku_Event_Handler $controller) {
        $controller->register_hook('PARSER_WIKITEXT_PREPROCESS', 'BEFORE', $this, '_doreplace');
        $controller->register_hook('PARSER_HANDLER_DONE', 'BEFORE', $this, '_fixsecedit');
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, '_cache_control');
    }
    
    
    function _replacecallback($hits) {
        return ($this->autoindexer++);
    }
    
    //trigger
    function _doreplace(&$event, $param) {
        global $ID;
        
        require_once(dirname(__FILE__) . "/class.evalmath.php");
        $math = new evalmath();
        
        $this->autoindexer = 0;
        $invalidate = false;
        
        $original = $event->data;
        $wikified = $original;
        
        //catch anonymous access
        $username=isset($_SERVER['REMOTE_USER'])?$_SERVER['REMOTE_USER']:"anonymous";
        
        //get const definitions
        $data = array();
        if (preg_match('/<const[^>]*>([^<]*)<\/const>/', $event->data, $data) > 0) {
            //split entries
            $data = array_pop($data);
            $data = preg_split('/[\r\n]+/', $data, -1, PREG_SPLIT_NO_EMPTY);
            
            //process wiki-data
            $autoindex = 0;
            foreach ($data as $entry) {
                //normal const
                $item = explode("=", trim($entry));
                if (count($item) === 2) {
                    //special string-replace
                    switch ($item[1]) {
                        case "%USER%":
                            $item[1] = $username;
                            $invalidate = true;
                            break; //pagename
                        case "%ID%":
                            $item[1] = noNS(cleanID(getID()));
                            break; //pagename
                        case "%NAMESPACE%":
                            $item[1] = getNS(cleanID(getID()));
                            break; //namespace
                        case "%RANDOM%":
                            $item[1] = strval(rand());
                            $invalidate = true;
                            break; //random number
                        case "%YEAR%":
                            $item[1] = date("Y");
                            break; //current year
                        case "%MONTH%":
                            $item[1] = date("m");
                            break; //current month
                        case "%MONTHNAME%":
                            $item[1] = date("F");
                            break; //current month
                        case "%WEEK%":
                            $item[1] = date("W");
                            $invalidate = true;
                            break; //current week (iso)
                        case "%DAY%":
                            $item[1] = date("d");
                            $invalidate = true;
                            break; //current day
                        case "%DAYNAME%":
                            $item[1]  = date("l");
                            $invalidate = true;
                            break; //current day
                        case "%AUTOINDEX%":
                            $item[1] = "%%INDEX#" . (++$autoindex) . "%%"; //special automatic indexer
                            break;
                        default:
                            $item[1] = trim($item[1]);
                            break;
                    } 
                    
                    //replace in wiki
                    $wikified = str_replace("%%" . trim($item[0]) . "%%", $item[1], $wikified);
                    
                    //load evaluator
                    @$math->evaluate($item[0]."=".$item[1]);
                } else {
                    //evaluate expression
                    $item = explode(":", $entry);
                    if (count($item) === 2) {
                        $wikified = str_replace("%%" . trim($item[0]) . "%%", @$math->evaluate($item[1]), $wikified);
                    }
                }
            }
            
            //autoindex?
            while ($autoindex > 0) {
                $this->autoindexer = 1;
                //replace all
                $wikified    = preg_replace_callback("|%%INDEX#" . $autoindex . "%%|", array(
                    $this,
                    "_replacecallback"
                ), $wikified);
                $autoindex--;
            }
            
            $event->data = $wikified;
            
            $original = explode("\n", $original);
            $wikified = explode("\n", $wikified);
            
            
            $this->offsets[$ID] = array();
            // fill offset array to deal with section editing issues
            for ($l = 0; $l < count($wikified); $l++) {
                // record offsets at the start of this line
                $this->offsets[$ID][] = array(
                    'pos' => $char_pos,
                    'offset' => $text_offset
                );
                // calculate position / offset for next line
                $char_pos += strlen($wikified[$l]) + 1;
                $text_offset += strlen($wikified[$l]) - strlen($original[$l]);
                //echo '(' . $char_pos . '/' . $text_offset . ')' . ' ';
            }
        }

        
        //save invalidation info to metadata            
        p_set_metadata($ID, array(
            'plugin_const' => array(
                'invalidate' => $invalidate
            )
        ), false, true);
    }
    
    /**
     * force cache invalidation for certain constants
     */
    function _cache_control(&$event, $param) {
        global $conf;
        
        $cache =& $event->data;
        
        if ((isset($cache->page) === true) && ($cache->mode === "i")) {
            //cache purge requested?
            $const = p_get_metadata($cache->page, 'plugin_const');
            
            //force initial purge
            if (!isset($const['invalidate'])) {
                $const['invalidate'] = true;
            }
            
            $cache->depends["purge"] = $const["invalidate"];
        }
    }
    
    /**
     * modifying the raw data has as side effect that the sectioning is based on the
     * modified data, not the original. This means that after processing, we need to
     * adjust the section start/end markers so that they point to start/end positions
     * in the original data, not the modified data. 
     */
    function _fixsecedit(&$event, $param) {
        $calls =& $event->data->calls;
        $count = count($calls);

        // iterate through the instruction list and set the file offset values
        // back to the values they would be if no const syntax has been added by this plugin
        for ($i = 0; $i < $count; $i++) {
            if (in_array($calls[$i][0],array(
            		'section_open', 
            		'section_close', 
            		'header', 
            		'p_open',
            		//'table_close',
            		//'table_open',
            	))) {
                $calls[$i][2] = $this->_convert($calls[$i][2]);
            }
            if (in_array($calls[$i][0],array('header','table_open'))) {
                $calls[$i][1][2] = $this->_convert($calls[$i][1][2]);
            }
            if(in_array($calls[$i][0],array('table_close'))) {
            	$calls[$i][1][0] = $this->_convert($calls[$i][1][0]);
            }
            // be aware of headernofloat plugin
            if ($calls[$i][0] === 'plugin' && $calls[$i][1][0] === 'headernofloat') {
                $calls[$i][1][1]['pos'] = $this->_convert($calls[$i][1][1]['pos']);
                $calls[$i][2] = $this->_convert($calls[$i][2]);
            }
            //if($calls[$i][0] == 'table_close')
        }
    }
    
    /**
     * Convert modified raw wiki offset value pos back to the unmodified value
     */
    function _convert($pos) {
        global $ID;
        if(!array_key_exists($ID,$this->offsets)) return $pos;
        // find the offset that applies to this character position
        $offset = 0;
        foreach ($this->offsets[$ID] as $tuple) {
            if ($pos >= $tuple['pos']) {
                $offset = $tuple['offset'];
            } else {
                break;
            }
        }
        
        // return corrected position
        return $pos - $offset;
    }
}
