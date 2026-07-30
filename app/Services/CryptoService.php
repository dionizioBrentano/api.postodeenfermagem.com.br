<?php

namespace App\Services;

use RuntimeException;

/**
 * Serviço central de criptografia por registro (AES-256-GCM).
 *
 * Cada registro sensível possui sua própria DEK (Data Encryption Key),
 * gerada aleatoriamente. A DEK é envelopada (encriptada) com a KEK
 * (Key Encryption Key) da aplicação antes de ser persistida.
 *
 * Formato de payload (encrypt/wrapKey): base64(iv[12 bytes] . ciphertext . tag[16 bytes])
 * Um único encode/decode, sem envelopes JSON aninhados — isso evita os bugs
 * de parsing (tag vazia, base64 duplicado) encontrados na tentativa anterior.
 */
class CryptoService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const KEY_LENGTH = 32;

    /**
     * Gera uma nova DEK de 256 bits em binário puro.
     */
    public function generateKey(): string
    {
        return random_bytes(self::KEY_LENGTH);
    }

    /**
     * Criptografa um valor em texto puro usando a chave (DEK) fornecida.
     *
     * @param  string  $plaintext
     * @param  string  $key  Chave binária de 32 bytes.
     * @return string  Payload em base64 (iv + ciphertext + tag).
     */
    public function encrypt(string $plaintext, string $key): string
    {
        $this->assertKeyLength($key);

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Falha ao criptografar o valor: '.(openssl_error_string() ?: 'erro desconhecido'));
        }

        if (strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Falha ao criptografar o valor: tag GCM com tamanho inválido.');
        }

        return base64_encode($iv.$ciphertext.$tag);
    }

    /**
     * Descriptografa uma string gerada por encrypt()/wrapKey().
     *
     * Retorna null (nunca lança exceção) quando o payload está vazio ou
     * quando a autenticação da tag GCM falha — isso permite que os casts
     * tratem "não foi possível ler" de forma previsível.
     */
    public function decrypt(?string $payload, string $key): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $this->assertKeyLength($key);

        $raw = base64_decode($payload, true);

        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, -self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH, -self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext === false ? null : $plaintext;
    }

    /**
     * Envelopa (encripta) uma DEK usando a KEK da aplicação.
     */
    public function wrapKey(string $rawKey): string
    {
        return $this->encrypt($rawKey, $this->kek());
    }

    /**
     * Desembrulha (decripta) uma DEK usando a KEK da aplicação.
     *
     * @throws RuntimeException  Se a KEK estiver incorreta ou o dado corrompido.
     */
    public function unwrapKey(string $wrappedKey): string
    {
        $key = $this->decrypt($wrappedKey, $this->kek());

        if ($key === null) {
            throw new RuntimeException('Não foi possível desembrulhar a DEK. A KEK pode estar incorreta ou o dado corrompido.');
        }

        return $key;
    }

    /**
     * Gera um índice cego (blind index) determinístico via HMAC-SHA256,
     * permitindo localizar registros por valor sensível sem descriptografar
     * a base inteira. O valor é normalizado antes do hash (ver normalize()).
     */
    public function blindIndex(string $value): string
    {
        return hash_hmac('sha256', $this->normalize($value), $this->blindIndexKey());
    }

    /**
     * Normaliza um valor antes de gerar o blind index: remove tudo que não
     * for dígito, para que "123.456.789-00" e "12345678900" gerem o mesmo
     * índice. Se não houver dígitos, usa o valor original (trim).
     */
    public function normalize(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);

        return $digits !== '' ? $digits : trim($value);
    }

    private function assertKeyLength(string $key): void
    {
        if (strlen($key) !== self::KEY_LENGTH) {
            throw new RuntimeException(sprintf('Chave de criptografia inválida: esperado %d bytes, recebido %d.', self::KEY_LENGTH, strlen($key)));
        }
    }

    private function kek(): string
    {
        $kek = config('encryption.kek');

        if (! $kek) {
            throw new RuntimeException('APP_KEK não configurada. Defina uma chave de 32 bytes em base64 no .env (openssl rand -base64 32).');
        }

        $decoded = base64_decode($kek, true);

        if ($decoded === false || strlen($decoded) !== self::KEY_LENGTH) {
            throw new RuntimeException('APP_KEK inválida. Deve ser uma chave de 256 bits (32 bytes) codificada em base64.');
        }

        return $decoded;
    }

    private function blindIndexKey(): string
    {
        $key = config('encryption.blind_index_key');

        if (! $key) {
            throw new RuntimeException('BLIND_INDEX_KEY não configurada. Defina no .env (openssl rand -hex 32).');
        }

        return $key;
    }
}
