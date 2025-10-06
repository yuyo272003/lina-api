<?php

if (!function_exists('capitalizeFirst')) {
    function capitalizeFirst($string)
    {
        if (empty($string)) {
            return '';
        }
        $string = mb_strtolower($string, 'UTF-8');
        return ucfirst($string);
    }
}