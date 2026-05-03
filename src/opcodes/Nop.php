<?php

declare(strict_types=1);

namespace SuperInstance\FluxVM\Opcodes;

use SuperInstance\FluxVM\FluxVM;

final class Nop
{
    public static function execute(FluxVM $vm, ...$args): void
    {
        // Nop
    }
}
