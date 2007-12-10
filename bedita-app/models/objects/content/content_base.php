<?php
/**
 *
 * PHP versions 5
 *
 * CakePHP :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright (c)	2006, Cake Software Foundation, Inc.
 *								1785 E. Sahara Avenue, Suite 490-204
 *								Las Vegas, Nevada 89104
 *
 * @filesource
 * @copyright		Copyright (c) 2007
 * @link			
 * @package			
 * @subpackage		
 * @since			
 * @version			
 * @modifiedby		
 * @lastmodified	
 * @license
 * @author 		giangi giangi@qwerg.com	
 * 		
 * 				Esprime  le relazioni tra oggetti di tipo contenuto:
 * 				oggetti associati per lingua, obj immagini, multimedia, e allegati		
*/
class ContentBase extends BEAppModel
{
	var $name = 'ContentBase';

	var $hasAndBelongsToMany = array(
			'langObjs' =>
				array(
					'className'				=> 'BEObject',
					'joinTable'    			=> 'content_bases_objects',
					'foreignKey'   			=> 'id',
					'associationForeignKey'	=> 'object_id',
					'unique'				=> true,
					'fields'				=> 'langObjs.id, langObjs.status, langObjs.lang, langObjs.title',
//					'conditions'			=> "ContentBasesObject.switch ='LANGS'",
//					'conditions'			=> "switch ='LANGS'",
					'switch'				=> "LANGS",
				),
			'images' =>
				array(
					'className'				=> 'ViewImage',
					'joinTable'    			=> 'content_bases_objects',
					'foreignKey'   			=> 'id',
					'associationForeignKey'	=> 'object_id',
					'unique'				=> true,
//					'fields'				=> 'images.id, images.status',
//					'conditions'			=> "ContentBasesObject.switch ='IMGS'",
					'conditions'			=> "switch ='IMGS'",
					'switch'				=> "IMGS",
				),
				
			'multimedia' =>
				array(
					'className'				=> 'ViewMultimedia',
					'joinTable'    			=> 'content_bases_objects',
					'foreignKey'   			=> 'id',
					'associationForeignKey'	=> 'object_id',
					'unique'				=> true,
					'fields'				=> 'multimedia.id, multimedia.status',
//					'conditions'			=> "ContentBasesObject.switch ='MULTIMS'",
					'switch'				=> "MULTIMS",
				),
			'attachments' =>
				array(
					'className'				=> 'ViewAttachment',
					'joinTable'    			=> 'content_bases_objects',
					'foreignKey'   			=> 'id',
					'associationForeignKey'	=> 'object_id',
					'unique'				=> true,
//					'conditions'			=> "ContentBasesObject.switch ='ATTACHS'",
					'switch'				=> "ATTACHS",
				),
		) ;			

	function __construct() {
		parent::__construct() ;

	}

	
	/**
	 * Le associazioni di tipo HABTM di questo modello non possono essere
	 * salvate con i metodi di cakePHP (la tabella di unione utilizza 
	 * 3 capi: id, object_id e swtich che determina il tipo di unione).
	 * 
	 * I dati delle associazioni vengono temporaneamente rimossi e poi 
	 * salvati dopo il modello (in $this->afterSave())
	 * 
	 */
	function beforeSave() {
		$this->tempData = array() ;
		 
		foreach ($this->hasAndBelongsToMany as $k => $assoc) {
			if(!isset($this->data[$k][$k])) continue ;
			
			$this->tempData[$k] = &$this->data[$k][$k] ;
			unset($this->data[$k][$k]) ;
		}
		
		return true ;
	}
	
	function afterSave() {
		$db 		= &ConnectionManager::getDataSource($this->useDbConfig);
		$queries 	= array() ;
		
		foreach ($this->tempData as $k => $values) {
			$assoc 	= $this->hasAndBelongsToMany[$k] ;
			$table 	= $db->name($db->fullTableName($assoc['joinTable']));
			$fields = $assoc['foreignKey'] .",".$assoc['associationForeignKey'].", switch"  ;
			
			// Cancella le precedenti associazioni
			$queries[] = "DELETE FROM {$table} WHERE {$assoc['foreignKey']} = '{$this->id}' AND {$assoc['conditions']} " ;
			
			for($i=0; $i < count($values); $i++) {
				$id 	= $this->id ;
				$obj_id	= $values[$i]['id'] ;
				$switch	= $assoc['switch'] ;
				
				$queries[] = "INSERT INTO {$table} ({$fields}) VALUES ({$id}, {$obj_id}, '{$switch}')" ;
			}
		}
		
		// Esegue le query
		for($i=0; $i < count($queries); $i++) {
			$db->query($queries[$i]);
		}

		unset($this->tempData);
		
		return true ;
	}
	
	/**
	 * Definisce i valori di default.
	 */		
	function beforeValidate() {
		if(isset($this->data[$this->name])) $data = &$this->data[$this->name] ;
		else $data = &$this->data ;
		
	 	$default = array(
			'start' 			=> array('_getDefaultDataFormat', (isset($data['start']) && !empty($data['start']))?$data['start']:time()),
			'end'	 			=> array('_getDefaultDataFormat', ((isset($data['end']) && !empty($data['end']))?$data['end']:null)),
			'formato' 			=> array('_getDefaultFormato', (isset($data['formato']))?$data['formato']:null),
		) ;
		
		foreach ($default as $name => $rule) {
			if(!is_array($rule)) {
				$data[$name] = $rule ;
				continue ;
			}
			
			$method = $rule[0];
			unset($rule[0]);
			
			if (method_exists($this, $method)) {
				$data[$name] = call_user_func_array(array(&$this, $method), $rule);
			} 
		}

		return true ;
	}
	
	/**
	 * Torna la data nel formato SQL
	 *
	 * @param unknown_type $value
	 * @return unknown
	 */
	private function _getDefaultDataFormat($value = null) {
		if(is_integer($value)) return date("Y-m-d", $value) ;
		
		if(is_string($value) && !empty($value)) {
			$conf = Configure::getInstance() ;
			
			if(preg_match($conf->date2iso, $value, $matched)) {
				$value = "{$matched[3]}-{$matched[2]}-{$matched[1]}" ;
			} 
			
			return $value ;
		}
		
		return null ;
	}
	
	/**
	 * Torna il formato di default per i testi
	 *
	 * @param unknown_type $value
	 * @return unknown
	 */
	private function _getDefaultFormato($value = null) {
		$labels = array('html', 'txt', 'txtParsed') ;
		if(isset($value) && in_array($value, $labels)) return $value ;

		$conf = Configure::getInstance() ;
		return ((isset($conf->formato))?$conf->formato:'') ;
	}
}
?>
