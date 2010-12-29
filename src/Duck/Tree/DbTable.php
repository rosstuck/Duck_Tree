<?php
namespace Duck\Tree;

use Zend_Db_Expr,
    Zend_Db_Table_Abstract,
    Zend_Db_Table_Select,
    Zend_Db;

/**
 * Base class for different variations of hierarchal tables
 * @author Ross Tuck
 */
abstract class DbTable extends \Zend_Db_Table /*implements SourceInterface*/ {

    /**
     * Name of class to use for nodes/rows
     * @var string
     */
    protected $_rowClass = '\Duck\Tree\Node';

    /**
     * Get the name of the id field used in the tree
     * @param bool $escape If true, escape it with the quote adapter
     * @return string
     */
    public function getIdField($escape = false) {
    	$keys = $this->info('primary');
    	if(!is_array($keys) || count($keys) > 1) {
    		throw new \LogicException('Duck Table does not support multiple primary keys. Sorry.');
    	}

    	$idField = array_pop($keys);

    	if($escape) {
    		return $this->getAdapter()->quoteIdentifier($idField);
    	}
    	return $idField;
    }

    /**
     * Fetch a single node by its id
     * @param mixed $id Unique node id
     * @param Zend_Db_Table_Select $select A select with any extra conditions
     * @return Zend_Db_Table_Rowset
     */
    public function getNode($id, $select = null) {
    	if(empty($id)) {
    		throw new \Exception('No id given');
    	}

    	return $this->fetchRow(
    		$this->_toSelect($select)
    			->where($this->getIdField(true).' = ?', $id)
    		);
    }

    /**
     * Get all nodes in the table, sorted as depth first search
     * @param Zend_Db_Table_Select $select A select with any extra conditions
     * @return Zend_Db_Table_Rowset
     */
    public function getAllNodes($select = null) {
    	return $this->getDescendants(null, $select);
    }

    /**
     * Get all root nodes for this tree
     * @param Zend_Db_Table_Select $select A select with any extra conditions
     * @return Zend_Db_Table_Rowset
     */
    public function getRoots($select = null) {
        return $this->fetchAll(
            $this->_toSelect($select)
                ->where("parent_id IS NULL")
                ->order('lft ASC')
        );
    }

    /**
     * Get all leaf nodes on a tree
     * @param Zend_Db_Table_Select $select A select with any extra conditions
     * @return Zend_Db_Table_Rowset
     */
    public function getLeaves($select = null) {
        $select = $this->_toSelect($select)
        	->where('rgt = lft + 1');

        return $this->fetchAll($select);
    }

    /**
     * Get the children for a particular node, sorted left to right
     * @param mixed $id Id of node
     * @param Zend_Db_Table_Select $select A select with any extra conditions
     * @return Zend_Db_Table_Rowset
     */
    public function getChildren($id, $select = null) {
        if($id === null) {
            return $this->getRoots();
        }

        $select = $this->_toSelect($select)
            ->where('parent_id = ?', $id)
            ->order('lft ASC');

        return $this->fetchAll($select);
    }

    /**
     * Get all ancestors for a particular node
     * @param mixed $id Node id
     * @param Zend_Db_Table_Select $select A select with any extra conditions
     * @return Zend_Db_Table_Rowset
     */
    public function getAncestors($id, $select = null) {
    	list($select, $name, $safeName) = $this->_toSelfJoin($id, $select);

    	$select
    		->where("parent.{$this->getIdField(true)} = ?", $id)
            ->where("{$safeName}.lft < parent.lft")
            ->where("{$safeName}.rgt > parent.rgt");

        return $this->fetchAll($select);
    }

    /**
     * Get all descendants for a node, sorted as DFS
     * @param mixed $id
     * @param Zend_Db_Table_Select $select A select with any extra conditions
     * @return Zend_Db_Table_Rowset
     */
    public function getDescendants($id, $select = null) {
    	list($select, $name, $safeName) = $this->_toSelfJoin($id, $select);

    	$select->where($safeName.'.lft BETWEEN parent.lft AND parent.rgt');

    	if(empty($id)) {
    		$select->where('parent.parent_id IS NULL');
    	} else {
    		$select->where('parent.parent_id = ?', $id);
    	}

        return $this->fetchAll($select);
    }

    /**
     * Get all siblings for a node, including the node itself
     * @param mixed $id Unique id for the node
     * @param Zend_Db_Table_Select $select A select with any extra conditions
     * @return Zend_Db_Table_Rowset
     */
    public function getSiblings($id, $select = null) {
    	list($select, $name, $safeName) = $this->_toSelfJoin($id, $select);

    	$select
    		->where("parent.{$this->getIdField(true)} = ?", $id)
    		->where(new Zend_Db_Expr(
    		    "( (parent.parent_id IS NULL     AND {$safeName}.parent_id IS NULL)
    		    OR (parent.parent_id IS NOT NULL AND {$safeName}.parent_id = parent.parent_id)
    	        )"
            ));

    	return $this->fetchAll($select);
    }

    /**
     * Add a root node to the tree
     * @param Node $node New node to insert
     * @param Node|null $before Optional. Insert root before this other root.
     * @return self
     */
    public function addRoot(Node $node, Node $before = null) {
    	$roots = $this->getRoots();

    	if(count($roots) === 0) {				 //...as only root in table
    		$node->lft = 1;
    		$node->rgt = 2;
    	} elseif($before === null) {
    		$lastRoot = $roots[count($roots)-1]; //...as last root
    		$node->lft = $lastRoot->rgt + 1;
    		$node->rgt = $lastRoot->rgt + 2;
    	} else {								 //...before another root
    		if(!$before->isRoot()) {
    			throw new \Exception('Can not insert a root after a non-root.');
    		}
    		$node->lft = $before->lft;
    		$node->rgt = $before->lft+1;
    	}

    	$this->_createGapFor($node);

    	$node->parent_id = null;
    	$node->save();
    	return $this;
    }

    /**
     * Add a child to a node
     * @param Node $parent The node to add the current node under
     * @param Node $child The node to insert
     * @param Node|null $before Optional. Insert the child BEFORE this node
     * @return self
     */
    public function addChild(Node $parent, Node $child, Node $before = null) {
        if(empty($parent->lft)) {
        	throw new \Exception('Parent has not yet been saved to the database');
        }

        if(!empty($child->lft)) {
        	throw new \Exception('Child already has a position in the database.');
        }

        if($before instanceof Node && $before->getParentId() !== $parent->getId()) {
        	throw new \Exception('The node to insert before is not a child of this parent');
        }

    	//Add as last child...
    	if($before === null) {
    		$child->lft = $parent->rgt;
    		$child->rgt = $parent->rgt+1;
    	//...or add behind another node
    	} else {
    		$child->lft = $before->lft;
    		$child->rgt = $before->lft+1;

    	//Update the *instances* of $before and $parent with new edges
    		$before->lft += 2;
    		$before->rgt += 2;
    	}
    	$parent->rgt += 2;

    	//Now update the *database* with the new edge values
    	$this->_createGapFor($child);

    	//Save and done
    	$child->parent_id = $parent->getId();
    	$child->save();
        return $this;
    }

    /**
     * Remove a node AND its descendants from the tree.
     * @param Node $node Node to remove
     * @return integer Number of nodes removed.
     */
    public function remove(Node $node) {
        //Setup
    	$adapter = $this->getAdapter();
        $width = $adapter->quote(($node->rgt - $node->lft) + 1, Zend_Db::INT_TYPE);
        $lft = $adapter->quote($node->lft, Zend_Db::INT_TYPE);
        $rgt = $adapter->quote($node->rgt, Zend_Db::INT_TYPE);

        $node->lft = $node->rgt = $node->parent_id = null;

        //Remove from tree
        $deleteCount = $this->delete("lft BETWEEN {$lft} AND {$rgt}");

        //Close the gap left by the nodes
    	$this->_removeGap($rgt, $width);

        return $deleteCount;
    }

    /**
     * Move a node within the tree.
     *
     * @author Warnar Boekkooi
     * @param Node $newParent The node to move the current node under
     * @param Node $node The node to move
     * @param Node|null $before Optional. Insert the node BEFORE this node
     * @return self
     */
    public function move(Node $node, Node $newParent = null, Node $before = null) {
        // Validate input
        if (empty($node->lft)) {
        	throw new \Exception('Node has not yet been saved to the database.');
        }

        if ($newParent !== null) {
        	if (empty($newParent->lft)) {
        		throw new \Exception('Parent has not yet been saved to the database');
        	}
        	if ($newParent->getId() === $node->getId()) {
        		throw new \Exception('Node cannot be it\'s own parent.');
        	}
        	if ($node->isAncestorOf($newParent)) {
        		throw new \Exception('Parent node may not be a ancestor of the given node.');
        	}
        }

        if ($before !== null) {
        	if (empty($node->lft)) {
        		throw new \Exception('Before node has not yet been saved to the database.');
        	}
        	if ($before->getId() === $node->getId()) {
        		throw new \Exception('Node cannot be added before it self.');
        	}
        	if ($newParent !== null) {
        		if ($before->getParentId() !== $newParent->getId()) {
        			throw new \Exception('Before node must to have the same parent as the given parent node.');
        		}
    	    	if ($before->getId() === $newParent->getId()) {
    	    		throw new \Exception('Before node cannot me the the parent node.');
    	    	}
        	}
        }

        // Get the current information of the node
        $width = ($node->rgt - $node->lft) + 1;
        $oldLft = $node->lft;
        $oldRgt = $node->rgt;

        // Get the position to make a new gap
        $parentId;
        $gapPosition;
        if ($before !== null) {
        	$gapPosition = $before->lft;
        	$parentId = $before->getParentId();
        } elseif($newParent !== null) {
        	$gapPosition = $newParent->rgt;
        	$parentId = $newParent->getId();
        } else {
        	$parentId = null;

        	// Get the last root
        	$lastRoot = $this->fetchRow(
        		$this->_toSelect(null)->where("parent_id IS NULL")->order('lft DESC')
            );

        	$gapPosition = $lastRoot->rgt+1;
        }

        // Calculate the number of positions the node will move
    	$shift = $gapPosition - $oldLft;
        if ($shift < 0) {
        	$shift -=  $width;
        }

        // Calculate the position of the node after the gap has been created
    	$currentLft = $oldLft;
    	$currentRght = $oldRgt;
    	if ($shift < 0) {
    		$currentLft += $width;
    		$currentRght += $width;
    	}

    	// Calculate the position of the gap after the node has been moved
    	$gapPositionAfter = $oldLft;
    	if ($shift < 0) {
    		$gapPositionAfter += $width;
    	}

        // Create a grap where to insert the node
        $this->_createGap($gapPosition, $width);

    	// Update the nodes parent id
    	$node->parent_id = $parentId;
    	$node->save();

    	// Now move the into the created gap
        $adapter = $this->getAdapter();
    	$shift = $adapter->quote($shift);
    	$currentRght = $adapter->quote($currentRght);
    	$currentLft = $adapter->quote($currentLft);

        $this->update(array(
    		'rgt' => new Zend_Db_Expr("rgt + {$shift}"),
    		'lft' => new Zend_Db_Expr("lft + {$shift}")
    	), "lft BETWEEN {$currentLft} AND {$currentRght}");

    	// Remove the created gap
    	$this->_removeGap($gapPositionAfter, $width);

    	// Refresh the code positions
    	$node->refresh();
    	if ($newParent !== null) {
    		$newParent->refresh();
    	}
    	if ($before !== null) {
    		$before->refresh();
    	}

        return true;
    }

    /**
     * Create a gap in the tree for the given node.
     *
     * In other words, this function scoots all nodes to the right of this node
     * over enough to make room for the given node. This function does NOT save
     * the node or calculate its indexes, that must be done externally.
     *
     * @param Node $node
     */
    protected function _createGapFor(Node $node) {
    	$this->_createGap($node->lft, 2);
    }

    /**
     * Create a gap in the tree.
     *
     * In other words, this function scoots all nodes to the right of the given
     * left position with the given width.
     *
     * @param int $lft The left position.
     * @param int $width The size of the gap.
     */
    protected function _createGap($lft, $width) {
    	$adapter = $this->getAdapter();

    	$lft = $adapter->quote($lft, \Zend_Db::INT_TYPE);
    	$width = $adapter->quote($width, \Zend_Db::INT_TYPE);

    	$this->update(array(
    		'lft' => new Zend_Db_Expr("lft + {$width}"),
    	), "lft >= {$lft}");

    	$this->update(array(
    		'rgt' => new Zend_Db_Expr("rgt + {$width}"),
    	), "rgt >= {$lft}");
    }

    /**
     * Remove a gap in the tree.
     *
     * @param int $lft The left position.
     * @param int $width The size of the gap.
     */
    protected function _removeGap($lft, $width) {
    	$adapter = $this->getAdapter();

    	$lft = $adapter->quote($lft, \Zend_Db::INT_TYPE);
    	$width = $adapter->quote($width, \Zend_Db::INT_TYPE);

    	$this->update(array(
    		'lft' => new Zend_Db_Expr("lft - {$width}"),
    	), "lft >= {$lft}");

    	$this->update(array(
    		'rgt' => new Zend_Db_Expr("rgt - {$width}"),
    	), "rgt >= {$lft}");
    }

    /**
     * Rebuild the edges in the database from an adjacency model.
     *
     * This method makes saving your trees to the database a little easier.
     * Essentially, you can use this to nuke your current tree table contents
     * (careful of those cascades!) and insert them using just the parent_id
     * setup everyone knows and then call this method which will add lft and rgt
     * values to each node.
     */
    public function buildEdges() {
        $roots = $this->getRoots();
        $this->_rebuildFromNodes($roots);
    }

    /**
     * A recursive function that rebuilds lft and rgt for all sub nodes.
     * @param ArrayAccess $nodes List of nodes to begin cycling through.
     * @param integer $left Ignore this. Used for passing left offset through.
     * @return Return new lft offset for future nodes
     */
    protected function _rebuildFromNodes($nodes, $left = 0) {
        foreach($nodes as $node) {
            $left++;

            //Fetch the right-side value for this node by finding those below it
            $right = $this->_rebuildFromNodes($node->getChildren(), $left);
            //var_dump($left.':'.$node->label.':'.$right);
            //Save the updated node.
            $node->lft = $left;
            $node->rgt = $right;
            $node->save();

            $left = $right;
        }

        //We always leave one higher, even if there were no nodes.
        return $left+1;
    }

    /**
     * Check if a user supplied value is a select to use or create one.
     * @param Zend_Db_Table_Select|null $select
     * @return Zend_Db_Table_Select
     */
    protected function _toSelect($select) {
        if(empty($select)) {
        	$select = $this->select();
        } elseif(!($select instanceof \Zend_Db_Select)) {
        	throw new \Exception('Invalid select object given');
        }

        return $select;
    }

    /**
     * Create a select object with a self join, useful for certain queries
     * @param mixed $id A node id
     * @param Zend_Db_Table_Select|null $select
     * @return array A(Select, $tableName, $escapedTableName) Useful with list()
     */
    protected function _toSelfJoin($id, $select) {
    	//Select object
    	$select = $this->_toSelect($select);

    	//Table attribs
    	$name = $this->info(Zend_Db_Table_Abstract::NAME);
    	$safeName = $this->getAdapter()->quoteIdentifier($name);

    	//Add sql
    	$select
    		->from($name)
        	->joinInner(array('parent' => $name), null, null)
        	->order("{$name}.lft ASC");

    	return array($select, $name, $safeName);
    }
}
