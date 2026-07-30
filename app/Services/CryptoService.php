<?php

namespace App\Services;

use App\Models\RecordEncryptionKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Encryption\Encrypter;
use Exception;

class CryptoService
{
    protected const CIPHER = 'aes-256-gcm';
    protected const IV_LENGTH = 12; // GCM uses 12 bytes IV
    protected const TAG_LENGTH = 16;

    protected $kekEncrypter;

    public function __construct()
    {
        // Usa o APP_KEK se existir, senao fallback pro APP_KEY
        $kek = env('APP_KEK');
        if (!$kek) {
            $kek = Config::get('app.key');
        }

        // Se a chave comecar com base64:, remove
        if (str_starts_with($kek, 'base64:')) {
            $kek = base64_decode(substr($kek, 7));
        }

        if (empty($kek)) {
            throw new Exception("Nenhuma chave KEK ou APP_KEY configurada para o CryptoService.");
        }

        $this->kekEncrypter = new Encrypter($kek, Config::get('app.cipher', 'AES-256-CBC'));
    }

    /**
     * Retorna a DEK de um registro. Se nao existir, gera e salva.
     */
    public function getOrCreateDek(string $recordId): string
    {
        $keyRecord = RecordEncryptionKey::firstOrCreate(
            ['record_id' => $recordId],
            ['encrypted_dek' => $this->kekEncrypter->encrypt(random_bytes(32))]
        );

        return $this->kekEncrypter->decrypt($keyRecord->encrypted_dek);
    }

    /**
     * Retorna a DEK de um registro. Retorna null se nao existir.
     */
    public function getDek(string $recordId): ?string
    {
        $keyRecord = RecordEncryptionKey::where('record_id', $recordId)->first();
        if (!$keyRecord) return null;

        return $this->kekEncrypter->decrypt($keyRecord->encrypted_dek);
    }

    /**
     * Destroi a DEK de um registro (Crypto-shredding)
     */
    public function shred(string $recordId): bool
    {
        return RecordEncryptionKey::where('record_id', $recordId)->delete() > 0;
    }

    /**
     * Criptografa o dado com a DEK
     */
    public function encrypt(string $plainText, string $dek): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        
        $ciphertext = openssl_encrypt(
            $plainText,
            self::CIPHER,
            $dek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new Exception("Falha ao criptografar o dado com AES-256-GCM.");
        }

        // Retorna Base64(IV . Ciphertext . Tag)
        return base64_encode($iv . $ciphertext . $tag);
    }

    /**
     * Descriptografa o dado com a DEK
     */
    public function decrypt(string $encryptedPayload, string $dek): ?string
    {
        $decoded = base64_decode($encryptedPayload);
        if ($decoded === false) return null;

        $iv = substr($decoded, 0, self::IV_LENGTH);
        $tag = substr($decoded, -self::TAG_LENGTH);
        $ciphertext = substr($decoded, self::IV_LENGTH, -self::TAG_LENGTH);

        if (strlen($iv) !== self::IV_LENGTH || strlen($tag) !== self::TAG_LENGTH) {
            return null; // Payload corrompido
        }

        $plainText = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $dek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plainText === false ? null : $plainText;
    }
}
