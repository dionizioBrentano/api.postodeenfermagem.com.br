<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

class Cns implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cns = preg_replace('/[^0-9]/is', '', $value);

        if (strlen($cns) != 15) {
            $fail('O :attribute não é um CNS válido.');
            return;
        }

        if (in_array($cns[0], ['1', '2'])) {
            $pis = substr($cns, 0, 11);
            $soma = 0;
            for ($i = 0; $i < 11; $i++) {
                $soma += (int)$pis[$i] * (15 - $i);
            }
            $resto = $soma % 11;
            $dv = 11 - $resto;
            if ($dv == 11) {
                $dv = 0;
            }
            if ($dv == 10) {
                $soma += 2;
                $resto = $soma % 11;
                $dv = 11 - $resto;
                $resultado = $pis . '001' . (string)$dv;
            } else {
                $resultado = $pis . '000' . (string)$dv;
            }
            if ($cns !== $resultado) {
                $fail('O :attribute não é um CNS válido.');
            }
        } elseif (in_array($cns[0], ['7', '8', '9'])) {
            $soma = 0;
            for ($i = 0; $i < 15; $i++) {
                $soma += (int)$cns[$i] * (15 - $i);
            }
            $resto = $soma % 11;
            if ($resto !== 0) {
                $fail('O :attribute não é um CNS válido.');
            }
        } else {
            $fail('O :attribute não é um CNS válido.');
        }
    }
}
