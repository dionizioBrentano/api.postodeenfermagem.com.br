<?php

namespace App\Services;

/**
 * Serviço de tokenização para dados sensíveis (CPF, CNS, etc).
 *
 * Nunca armazenamos CPF/CNS em texto puro. O valor em si é guardado
 * criptografado (via CryptoService/EncryptedWithDek) e, adicionalmente,
 * geramos um token irreversível (blind index HMAC) que permite localizar
 * o registro por igualdade exata sem descriptografar toda a base.
 */
class TokenizationService
{
    public function __construct(private readonly CryptoService $crypto)
    {
    }

    /**
     * Gera o token (irreversível) para um valor sensível.
     */
    public function tokenize(string $value): string
    {
        return $this->crypto->blindIndex($value);
    }

    /**
     * Verifica se um valor corresponde a um token já gerado, sem
     * vazar timing information (hash_equals).
     */
    public function matches(string $value, string $token): bool
    {
        return hash_equals($token, $this->tokenize($value));
    }
}
