<?php

namespace App\Console\Commands;

use App\Models\Procedure;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CopyPublishedProceduresCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'procedures:copy-published {fromTenantId} {toTenantId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copia procedimentos com status published do tenant origem para o destino sem sobrescrever slugs existentes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fromTenantId = (string) $this->argument('fromTenantId');
        $toTenantId = (string) $this->argument('toTenantId');

        $fromTenant = Tenant::find($fromTenantId);
        if (! $fromTenant) {
            $this->error("Tenant de origem não encontrado: {$fromTenantId}");
            return self::FAILURE;
        }

        $toTenant = Tenant::find($toTenantId);
        if (! $toTenant) {
            $this->error("Tenant de destino não encontrado: {$toTenantId}");
            return self::FAILURE;
        }

        $publishedProcedures = Procedure::withoutGlobalScope('tenant')
            ->where('tenant_id', $fromTenantId)
            ->where('status', Procedure::STATUS_PUBLISHED)
            ->get();

        $copiedCount = 0;

        foreach ($publishedProcedures as $procedure) {
            // Verifica se o slug já existe no destino.
            // Não sobrescreve se o slug já existe, mantendo o mesmo slug apenas se não existir.
            $slugExists = Procedure::withoutGlobalScope('tenant')
                ->withTrashed()
                ->where('tenant_id', $toTenantId)
                ->where('slug', $procedure->slug)
                ->exists();

            if ($slugExists) {
                continue;
            }

            // Copia apenas o procedimento (sem service_points ou offerings)
            Procedure::create([
                'tenant_id' => $toTenantId,
                'title' => $procedure->title,
                'slug' => $procedure->slug,
                'category' => $procedure->category,
                'short_description' => $procedure->short_description,
                'content' => $procedure->content,
                'featured_image' => $procedure->featured_image,
                'gallery' => $procedure->gallery,
                'order' => $procedure->order,
                'status' => Procedure::STATUS_PUBLISHED,
                'meta_title' => $procedure->meta_title,
                'meta_description' => $procedure->meta_description,
                'published_at' => $procedure->published_at ?? now(),
            ]);

            $copiedCount++;
        }

        // Sem output de HTML no console (só contagem)
        $this->line((string) $copiedCount);

        return self::SUCCESS;
    }
}
