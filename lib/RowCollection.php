<?php
namespace isqua;

class RowCollection extends \ArrayObject{
	
	public function isEmpty() {
		return $this->count() === 0;
	}
	
	public function getFirst() {
		if ($this->isEmpty()) return NULL;
		else {
			$this->getIterator()->rewind();
			return $this->getIterator()->current();
		}
	}
	
	public function delete($index) {
		if (isset($this[$index])) {
			$this->offsetGet($index)->delete();
			unset($this[$index]);
		}
	}
	
	public function getClass() {
		return $this->isEmpty() ? NULL : get_class($this->getIterator()->current());
	}
	
	public function __get($name) {
		if ($name == 'keys') {
			$keys = array();
			foreach ($this->getIterator() as $key=>$current)
				$keys[] = $key;
			return $keys;
		} elseif ($name == 'values') {
			$values = array();
			foreach ($this->getIterator() as $key=>$current)
				$values[] = $current;
			return $values;
		}
	}
	
	public function offsetGet($index) {
		if ( ($pos = strpos($index, '=')) !== FALSE) {
			$property = substr($index, 0, $pos);
			$value = substr($index, $pos+1);
			$r = new RowCollection();
			foreach ($this->getIterator() as $key=>$current) {
				if (isset($current->$property) && $current->$property == $value)
					$r[$key] = $current;
			}
			return $r;
		} else {
			return parent::offsetGet($index);
		}
	}
	
	public function offsetExists($index) {
		if ( ($pos = strpos($index, '=')) !== FALSE) {
			return TRUE;
		} else {
			return parent::offsetExists($index);
			//return FALSE;
		}
	}
}