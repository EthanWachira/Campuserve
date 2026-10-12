<?php
declare(strict_types=1);
/** Front controller. Every request enters here: index.php?r=controller/action */
define('BASE_PATH', __DIR__);
require BASE_PATH . '/core/App.php';
App::boot();
App::dispatch();
