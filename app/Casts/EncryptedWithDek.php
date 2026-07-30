<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use App\Services\CryptoService;
use Illuminate\Support\Str;

class EncryptedWithDek implements CastsAttributes
{
    protected $dekKey;
    protected $blindIndexField;

    public function __construct(string $dekKey = null, string $blindIndexField = null)
    {
        $this->dekKey = $dekKey;
        $this->blindIndexField = $blindIndexField;
    }

    /**
     * Cast the given value (Decrypt).
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        $idField = $this->dekKey ?: $model->getKeyName();
        $recordId = $model->{$idField};

        if (empty($recordId)) {
            return $value; // Fallback se nao houver ID
        }

        /** @var CryptoService */
        $cryptoService = app(CryptoService::class);
        $dek = $cryptoService->getDek($recordId);

        if (!$dek) {
            return '[DADO APAGADO/SHREDDED]';
        }

        $decrypted = $cryptoService->decrypt($value, $dek);

        return $decrypted !== null ? $decrypted : '[ERRO DE DECRIPTOGRAFIA]';
    }

    /**
     * Prepare the given value for storage (Encrypt).
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        $idField = $this->dekKey ?: $model->getKeyName();

        // Se o field for a primary key e estiver vazio, tenta gerar
        if ($idField === $model->getKeyName() && empty($model->{$idField})) {
            $model->{$idField} = (string) Str::uuid();
        }

        $recordId = $model->{$idField};

        if (empty($recordId)) {
             throw new \Exception("Nao e possivel criptografar: $idField ausente no modelo.");
        }

        /** @var CryptoService */
        $cryptoService = app(CryptoService::class);
        $dek = $cryptoService->getOrCreateDek($recordId);
        
        $encrypted = $cryptoService->encrypt((string) $value, $dek);

        // Se tivermos um campo de blind index, retornamos ambos em um array (suportado por Laravel Casts)
        if ($this->blindIndexField) {
            return [
                $key => $encrypted,
                $this->blindIndexField => \App\Services\TokenizationService::blindIndex((string) $value),
            ];
        }

        return $encrypted;
    }
}
