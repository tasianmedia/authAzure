<?php
/**
 * @package authazure
 */
require_once (strtr(realpath(dirname(dirname(__FILE__))), '\\', '/') . '/aazprofile.class.php');
class AazProfile_mysql extends AazProfile {}
?>