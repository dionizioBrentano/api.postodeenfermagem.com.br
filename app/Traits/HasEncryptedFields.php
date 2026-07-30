<?php

namespace App\Traits;

use App\Models\RecordEncryptionKey;
use App\Services\CryptoService;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

/**
 * Dá a um model a capacidade de ter campos criptografados por registro
 * (uma única DEK compartilhada por todos os campos "Encryptable" daquele
 * registro), usada em conjunto com App\Casts\EncryptedWithDek.
 *
 * Problema resolvido em relação à tentativa anterior:
 * quando um model novo é criado (ex.: Patient::create(['cpf' => ..., 'cns' => ...])),
 * os casts de cada atributo são aplicados durante o fill(), ANTES do evento
 * "creating" que atribui o UUID (HasUuids) e antes do INSERT. Ou seja, o ID
 * do registro ainda não existe quando os campos são criptografados — usar o
 * ID do model para localizar/persistir a DEK nesse momento é a causa raiz do
 * bug "multiple attributes" das tentativas anteriores.
 *
 * Aqui a DEK é gerada em memória e cacheada por instância (spl_object_id),
 * sem depender do ID do registro. A persistência da DEK envelopada só
 * acontece no evento "saved" (disparado sempre depois do INSERT/UPDATE),
 * quando o ID já está garantidamente disponível.
 */
trait HasEncryptedFields
{
    /**
     * DEKs em memória, por instância de model (spl_object_id), aguardando
     * persistência. Propriedades estáticas declaradas em traits são
     * isoladas por classe que usa a trait (Patient e User não compartilham
     * o mesmo array), então não há vazamento entre models diferentes.
     *
     * @var array<int, string>
     */
    protected static array $pendingDeks = [];

    protected static function bootHasEncryptedFields(): void
    {
        static::saved(function ($model) {
            $model->persistPendingDek();
        });

        // "forceDeleted" só existe como evento registrável em models que usam
        // SoftDeletes (ex.: Patient, User). Modelos append-only como AuditLog
        // não usam SoftDeletes de propósito — chamar static::forceDeleted()
        // incondicionalmente aqui quebra o boot desses models (o método não
        // existe e a chamada cai em Model::__callStatic(), recriando a
        // instância recursivamente e lançando BadMethodCallException).
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class))) {
            static::forceDeleted(function ($model) {
                $model->recordEncryptionKey()->delete();
                unset(static::$pendingDeks[spl_object_id($model)]);
            });
        }
    }

    public function recordEncryptionKey()
    {
        return $this->morphOne(RecordEncryptionKey::class, 'keyable');
    }

    /**
     * Retorna a DEK em texto puro (binário) para este registro, gerando
     * uma nova em memória se ainda não existir nenhuma (nem em cache, nem
     * persistida). Lança exceção se a chave já tiver sido destruída
     * (crypto-shredding).
     */
    public function getDek(): string
    {
        $objectId = spl_object_id($this);

        if (isset(static::$pendingDeks[$objectId])) {
            return static::$pendingDeks[$objectId];
        }

        if ($this->exists) {
            $rek = $this->recordEncryptionKey()->first();

            if ($rek && $rek->revoked_at) {
                throw new RuntimeException(sprintf(
                    'A chave de criptografia do registro %s (%s) foi destruída via crypto-shredding e os dados não podem mais ser lidos.',
                    static::class,
                    $this->getKey()
                ));
            }

            if ($rek && $rek->encrypted_dek) {
                $dek = app(CryptoService::class)->unwrapKey($rek->encrypted_dek);
                static::$pendingDeks[$objectId] = $dek;

                return $dek;
            }
        }

        $dek = app(CryptoService::class)->generateKey();
        static::$pendingDeks[$objectId] = $dek;

        return $dek;
    }

    /**
     * Persiste (se ainda não existir) a DEK gerada em memória, agora que
     * o registro já possui um ID garantido (evento "saved").
     */
    protected function persistPendingDek(): void
    {
        $objectId = spl_object_id($this);

        if (! isset(static::$pendingDeks[$objectId])) {
            return; // nenhum campo criptografado foi tocado nesta instância
        }

        if (! $this->recordEncryptionKey()->exists()) {
            RecordEncryptionKey::create([
                'tenant_id' => $this->tenant_id ?? null,
                'keyable_type' => static::class,
                'keyable_id' => $this->getKey(),
                'encrypted_dek' => app(CryptoService::class)->wrapKey(static::$pendingDeks[$objectId]),
            ]);
        }

        unset(static::$pendingDeks[$objectId]);
    }

    /**
     * Crypto-shredding: destrói a DEK deste registro. A partir daqui, todos
     * os campos criptografados associados a este registro tornam-se
     * permanentemente ilegíveis, mesmo com a KEK correta.
     */
    public function shredEncryptionKey(): bool
    {
        $rek = $this->recordEncryptionKey()->first();

        if (! $rek) {
            return false;
        }

        $rek->update([
            'encrypted_dek' => null,
            'revoked_at' => now(),
        ]);

        unset(static::$pendingDeks[spl_object_id($this)]);

        return true;
    }
}
