<?php
class Tree_TableTest extends Tree_TestCase {

    protected $_dataset = 'regions';

    public function testFetchRoots() {
        $regions = $this->regions->getRoots();
        $this->assertEquals(2, count($regions));
    }

    public function testFetchLeaves() {
        $results = $this->regions->getLeaves();
        $this->assertEquals(15, $results->count());
    }
}
