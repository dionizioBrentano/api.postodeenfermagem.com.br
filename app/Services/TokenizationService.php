<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Exception;

class TokenizationService
{
    /**
     * Gera um indice cego (Blind Index) deterministico para pesquisas em banco.
     * Utiliza o BLIND_INDEX_KEY do .env. Se nao existir, faz fallback para o APP_KEY.
     *
     * @param string $value O valor sensivel (ex: CPF, CNS)
     * @return string
     */
    public static function blindIndex(?string $value): ?string
    {
        if (empty($value)) return null;

        $key = env('BLIND_INDEX_KEY');
        if (!$key) {
            $key = Config::get('app.key');
        }
        
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        if (empty($key)) {
            throw new Exception("Chave de Blind Index nao configurada.");
        }

        // Limpa formatação (ex: 123.456.789-00 -> 12345678900)
        $normalizedValue = preg_replace('/[^0-9A-Za-z]/', '', $value);
        $normalizedValue = strtolower($normalizedValue);

        // Gera o HMAC SHA256 e retorna o hex
        return hash_hmac('sha256', $normalizedValue, $key);
    }
}
