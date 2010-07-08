<?php

$simulate = (bool) $params['simulate'];
$ladder = new Ladder($params['migrate-to'], $simulate);
