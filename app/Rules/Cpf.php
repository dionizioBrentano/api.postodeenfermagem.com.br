<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cpf = preg_replace('/[^0-9]/is', '', $value);

        if (strlen($cpf) != 11) {
            $fail('O :attribute não é um CPF válido.');
            return;
        }

        if (preg_match('/(\d)\1{10}/', $cpf)) {
            $fail('O :attribute não é um CPF válido.');
            return;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                $fail('O :attribute não é um CPF válido.');
                return;
            }
        }
    }
}
