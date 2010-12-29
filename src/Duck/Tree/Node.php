<?php
namespace Duck\Tree;

use Duck\Tree\Source\SourceInterface;

/**
 * Represents a row in the database that is also a node in a tree
 * @author Ross Tuck
 */
class Node extends \Zend_Db_Table_Row_Abstract {

	/**
	 * Get the unique id for this node
	 * @return mixed Node id
	 */
	public function getId() {
		return $this->__get($this->getTable()->getIdField());
	}

	/**
	 * Get the id of the parent node
	 * @return node
	 */
    public function getParentId() {
        return $this->parent_id;
    }

    /**
     * Get all children of this node, sorted from left to right
     * @return ArrayAccess
     */
	public function getChildren() {
        return $this->getTable()->getChildren($this->getId());
    }

    /**
     * Fetches this node's parent or null if node is a root node
     * @return Node|null
     */
    public function getParent() {
        $parentId = $this->getParentId();
        if(empty($parentId)) {
            return null;
        }

        return $this->getTable()->getNode($parentId);
    }

    /**
     * Get this node's parent and parent's parents, beginning with the root.
     * @return ArrayAccess Returns empty array if node is root
     */
    public function getAncestors() {
        return $this->getTable()->getAncestors($this->getId());
    }

    /**
     * Get this node's children and children's children, from top to bottom.
     * @return ArrayAccess Objects are sorted as DFS (depth first search)
     * @see http://en.wikipedia.org/wiki/Depth_first_search
     */
    public function getDescendants() {
        return $this->getTable()->getDescendants($this->getId());
    }

    /**
     * Get the siblings for this node, including the node itself
     * @return ArrayAccess Sorted from left to right
     */
    public function getSiblings() {
        return $this->getTable()->getSiblings($this->getId());
    }

    /**
     * Checks if this node descends from another node
     * @param Node $parent
     * @return boolean
     */
    public function isDescendantOf(Node $parent) {
        return ($parent->lft < $this->lft && $parent->rgt > $this->rgt);
    }

    /**
     * Check if a node is the ancestor of another node
     * @param Node $child
     * @return boolean
     */
    public function isAncestorOf($child) {
        return ($this->lft < $child->lft && $this->rgt > $child->rgt);
    }

    /**
     * Check if this node is a leaf node (i.e. has no children)
     * @return boolean
     */
    public function isLeaf() {
        return (int)$this->rgt === (int)$this->lft+1;
    }

    /**
     * Check if this node is a root node
     * @return boolean
     */
    public function isRoot() {
        return !$this->parent_id;
    }

    /**
     * Get the number of descendants beneath this node
     * @return integer
     */
    public function getDescendantCount() {
    	return ($this->rgt - $this->lft - 1) / 2;
    }

    /**
     * Add a child to this node
     * @param Node $row New node to insert
     * @param Node|null $before Optional. Specify a sibling to add this node before.
     * @return self
     */
    public function addChild(Node $row, Node $before = null) {
    	$this->getTable()->addChild($this, $row, $before);
    	return $this;
    }

	/**
	 * Close the gap left in the tree when deleting it.
	 * @TODO: Override the delete() function to make it return the total deleted
	 * @return Number of nodes removed.
	 */
    protected function _delete() {
    	return $this->getTable()->remove($this);
    }
}
