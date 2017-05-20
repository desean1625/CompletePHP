#!/usr/bin/env php
<?php

$autoloader = $argv[1];
chdir(dirname(dirname($autoloader)));

require $autoloader;
$class = $argv[2];
$out   = array();
$rc    = new ReflectionClass($class);
$meths = $rc->getMethods();
foreach ($meths as $key => $value) {
    $params    = $value->getParameters();
    $meth      = ($value->name == "__construct") ? "new" : $value->name;
    $classname = array('class' => $class, "params" => array());
    foreach ($params as $index => $param) {
        $classname["params"][] = $param->name;

    }
    $out[$meth][] = $classname;
}
echo json_encode($out);