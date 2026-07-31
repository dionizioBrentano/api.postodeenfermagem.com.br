<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Endpoint de debug para ler/limpar storage/logs/laravel.log via HTTP, sem
 * precisar de SSH toda hora durante o desenvolvimento.
 *
 * Protegido por: auth:sanctum + ability "tenant:admin" (só o usuário admin
 * seedado tem essa ability) + a flag LOG_VIEWER_ENABLED no .env (desligada
 * por padrão). É uma ferramenta temporária de debug — desligue a flag ou
 * remova as rotas quando não precisar mais.
 */
class LogViewerController extends Controller
{
    private function assertEnabled(): void
    {
        abort_unless(config('logging.viewer_enabled'), 404);
    }

    /**
     * Retorna as últimas N linhas de storage/logs/laravel.log.
     */
    public function tail(Request $request)
    {
        $this->assertEnabled();

        $lines = (int) $request->query('lines', 200);
        $lines = max(1, min($lines, 2000));

        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            return response()->json([
                'lines' => 0,
                'size_bytes' => 0,
                'content' => '',
                'message' => 'Arquivo de log ainda não existe.',
            ]);
        }

        return response()->json([
            'lines' => $lines,
            'size_bytes' => File::size($path),
            'updated_at' => date('c', File::lastModified($path)),
            'content' => $this->tailFile($path, $lines),
        ]);
    }

    /**
     * Zera o conteúdo do arquivo de log (equivalente a `> laravel.log`).
     */
    public function clear(Request $request)
    {
        $this->assertEnabled();

        $path = storage_path('logs/laravel.log');

        if (File::exists($path)) {
            file_put_contents($path, '');
        }

        return response()->json(['message' => 'Log limpo com sucesso.']);
    }

    /**
     * Lê as últimas N linhas de um arquivo sem carregar o arquivo inteiro em
     * memória (importante, já que laravel.log pode crescer bastante).
     */
    private function tailFile(string $path, int $lines): string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return '';
        }

        $bufferSize = 4096;
        $chunk = '';

        fseek($handle, 0, SEEK_END);
        $pos = ftell($handle);
        $lineCount = 0;

        while ($pos > 0 && $lineCount <= $lines) {
            $readSize = min($bufferSize, $pos);
            $pos -= $readSize;
            fseek($handle, $pos);
            $chunk = fread($handle, $readSize).$chunk;
            $lineCount = substr_count($chunk, "\n");
        }

        fclose($handle);

        $allLines = explode("\n", $chunk);

        return implode("\n", array_slice($allLines, -$lines));
    }
}
