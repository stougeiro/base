<?php declare(strict_types=1);

use Tests\Fixtures\OkController;

return [
    '/users/{id:num}' => OkController::class,
];
