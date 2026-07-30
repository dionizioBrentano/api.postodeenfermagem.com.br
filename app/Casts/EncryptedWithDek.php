<?php

namespace App\Casts;

use App\Services\CryptoService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Cast que criptografa/descriptografa um atributo usando a DEK do registro
 * (fornecida pela trait App\Traits\HasEncryptedFields) e, opcionalmente,
 * mantém uma coluna de "blind index" (token de busca) sincronizada.
 *
 * Uso:
 *   protected function casts(): array
 *   {
 *       return [
 *           'cpf' => EncryptedWithDek::class.':cpf_token', // com blind index
 *           'old_values' => EncryptedWithDek::class,       // sem blind index
 *       ];
 *   }
 *
 * Importante: set() retorna um array [coluna => valor] (suporte nativo do
 * Eloquent para "value object casting" com múltiplas colunas), em vez de
 * atribuir diretamente em $model->{$coluna}. Atribuição direta durante o
 * set() de outro atributo é o que causava o notice de "indirect modification
 * of overloaded property" na tentativa anterior.
 */
class EncryptedWithDek implements CastsAttributes
{
    public function __construct(private readonly ?string $blindIndexColumn = null)
    {
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! method_exists($model, 'getDek')) {
            throw new LogicException(sprintf(
                '%s precisa usar a trait App\Traits\HasEncryptedFields para ter campos criptografados.',
                get_class($model)
            ));
        }

        return app(CryptoService::class)->decrypt($value, $model->getDek());
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            $result = [$key => null];

            if ($this->blindIndexColumn) {
                $result[$this->blindIndexColumn] = null;
            }

            return $result;
        }

        $crypto = app(CryptoService::class);
        $dek = $model->getDek();

        $result = [$key => $crypto->encrypt((string) $value, $dek)];

        if ($this->blindIndexColumn) {
            $result[$this->blindIndexColumn] = $crypto->blindIndex((string) $value);
        }

        return $result;
    }
}
