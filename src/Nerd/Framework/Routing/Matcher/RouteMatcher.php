<?php
/**
 * Created by PhpStorm.
 * User: roman
 * Date: 11/25/16
 * Time: 5:35 PM
 */

namespace Nerd\Framework\Routing\Matcher;

interface RouteMatcher
{
    public function matches(string $route): boolean;

    public function parameters(string $route): array;
}
