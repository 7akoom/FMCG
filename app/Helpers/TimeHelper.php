<?php

function calculateTime()
{
    $milliseconds = now()->format('v');
    $seconds = now()->format('s');
    $minutes = now()->format('i');
    $hours = now()->format('h');
    return $milliseconds + (256 * $seconds) + (65536 * $minutes) + (16777216 * $hours);
}









//Time = Milliseconds + 256 x Seconds + 65536 x Minutes + 16777216 x Hours
