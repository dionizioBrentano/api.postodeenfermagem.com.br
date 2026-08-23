<?php

namespace App\Http\Resources;

/**
 * Item de listagem pública: mesmos campos da PublicProcedureResource, porém
 * sem o corpo (content/gallery) — listagens não precisam trafegar o
 * conteúdo rico inteiro de cada procedimento.
 *
 * @mixin \App\Models\Procedure
 */
class PublicProcedureListResource extends PublicProcedureResource
{
    protected bool $summaryOnly = true;
}
