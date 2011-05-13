<?php

class JSONEncoder {

	/** @var $additionalAttributes array Set of additional attributes for special cases*/
	var $additionalAttributes;

	/**
	* Set the additionalAttributes array
	* @param $additionalAttributes array
	*/
	function setAdditionalAttributes($additionalAttributes) {
		$this->additionalAttributes = $additionalAttributes;
	}

	/**
	* Construct a JSON string to use for AJAX communication
	* @return string
	*/
	function getString() {
		$jsonString = "{\"status\": \"\"";
			if(isset($this->additionalAttributes)) {
				foreach($this->additionalAttributes as $key => $value) {
					$jsonString .= ", \"$key\": " . $this->_json_encode($value);
				}
			}
		$jsonString .= "}";

		return $jsonString;
	}

	/**
	 * encode a string for use with JSON
	 * Thanks to: http://usphp.com/manual/en/function.json-encode.php#82904
	 */
	function _json_encode($a = false) {
		if (function_exists('json_encode')) {
			return json_encode($a);
		} else {
			if (is_null($a)) return 'null';
			if ($a === false) return 'false';
			if ($a === true) return 'true';
			if (is_scalar($a)){
				if (is_float($a)) {
					// Always use "." for floats.
					return floatval(str_replace(",", ".", strval($a)));
				}
				if (is_string($a)) {
					static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\
f', '\"'));
					return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
				}
				else {
					return $a;
				}
			}
			$isList = true;
			for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
				if (key($a) !== $i) {
					$isList = false;
					break;
				}
			}
			$result = array();
			if ($isList) {
				foreach ($a as $v) $result[] = $this->_json_encode($v);
				return '[' . join(',', $result) . ']';
			}
			else {
				foreach ($a as $k => $v) $result[] = $this->_json_encode($k).':'.$this->_json_encode($v);
				return '{' . join(',', $result) . '}';
			}
		}
	}
}
?>