<?php

use App\com_pinoox_app\Router\Actions;
use function Pinoox\Router\get;

get('/', '@' . Actions::HOME);
