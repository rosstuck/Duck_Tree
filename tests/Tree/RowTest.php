<?php
class Tree_RowTest extends Tree_TestCase {

    protected $_dataset = 'regions';

    protected function _row($label) {
        return $this->regions->findByLabel($label);
    }

    protected function _pluckLabels($rowset) {
        $output = array();
        foreach($rowset as $row) {
            $output[] = $row['label'];
        }

        return $output;
    }

    public function testGetChildren() {
        $south = $this->_row('Southern USA');
        $states = $south->getChildren();

        $this->assertEquals(2, $states->count());
        $this->assertEquals('Florida', $states[0]['label']);
        $this->assertEquals('Kentucky', $states[1]['label']);
    }

    /** is<Type> node tests **/
    public function testNodeIsLeaf() {
        $florida = $this->_row('Florida');
        $this->assertTrue($florida->isLeaf());
    }

    public function testNodeIsNotLeaf() {
        $south = $this->_row('Southern USA');
        $this->assertFalse($south->isLeaf());
    }

    public function testNodeIsRoot() {
        $europe = $this->_row('Europe');
        $this->assertTrue($europe->isRoot());
    }

    public function testNodeIsNotRoot() {
        $florida = $this->_row('Florida');
        $this->assertFalse($florida->isRoot());
    }

    /** Test parent and parent id fetching for nodes and roots **/
    public function testNodeGetParentId() {
        $south = $this->_row('Southern USA');
        $this->assertEquals(31, $south->getParentId());
    }

    public function testRootGetParentId() {
        $south = $this->_row('Europe');
        $this->assertEquals(NULL, $south->getParentId());
    }

    public function testRootGetParent() {
        $europe = $this->_row('Europe');
        $this->assertNull($europe->getParent());
    }

    public function testNodeGetParent() {
        $florida  = $this->_row('Florida');
        $south = $florida->getParent();

        $this->assertEquals('Southern USA', $south->label);
    }

    /** Lineage tests **/
    public function testDescendsFrom() {
        $usa = $this->_row('USA');
        $florida = $this->_row('Florida');

        $this->assertTrue($florida->isDescendantOf($usa));
    }

    public function testDoesNotDescendFrom() {
        $netherlands = $this->_row('Netherlands');
        $canada = $this->_row('Canada');

        $this->assertFalse($netherlands->isDescendantOf($canada));
    }

    public function testIsAncestorOf() {
        $usa = $this->_row('USA');
        $florida = $this->_row('Florida');

        $this->assertTrue($usa->isAncestorOf($florida));
    }

    public function testIsNotAncestorOf() {
        $netherlands = $this->_row('Netherlands');
        $canada = $this->_row('Canada');

        $this->assertFalse($netherlands->isAncestorOf($canada));
    }

    public function testGetMultipleAncestors() {
        $florida = $this->_row('Florida');
        $parents = $florida->getAncestors();

        $this->assertEquals(3, count($parents));
        $this->assertEquals('North America',    $parents[0]['label']);
        $this->assertEquals('USA',              $parents[1]['label']);
        $this->assertEquals('Southern USA',     $parents[2]['label']);
    }

    public function testGetSingleAncestor() {
        $netherlands = $this->_row('Netherlands');
        $parent = $netherlands->getAncestors();

        $this->assertEquals(1, $parent->count());
        $this->assertEquals('Europe', $parent[0]['label']);
    }

    public function testGetNoAncestors() {
        $europe = $this->_row('Europe');
        $emptyRowset = $europe->getAncestors();

        $this->assertEquals(0, $emptyRowset->count());
    }

    public function testGetSiblings() {
        $quebec = $this->_row('Quebec');
        $siblings = $quebec->getSiblings();

        $this->assertEquals(2, $siblings->count());
        $this->assertEquals('Quebec', $siblings[0]['label']);
        $this->assertEquals('British Columbia', $siblings[1]['label']);
    }

    public function testRootSiblings() {
        $europe = $this->_row('Europe');
        $siblings = $europe->getSiblings();

        $this->assertEquals(2, $siblings->count());
        $this->assertEquals('North America', $siblings[0]['label']);
        $this->assertEquals('Europe', $siblings[1]['label']);
    }

    public function testGetDescendants() {
        $northAmerica = $this->_row('North America');
        $this->assertEquals(
            Tree_Fixtures::northAmericaDFS(),
            $this->_pluckLabels($northAmerica->getDescendants())
        );
    }

    public function testGetDescendantsOfLeafNode() {
        $leaf = $this->_row('Florida');

        $this->assertEquals(
            array(),
            $leaf->getDescendants()->toArray()
        );
    }
}
