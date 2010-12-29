<?php
class Tree_BuildTest extends Tree_TestCase {

    protected $_dataset = 'regions_unbuilt';

    public function testEdgeRebuild() {
        $regions = new Region_Table();
        $regions->buildEdges();
        
        //Check that a root appears okay.
        $europe = $regions->findByLabel('Europe');
        $this->assertEquals(35, $europe->lft);
        $this->assertEquals(44, $europe->rgt);
        
        //Check that a leaf looks okay.
        $florida = $regions->findByLabel('Florida');
        $this->assertEquals(12, $florida->lft);
        $this->assertEquals(13, $florida->rgt);
    }
}
