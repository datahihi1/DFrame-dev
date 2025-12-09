<?php

namespace App\Command;

use DFrame\Command\Helper\ConsoleInput;

class Hello
{
    public static function handle(): void
    {
        $name = ConsoleInput::prompt("Enter your name");
        echo "Hello, {$name}!\n";
    }

    public static function num(): void
    {
        $num = ConsoleInput::prompt("Enter a number ", null, ConsoleInput::validateNumber());
        echo "You entered: {$num}\n";
    }

    public static function choice(): void
    {
        $option = ConsoleInput::select(
            "Select environment",
            [
                "1" => "Development",
                "2" => "Staging",
                "3" => "Production",
            ],
            "1" // default
        );

        echo "You selected: $option\n";
    }
}
