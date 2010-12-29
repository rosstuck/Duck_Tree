<?php
class Region_Table extends \Duck\Tree\DbTable {
    protected $_name = 'regions';

    public function findByLabel($label) {
        return $this->fetchRow(
            $this->select()->where('label = ?', $label)
        );
    }
}
