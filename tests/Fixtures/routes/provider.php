<?php declare(strict_types=1);

use Tests\Fixtures\OkController;

return [
    '/health' => [OkController::class => 'health'],
];
