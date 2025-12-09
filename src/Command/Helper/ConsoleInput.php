<?php

namespace DFrame\Command\Helper;

class ConsoleInput
{
    /**
     * Basic prompt with optional validation.
     *
     * @param string $message The prompt message.
     * @param string|null $default The default value if input is empty.
     * @param callable|null $validator  Return true if valid, or string error message if invalid.
     * @return string
     */
    public static function prompt(
        string $message,
        ?string $default = null,
        ?callable $validator = null
    ): string {
        while (true) {
            $msg = $default !== null
                ? "{$message} [default: {$default}]: "
                : "{$message}: ";

            echo $msg;

            $input = fgets(STDIN);

            if ($input === false) {
                return $default ?? '';
            }

            $input = trim($input);

            if ($input === '' && $default !== null) {
                return $default;
            }

            if ($validator !== null) {
                $valid = $validator($input);

                if ($valid === true) {
                    return $input;
                }

                if (is_string($valid)) {
                    echo "Wrong input: {$valid}\n";
                    continue;
                }
            }

            if ($input !== '') {
                return $input;
            }

            echo "Please enter a non-empty value.\n";
        }
    }


    /**
     * Ask Yes/No question.
     * Return: true = Yes, false = No
     * 
     * @param string $message The question message.
     * @param bool $default Default answer if input is empty (true = Yes, false = No).
     * @return bool
     */
    public static function askYesNo(
        string $message,
        bool $default = true
    ): bool {
        $defaultText = $default ? "Y/n" : "y/N";

        while (true) {
            echo "{$message} [{$defaultText}]: ";
            $input = trim(fgets(STDIN));

            if ($input === '') {
                return $default;
            }

            $lower = strtolower($input);

            if (in_array($lower, ['y', 'yes'], true)) return true;
            if (in_array($lower, ['n', 'no'], true)) return false;

            echo "Please answer yes or no.\n";
        }
    }


    /**
     * Let user select an option from a list.
     *
     * @param string $message The prompt message.
     * @param array $options Key-value pairs of options (key => label).
     * @param string|null $defaultKey
     * @return string selected key
     */
    public static function select(
        string $message,
        array $options,
        ?string $defaultKey = null
    ): string {

        echo "{$message}:\n";

        foreach ($options as $key => $label) {
            echo "  [$key] $label\n";
        }

        while (true) {
            $prompt = $defaultKey ? "Choose option [default: {$defaultKey}]: " : "Choose option: ";
            echo $prompt;

            $input = trim(fgets(STDIN));

            if ($input === '' && $defaultKey !== null) {
                return $defaultKey;
            }

            if (array_key_exists($input, $options)) {
                return $input;
            }

            echo "Invalid option. Try again.\n";
        }
    }



    // --- Validators --- //

    public static function validateNumber(): callable
    {
        return function ($value) {
            return is_numeric($value)
                ? true
                : "Value must be a number.";
        };
    }

    public static function validateEmail(): callable
    {
        return function ($value) {
            return filter_var($value, FILTER_VALIDATE_EMAIL)
                ? true
                : "Invalid email format.";
        };
    }

    public static function validateRegex(string $pattern): callable
    {
        return function ($value) use ($pattern) {
            return preg_match($pattern, $value)
                ? true
                : "Input does not match required pattern.";
        };
    }
}
