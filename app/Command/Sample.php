<?php

namespace App\Command;

use DFrame\Command\Helper\ConsoleInput;

class Sample
{
    public static function handle(): void
    {
        $isOk = ConsoleInput::askYesNo("Continue ?");
        if ($isOk) {
            echo "You chose to continue.\n";
        } else {
            echo "Cancelled.\n";
        }
    }
}
