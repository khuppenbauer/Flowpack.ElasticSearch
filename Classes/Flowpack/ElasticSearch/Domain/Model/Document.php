<?php
namespace Flowpack\ElasticSearch\Domain\Model;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Flowpack.ElasticSearch".*
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 *  of the License, or (at your option) any later version.                *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Utility\Arrays;

/**
 * A Document which itself holds the data
 */
class Document {

	/**
	 * @var \Flowpack\ElasticSearch\Domain\Model\AbstractType
	 */
	protected $type;

	/**
	 * The actual data to store to the document
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * The version that has been assigned to this document.
	 *
	 * @var integer
	 */
	protected $version;

	/**
	 * @var string
	 */
	protected $id;

	/**
	 * Whether this document represents the state like it should be at the storage.
	 * With a fresh instance of this document, or a conducted change, this flag gets set to TRUE again.
	 * When retrieved from the storage, or successfully set to the storage, it's FALSE.
	 *
	 * @var boolean
	 */
	protected $dirty = TRUE;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * @param \Flowpack\ElasticSearch\Domain\Model\AbstractType $type
	 * @param array $data
	 * @param string $id
	 * @param null $version
	 */
	public function __construct(AbstractType $type, array $data = NULL, $id = NULL, $version = NULL) {
		$this->type = $type;
		$this->data = $data;
		$this->id = $id;
		$this->version = $version;
	}

	/**
	 * When cloning (locally), the cloned object doesn't represent a stored one anymore,
	 * so reset id, version and the dirty state.
	 */
	public function __clone() {
		$this->id = NULL;
		$this->version = NULL;
		$this->setDirty();
	}

	/**
	 * @param string $method
	 * @param string $path
	 * @param array $arguments
	 * @param string $content
	 *
	 * @return \Flowpack\ElasticSearch\Transfer\Response
	 */
	protected function request($method, $path = NULL, $arguments = array(), $content = NULL) {
		return $this->type->request($method, $path, $arguments, $content);
	}

	/**
	 * Stores this document. If ID is given, PUT will be used; else POST
	 *
	 * @throws \Flowpack\ElasticSearch\Exception
	 * @return void
	 */
	public function store() {
		if ($this->id !== NULL) {
			$method = 'PUT';
			$path = '/' . $this->id;
		} else {
			$method = 'POST';
			$path = '';
		}
		$mapping = Arrays::getValueByPath($this->settings, 'mapping.' . $this->type->getIndex()->getName() . '.' . $this->type->getName());
		$additionalPath = array();
		if (isset($mapping['_parent'])) {
			$additionalPath[] = 'parent=' . $this->data[$mapping['_parent']['type']];
		}
		if (isset($mapping['_routing'])) {
			$additionalPath[] = 'routing=' . $this->data[$mapping['_routing']['path']];
		}
		if (!empty($additionalPath)) {
			$path .= '?' . implode('&', $additionalPath);
		}
		$response = $this->request($method, $path, array(), json_encode($this->data));
		$treatedContent = $response->getTreatedContent();

		$this->id = $treatedContent['_id'];
		$this->version = $treatedContent['_version'];
		$this->dirty = FALSE;
	}

	/**
	 * Performs a partial update of a document
	 *
	 * @throws \Flowpack\ElasticSearch\Exception
	 * @return void
	 * @see http://www.elastic.co/guide/en/elasticsearch/guide/master/partial-updates.html
	 */
	public function update() {
		$method = 'POST';
		$path = '/' . $this->id . '/_update';
		$response = $this->request($method, $path, array(), json_encode($this->data));
		$treatedContent = $response->getTreatedContent();

		$this->id = $treatedContent['_id'];
		$this->version = $treatedContent['_version'];
		$this->dirty = FALSE;
	}

	/**
	 * @param boolean $dirty
	 */
	protected function setDirty($dirty = TRUE) {
		$this->dirty = $dirty;
	}

	/**
	 * @return boolean
	 */
	public function isDirty() {
		return $this->dirty;
	}

	/**
	 * @return integer
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 * The contents of this document
	 *
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * @param array $data
	 */
	public function setData($data) {
		$this->data = $data;
		$this->setDirty();
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Gets a specific field's value from this' data
	 *
	 * @param string $fieldName
	 * @param boolean $silent
	 *
	 * @throws \Flowpack\ElasticSearch\Exception
	 * @return mixed
	 */
	public function getField($fieldName, $silent = FALSE) {
		if (!array_key_exists($fieldName, $this->data) && $silent === FALSE) {
			throw new \Flowpack\ElasticSearch\Exception(sprintf('The field %s was not present in data of document in %s/%s.', $fieldName, $this->type->getIndex()->getName(), $this->type->getName()), 1340274696);
		}

		return $this->data[$fieldName];
	}

	/**
	 * @return \Flowpack\ElasticSearch\Domain\Model\AbstractType the type of this Document
	 */
	public function getType() {
		return $this->type;
	}
}

