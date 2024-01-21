<?php

abstract class parser {

    /**
     * actual parsing of the data
     * @return array
     */
    abstract function parse($string, $static_data = array(), $debug=false);

    public static function convertDate($inputFormat, $targetFormat, $inputDate)
	{
		$dateObject = DateTime::createFromFormat($inputFormat, $inputDate);
		return $dateObject->format( $targetFormat);
	}
    
}