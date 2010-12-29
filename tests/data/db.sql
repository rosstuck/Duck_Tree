CREATE TABLE IF NOT EXISTS `regions` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `label` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `lft` tinyint(3) unsigned NOT NULL,
  `rgt` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

ALTER TABLE `regions`
  ADD CONSTRAINT `regions_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `regions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
