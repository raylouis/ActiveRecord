ActiveRecord helper for Wolf CMS
================================

The ActiveRecord helper for Wolf CMS is intended to improve the default Record class that comes with Wolf CMS. The class ActiveRecord extends the Record class, adding a find() method in order to easily build advanced queries. It also provides eager loading possiblities in order to reduce the N + 1 problem. ActiveRecord is loosely inspired by the PHPActiveRecord project.

Instructions
------------

* Copy ActiveRecord.php to **CMS_ROOT/wolf/helpers**

Requirements
------------

* PHP 5.3+
* The ActiveRecord helper has only been tested in combination with MySQL.