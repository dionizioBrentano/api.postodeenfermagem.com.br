<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

class Cpf implements ValidationRule
{
    public static function isValid(string $value): bool
    {
        $cpf = preg_replace('/[^0-9]/is', '', $value);

        if (strlen($cpf) != 11) {
            return false;
        }

        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::isValid((string) $value)) {
            $fail('O :attribute não é um CPF válido.');
        }
    }
}
