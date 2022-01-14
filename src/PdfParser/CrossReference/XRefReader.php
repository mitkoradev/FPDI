<?php

/**
 * This file is part of FPDI
 *
 * @package   setasign\Fpdi
 * @copyright Copyright (c) 2020 Setasign GmbH & Co. KG (https://www.setasign.com)
 * @license   http://opensource.org/licenses/mit-license The MIT License
 */

namespace setasign\Fpdi\PdfParser\CrossReference;

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;

/**
 * Class XRefReader
 *
 * This reader allows a very less overhead parsing of single entries of the cross-reference, because the main entries
 * are only read when needed and not in a single run.
 */
class XRefReader extends AbstractReader implements ReaderInterface
{

    /**
     * Data of subsections.
     *
     * @var array
     */
    protected $map;

	
    protected $trailer;
	
    /**
     * XRefReader constructor.
     *
     * @param PdfParser $parser
     * @throws CrossReferenceException
     */
    public function __construct(PdfParser $parser,$stream)
    {
		//var_dump($stream->value);
		$this->map=[];

        $this->read($stream);
        parent::__construct($parser);
    }

    /**
     * Get all subsection data.
     *
     * @return array
     */
    public function getSubSections()
    {
        return $this->subSections;
    }

    /**
     * @inheritdoc
     */
    public function getOffsetFor($objectNumber)
    {
		if(isset($this->map[$objectNumber])&&$this->map[$objectNumber][0]==1)
			return $this->map[$objectNumber][1];
		else 
			return false;
    }

    public function getDataFor($objectNumber)
    {
		if(isset($this->map[$objectNumber]))
			return $this->map[$objectNumber];
		else 
			return false;
    }

    /**
     * Read the cross-reference.
     *
     * This reader will only read the subsections in this method. The offsets were resolved individually by this
     * information.
     *
     * @throws CrossReferenceException
     */
    protected function read($stream)
    {
		$w = PdfDictionary::get($stream->value, 'W');
		$index = PdfDictionary::get($stream->value, 'Index');
		$size = PdfDictionary::get($stream->value, 'Size');
		$unfiltered=$stream->getUnfilteredStream();
		
        $this->trailer = $stream->value;
		
		
		//var_dump($w->value[0]->value);
		//var_dump($w->value[1]->value);
		//var_dump($w->value[2]->value);
		//var_dump($size->value);
		//var_dump($unfiltered);
		
		$size=$size->value;
		
		$parts=[$w->value[0]->value,$w->value[1]->value,$w->value[2]->value];
		
		$line=$parts[0]+$parts[1]+$parts[2];
		//print_r($parts);
		//echo '<br/>';
		
		$prev=0;
		
		$base = 0;
		
		if(!empty($index->value))
		{
			$base=$index->value[0]->value;
			$size=$index->value[1]->value;
		}
		
		for($i=0;$i<$size;$i++)
		{
			$vals=[];
			$offs=0;
			for($j=0;$j<count($parts);$j++)
			{
				$val=0;
				for($k=0;$k<($parts[$j]);$k++)
				{
					//echo ord($unfiltered[$line*$i+$offs]).' ';
					$val=$val*256+ord($unfiltered[$line*$i+$offs]);
					$offs++;
					
				}
				
				$vals[]=$val;
			}
			//print_r($vals);
			//echo $vals[0].' '.$vals[1].' '.$vals[2].' '.'<br/>';
			
			$this->map[($base+$i)]=$vals;
		}
    }

    /**
     * Fixes an invalid object number shift.
     *
     * This method can be used to repair documents with an invalid subsection header:
     *
     * <code>
     * xref
     * 1 7
     * 0000000000 65535 f
     * 0000000009 00000 n
     * 0000412075 00000 n
     * 0000412172 00000 n
     * 0000412359 00000 n
     * 0000412417 00000 n
     * 0000412468 00000 n
     * </code>
     *
     * It shall only be called on the first table.
     *
     * @return bool
     */
    public function fixFaultySubSectionShift()
    {
        $subSections = $this->getSubSections();
        if (\count($subSections) > 1) {
            return false;
        }

        $subSection = \current($subSections);
        if ($subSection[0] != 1) {
            return false;
        }

        if ($this->getOffsetFor(1) === false) {
            foreach ($subSections as $offset => list($startObject, $objectCount)) {
                $this->subSections[$offset] = [$startObject - 1, $objectCount];
            }
            return true;
        }

        return false;
    }
	
	
	
    /**
     * Get the trailer dictionary.
     *
     * @return PdfDictionary
     */
    public function getTrailer()
    {
        return $this->trailer;
    }

    /**
     * Read the trailer dictionary.
     *
     * @throws CrossReferenceException
     * @throws PdfTypeException
     */
    protected function readTrailer()
    {
       

    }
}
