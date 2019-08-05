<?php

namespace Scar\Doc;
/**
 * 注解解析类
 *
 * Parses the PHPDoc comments for metadata. Inspired by Documentor code base
 *
 * @category   Framework
 * @package    restler
 * @subpackage helper
 * @author     Murray Picton <info@murraypicton.com>
 * @author     R.Arul Kumaran <arul@luracast.com>
 * @copyright  2010 Luracast
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @link       https://github.com/murraypicton/Doqumentor
 */
class DocParser {
    private $params = array ();
    function parse($doc = '') {
        $this->params = [];
        if ($doc == '') {
            return $this->params;
        }
        // Get the comment
        if (preg_match ( '#^/\*\*(.*)\*/#s', $doc, $comment ) === false)
            return $this->params;
        $comment = trim ( $comment [1] );
        // Get all the lines and strip the * from the first character
        if (preg_match_all ( '#^\s*\*(.*)#m', $comment, $lines ) === false)
            return $this->params;
        $this->parseLines ( $lines [1] );
        return $this->params;
    }
    private function parseLines($lines) {
        foreach ( $lines as $line ) {
            $parsedLine = $this->parseLine ( $line ); // Parse the line

            if ($parsedLine === false && ! isset ( $this->params ['description'] )) {
                if (isset ( $desc )) {
                    // Store the first line in the short description
                    $this->params ['description'] = implode ( PHP_EOL, $desc );
                }
                $desc = array ();
            } elseif ($parsedLine !== false) {
                $desc [] = $parsedLine; // Store the line in the long description
            }
        }
        if (isset($desc) && !empty ( $desc )){
            $desc = implode ( ' ', $desc );

            $this->params ['long_description'] = $desc;
        }
    }
    private function parseLine($line) {
        // trim the whitespace from the line
        $line = trim ( $line );

        if (empty ( $line ))
            return false; // Empty line

        if (strpos ( $line, '@' ) === 0) {
            if (strpos ( $line, ' ' ) > 0) {
                // Get the parameter name
                $param = substr ( $line, 1, strpos ( $line, ' ' ) - 1 );
                $value = substr ( $line, strlen ( $param ) + 2 ); // Get the value
            } else {
                $param = substr ( $line, 1 );
                $value = '';
            }
            // Parse the line and return false if the parameter is valid
            if ($this->setParam ( $param, $value ))
                return false;
        }

        return $line;
    }
    private function setParam($param, $value) {


        if ($param == 'class')
            list ( $param, $value ) = $this->formatClass ( $value );

        if($param=='param'){
	        $value = $this->formatParamOrReturn ( $value );
	        $this->setParamItemValue( $param,$value );
        }else{
	        if (!isset( $this->params [$param] )) {
		        $this->params [$param] = $value;
	        } else {
		        if(!is_array($this->params[$param])){
			        $temp = $this->params[ $param ];
			        $this->params[ $param ]=array($temp,$value);
		        }else{
			        array_push($this->params[ $param ],$value);
		        }

	        }
        }

	    return true;
    }

	private function setParamItemValue($param, $value ) {
		if(!isset($this->params[$param])){
			$this->params[ $param ] = [];
		}
		$this->params[ $param ][] = $value;
	}

    private function formatClass($value) {
        $r = preg_split ( "[\(|\)]", $value );
        if (is_array ( $r )) {
            $param = $r [0];
            parse_str ( $r [1], $value );
            foreach ( $value as $key => $val ) {
                $val = explode ( ',', $val );
                if (count ( $val ) > 1)
                    $value [$key] = $val;
            }
        } else {
            $param = 'Unknown';
        }
        return array (
            $param,
            $value
        );
    }

	/**
	 * 参数类型处理
	 * @param $string
	 *
	 * @return string
	 */
    private function formatParamOrReturn($string) {
	    $arr=explode( ' ', $string, 3 );
	    $re = [];
	    if(count($arr)==1){
		    $re['variable'] = $arr[0];
	    }else{
		    isset($arr[0])&&$re['type'] = $arr[0];
		    isset($arr[1])&&$re['variable'] = $arr[1];
		    isset($arr[2])&&$re['description'] = $arr[2];
	    }
	    return $re;
    }
}