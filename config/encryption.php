<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Key Encryption Key (KEK)
    |--------------------------------------------------------------------------
    |
    | Chave mestra usada apenas para envelopar (encriptar) as DEKs (Data
    | Encryption Keys) geradas por registro. Deve ser uma chave de 256 bits
    | (32 bytes) codificada em base64. Nunca é usada para encriptar dados
    | diretamente, apenas as DEKs individuais de cada registro.
    |
    | Gere com: openssl rand -base64 32
    |
    */

    'kek' => env('APP_KEK'),

    /*
    |--------------------------------------------------------------------------
    | Blind Index Key
    |--------------------------------------------------------------------------
    |
    | Chave HMAC usada para gerar tokens determinísticos (blind index) que
    | permitem localizar registros por valores sensíveis (CPF, CNS, etc.)
    | sem a necessidade de descriptografar todos os registros do tenant.
    |
    */

    'blind_index_key' => env('BLIND_INDEX_KEY'),

];
