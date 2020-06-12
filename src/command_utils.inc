<?php

namespace EPFL_WP_CLI;

function nuke_command ($name) {
    $root_command = \WP_CLI::get_root_command();
    $reflection = new \ReflectionClass($root_command);
    $property = $reflection->getProperty("subcommands");
    $property->setAccessible(true);
    $subcommands = $property->getValue($root_command);
    unset($subcommands[$name]);
    $property->setValue($root_command, $subcommands);
}
