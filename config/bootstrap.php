<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

use lithium\core\Libraries;

/**
 * Overrides default adapter lookup path. So that third-party adaptables can find their adapters
 * under their own namespace/folder.
 */
$adapter = Libraries::paths('adapter');
$adapter[] = '{:library}\{:namespace}\{:class}\adapter\{:name}';
Libraries::paths(compact('adapter'));

?>